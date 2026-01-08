#!/bin/bash
#
# SiteKit Uninstall Script
# Removes SiteKit and optionally its dependencies
#
# Usage:
#   bash uninstall.sh
#

set -e

# =============================================================================
# Configuration
# =============================================================================
INSTALL_DIR="/opt/sitekit"
LOG_FILE="/var/log/sitekit-uninstall.log"

# =============================================================================
# Colors and Output Helpers
# =============================================================================
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'
BOLD='\033[1m'

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

info() {
    echo -e "${BLUE}[INFO]${NC} $1"
    log "INFO: $1"
}

success() {
    echo -e "${GREEN}[✓]${NC} $1"
    log "SUCCESS: $1"
}

warning() {
    echo -e "${YELLOW}[!]${NC} $1"
    log "WARNING: $1"
}

error() {
    echo -e "${RED}[✗]${NC} $1"
    log "ERROR: $1"
}

fatal() {
    error "$1"
    exit 1
}

step() {
    echo ""
    echo -e "${BOLD}${CYAN}▶ $1${NC}"
    log "STEP: $1"
}

# =============================================================================
# Pre-flight Checks
# =============================================================================
check_root() {
    if [[ $EUID -ne 0 ]]; then
        fatal "This script must be run as root. Use: sudo bash uninstall.sh"
    fi
}

# =============================================================================
# Uninstall Functions
# =============================================================================
remove_sitekit() {
    step "Removing SiteKit application..."

    if [[ -d "$INSTALL_DIR" ]]; then
        rm -rf "$INSTALL_DIR"
        success "Removed $INSTALL_DIR"
    else
        warning "SiteKit directory not found at $INSTALL_DIR"
    fi
}

remove_nginx_config() {
    step "Removing Nginx configuration..."

    if [[ -f /etc/nginx/sites-enabled/sitekit ]]; then
        rm -f /etc/nginx/sites-enabled/sitekit
        success "Removed Nginx site symlink"
    fi

    if [[ -f /etc/nginx/sites-available/sitekit ]]; then
        rm -f /etc/nginx/sites-available/sitekit
        success "Removed Nginx site config"
    fi

    # Restore default site if nothing else is enabled
    if [[ -z "$(ls -A /etc/nginx/sites-enabled/ 2>/dev/null)" ]]; then
        if [[ -f /etc/nginx/sites-available/default ]]; then
            ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/
            info "Restored default Nginx site"
        fi
    fi

    if systemctl is-active --quiet nginx; then
        systemctl reload nginx
        success "Reloaded Nginx"
    fi
}

remove_supervisor_config() {
    step "Removing Supervisor configuration..."

    if [[ -f /etc/supervisor/conf.d/sitekit-worker.conf ]]; then
        supervisorctl stop sitekit-worker:* >> "$LOG_FILE" 2>&1 || true
        rm -f /etc/supervisor/conf.d/sitekit-worker.conf
        supervisorctl reread >> "$LOG_FILE" 2>&1 || true
        supervisorctl update >> "$LOG_FILE" 2>&1 || true
        success "Removed Supervisor worker config"
    else
        warning "Supervisor config not found"
    fi
}

remove_database() {
    step "Removing database..."

    echo ""
    read -p "Do you want to remove the SiteKit database and user? [y/N]: " confirm < /dev/tty
    if [[ "$confirm" =~ ^[Yy]$ ]]; then
        # Try MySQL first, then MariaDB
        if command -v mysql &> /dev/null && systemctl is-active --quiet mysql; then
            mysql -e "DROP DATABASE IF EXISTS sitekit;" >> "$LOG_FILE" 2>&1 || true
            mysql -e "DROP USER IF EXISTS 'sitekit'@'localhost';" >> "$LOG_FILE" 2>&1 || true
            success "Removed MySQL database and user"
        elif command -v mariadb &> /dev/null && systemctl is-active --quiet mariadb; then
            mariadb -e "DROP DATABASE IF EXISTS sitekit;" >> "$LOG_FILE" 2>&1 || true
            mariadb -e "DROP USER IF EXISTS 'sitekit'@'localhost';" >> "$LOG_FILE" 2>&1 || true
            success "Removed MariaDB database and user"
        else
            warning "No running database server found"
        fi
    else
        info "Skipping database removal"
    fi
}

remove_ssl_certificate() {
    step "Removing SSL certificate..."

    echo ""
    read -p "Do you want to remove the SSL certificate? [y/N]: " confirm < /dev/tty
    if [[ "$confirm" =~ ^[Yy]$ ]]; then
        # Find and delete SiteKit certificates
        if command -v certbot &> /dev/null; then
            # List certificates and find sitekit-related ones
            certbot certificates 2>/dev/null | grep -A2 "Certificate Name:" | grep -B1 "Domains:" | while read -r line; do
                if echo "$line" | grep -q "Certificate Name:"; then
                    cert_name=$(echo "$line" | awk '{print $3}')
                    certbot delete --cert-name "$cert_name" --non-interactive >> "$LOG_FILE" 2>&1 || true
                    success "Removed SSL certificate: $cert_name"
                fi
            done
        else
            warning "Certbot not found"
        fi
    else
        info "Skipping SSL certificate removal"
    fi
}

remove_dependencies() {
    step "Remove installed packages?"

    echo ""
    echo "The following packages were installed by SiteKit:"
    echo "  - PHP 8.4 and extensions"
    echo "  - Nginx"
    echo "  - Redis"
    echo "  - Supervisor"
    echo "  - MySQL/MariaDB"
    echo "  - Node.js"
    echo "  - Composer"
    echo ""
    warning "Removing these may affect other applications on this server!"
    echo ""
    read -p "Do you want to remove ALL dependencies? [y/N]: " confirm < /dev/tty
    if [[ "$confirm" =~ ^[Yy]$ ]]; then
        read -p "Are you SURE? This cannot be undone. Type 'YES' to confirm: " double_confirm < /dev/tty
        if [[ "$double_confirm" == "YES" ]]; then
            info "Removing packages..."

            # Stop services first
            systemctl stop nginx >> "$LOG_FILE" 2>&1 || true
            systemctl stop php8.4-fpm >> "$LOG_FILE" 2>&1 || true
            systemctl stop redis-server >> "$LOG_FILE" 2>&1 || true
            systemctl stop supervisor >> "$LOG_FILE" 2>&1 || true
            systemctl stop mysql >> "$LOG_FILE" 2>&1 || true
            systemctl stop mariadb >> "$LOG_FILE" 2>&1 || true

            # Remove packages
            apt-get remove --purge -y \
                nginx \
                'php8.4*' \
                redis-server \
                supervisor \
                mysql-server \
                mariadb-server \
                certbot \
                python3-certbot-nginx \
                >> "$LOG_FILE" 2>&1 || true

            # Clean up
            apt-get autoremove -y >> "$LOG_FILE" 2>&1 || true

            # Remove Node.js
            if [[ -f /etc/apt/sources.list.d/nodesource.list ]]; then
                rm -f /etc/apt/sources.list.d/nodesource.list
                apt-get update >> "$LOG_FILE" 2>&1 || true
            fi
            apt-get remove --purge -y nodejs >> "$LOG_FILE" 2>&1 || true

            # Remove Composer
            rm -f /usr/local/bin/composer

            # Remove PHP repository
            add-apt-repository --remove -y ppa:ondrej/php >> "$LOG_FILE" 2>&1 || true

            success "Removed all dependencies"
        else
            info "Skipping dependency removal"
        fi
    else
        info "Skipping dependency removal"
    fi
}

cleanup_logs() {
    step "Cleaning up logs..."

    rm -f /var/log/sitekit-install.log
    info "Removed installation log"
}

# =============================================================================
# Main
# =============================================================================
main() {
    # Initialize log file
    mkdir -p "$(dirname "$LOG_FILE")"
    echo "SiteKit Uninstall Log - $(date)" > "$LOG_FILE"

    echo ""
    echo -e "${RED}╔═══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║              SiteKit Uninstaller                              ║${NC}"
    echo -e "${RED}╚═══════════════════════════════════════════════════════════════╝${NC}"
    echo ""

    warning "This will remove SiteKit from your server."
    echo ""
    read -p "Are you sure you want to continue? [y/N]: " confirm < /dev/tty
    if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
        echo "Uninstall cancelled."
        exit 0
    fi

    check_root

    remove_supervisor_config
    remove_nginx_config
    remove_sitekit
    remove_database
    remove_ssl_certificate
    remove_dependencies
    cleanup_logs

    echo ""
    echo -e "${GREEN}╔═══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║          SiteKit has been uninstalled                         ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    success "Uninstall completed!"
    echo ""
    info "Log file: $LOG_FILE"
    echo ""
}

main "$@"
