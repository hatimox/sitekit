#!/bin/bash
#
# SiteKit Installation Script
# Installs SiteKit on a fresh Ubuntu 22.04/24.04 server
#
# Usage:
#   curl -sSL https://your-domain.com/install.sh | bash
#   or
#   wget -qO- https://your-domain.com/install.sh | bash
#

set -e

# =============================================================================
# Configuration
# =============================================================================
SITEKIT_VERSION="master"
SITEKIT_REPO="https://github.com/hatimox/sitekit.git"
INSTALL_DIR="/opt/sitekit"
LOG_FILE="/var/log/sitekit-install.log"
PHP_VERSION="8.4"

# =============================================================================
# Colors and Output Helpers
# =============================================================================
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color
BOLD='\033[1m'

print_banner() {
    echo -e "${CYAN}"
    echo "╔═══════════════════════════════════════════════════════════════╗"
    echo "║                                                               ║"
    echo "║   ███████╗██╗████████╗███████╗██╗  ██╗██╗████████╗           ║"
    echo "║   ██╔════╝██║╚══██╔══╝██╔════╝██║ ██╔╝██║╚══██╔══╝           ║"
    echo "║   ███████╗██║   ██║   █████╗  █████╔╝ ██║   ██║              ║"
    echo "║   ╚════██║██║   ██║   ██╔══╝  ██╔═██╗ ██║   ██║              ║"
    echo "║   ███████║██║   ██║   ███████╗██║  ██╗██║   ██║              ║"
    echo "║   ╚══════╝╚═╝   ╚═╝   ╚══════╝╚═╝  ╚═╝╚═╝   ╚═╝              ║"
    echo "║                                                               ║"
    echo "║              Server Management Made Simple                    ║"
    echo "║                                                               ║"
    echo "╚═══════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
}

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
    echo -e "${RED}Installation failed. Check $LOG_FILE for details.${NC}"
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
check_tty() {
    if [[ ! -t 0 ]] && [[ ! -e /dev/tty ]]; then
        echo ""
        echo -e "${RED}[✗] Interactive terminal required${NC}"
        echo ""
        echo "This script requires user input. Please run it using one of these methods:"
        echo ""
        echo "  Method 1 (recommended):"
        echo "    curl -sSL https://raw.githubusercontent.com/hatimox/sitekit/master/scripts/install.sh -o install.sh && bash install.sh"
        echo ""
        echo "  Method 2:"
        echo "    wget -qO install.sh https://raw.githubusercontent.com/hatimox/sitekit/master/scripts/install.sh && bash install.sh"
        echo ""
        exit 1
    fi
}

check_root() {
    if [[ $EUID -ne 0 ]]; then
        fatal "This script must be run as root. Use: sudo bash install.sh"
    fi
}

check_os() {
    if [[ ! -f /etc/os-release ]]; then
        fatal "Cannot detect OS. /etc/os-release not found."
    fi

    source /etc/os-release

    if [[ "$ID" != "ubuntu" ]]; then
        fatal "This script only supports Ubuntu. Detected: $ID"
    fi

    if [[ "$VERSION_ID" != "22.04" && "$VERSION_ID" != "24.04" ]]; then
        fatal "This script requires Ubuntu 22.04 or 24.04. Detected: $VERSION_ID"
    fi

    success "Operating system: Ubuntu $VERSION_ID"
}

check_memory() {
    local total_mem=$(free -m | awk '/^Mem:/{print $2}')

    if [[ $total_mem -lt 1024 ]]; then
        fatal "Minimum 1GB RAM required. Detected: ${total_mem}MB"
    elif [[ $total_mem -lt 2048 ]]; then
        warning "2GB RAM recommended for optimal performance. Detected: ${total_mem}MB"
    else
        success "Memory: ${total_mem}MB"
    fi
}

check_disk() {
    local free_space=$(df -BG / | awk 'NR==2 {print $4}' | tr -d 'G')

    if [[ $free_space -lt 5 ]]; then
        fatal "Minimum 5GB free disk space required. Available: ${free_space}GB"
    elif [[ $free_space -lt 10 ]]; then
        warning "10GB free disk space recommended. Available: ${free_space}GB"
    else
        success "Disk space: ${free_space}GB available"
    fi
}

check_existing_installation() {
    if [[ -d "$INSTALL_DIR" ]]; then
        warning "SiteKit directory already exists at $INSTALL_DIR"
        echo ""
        read -p "Do you want to remove it and reinstall? [y/N]: " confirm < /dev/tty
        if [[ "$confirm" =~ ^[Yy]$ ]]; then
            rm -rf "$INSTALL_DIR"
            success "Removed existing installation"
        else
            fatal "Installation cancelled. Remove $INSTALL_DIR manually to reinstall."
        fi
    fi
}

# =============================================================================
# User Input
# =============================================================================
get_user_input() {
    echo ""
    echo -e "${BOLD}Please provide the following information:${NC}"
    echo ""

    # Domain
    while true; do
        read -p "Domain name for SiteKit panel (e.g., panel.example.com): " DOMAIN < /dev/tty
        if [[ -n "$DOMAIN" && "$DOMAIN" =~ ^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?)*$ ]]; then
            break
        fi
        error "Please enter a valid domain name"
    done

    # Database choice
    echo ""
    echo "Select database server:"
    echo "  1) MySQL 8.0"
    echo "  2) MariaDB 10.11"
    while true; do
        read -p "Enter choice [1-2]: " db_choice < /dev/tty
        case $db_choice in
            1) DATABASE="mysql"; break;;
            2) DATABASE="mariadb"; break;;
            *) error "Please enter 1 or 2";;
        esac
    done

    # Admin email
    echo ""
    while true; do
        read -p "Admin email address: " ADMIN_EMAIL < /dev/tty
        if [[ "$ADMIN_EMAIL" =~ ^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$ ]]; then
            break
        fi
        error "Please enter a valid email address"
    done

    # Admin password
    echo ""
    while true; do
        read -s -p "Admin password (min 8 characters): " ADMIN_PASSWORD < /dev/tty
        echo ""
        if [[ ${#ADMIN_PASSWORD} -ge 8 ]]; then
            read -s -p "Confirm password: " ADMIN_PASSWORD_CONFIRM < /dev/tty
            echo ""
            if [[ "$ADMIN_PASSWORD" == "$ADMIN_PASSWORD_CONFIRM" ]]; then
                break
            fi
            error "Passwords do not match"
        else
            error "Password must be at least 8 characters"
        fi
    done

    # Confirmation
    echo ""
    echo -e "${BOLD}Installation Summary:${NC}"
    echo "  Domain:    $DOMAIN"
    echo "  Database:  $DATABASE"
    echo "  Admin:     $ADMIN_EMAIL"
    echo "  Path:      $INSTALL_DIR"
    echo ""
    read -p "Proceed with installation? [Y/n]: " confirm < /dev/tty
    if [[ "$confirm" =~ ^[Nn]$ ]]; then
        fatal "Installation cancelled by user"
    fi
}

# =============================================================================
# Installation Functions
# =============================================================================
install_dependencies() {
    step "Installing system dependencies..."

    export DEBIAN_FRONTEND=noninteractive

    info "Updating package lists..."
    apt-get update -qq >> "$LOG_FILE" 2>&1

    info "Installing essential packages..."
    apt-get install -y -qq \
        software-properties-common \
        apt-transport-https \
        ca-certificates \
        curl \
        wget \
        gnupg \
        lsb-release \
        unzip \
        git \
        acl \
        >> "$LOG_FILE" 2>&1

    success "System dependencies installed"
}

install_php() {
    step "Installing PHP ${PHP_VERSION}..."

    # Add PHP repository
    info "Adding PHP repository..."
    add-apt-repository -y ppa:ondrej/php >> "$LOG_FILE" 2>&1
    apt-get update -qq >> "$LOG_FILE" 2>&1

    info "Installing PHP and extensions..."
    apt-get install -y -qq \
        php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-common \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-pgsql \
        php${PHP_VERSION}-sqlite3 \
        php${PHP_VERSION}-redis \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-imagick \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-soap \
        php${PHP_VERSION}-readline \
        php${PHP_VERSION}-opcache \
        >> "$LOG_FILE" 2>&1

    # Configure PHP
    info "Configuring PHP..."
    local php_ini="/etc/php/${PHP_VERSION}/fpm/php.ini"
    sed -i "s/upload_max_filesize = .*/upload_max_filesize = 100M/" "$php_ini"
    sed -i "s/post_max_size = .*/post_max_size = 100M/" "$php_ini"
    sed -i "s/memory_limit = .*/memory_limit = 256M/" "$php_ini"
    sed -i "s/max_execution_time = .*/max_execution_time = 300/" "$php_ini"

    systemctl restart php${PHP_VERSION}-fpm

    success "PHP ${PHP_VERSION} installed and configured"
}

install_composer() {
    step "Installing Composer..."

    if command -v composer &> /dev/null; then
        info "Composer already installed, updating..."
        composer self-update >> "$LOG_FILE" 2>&1
    else
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer >> "$LOG_FILE" 2>&1
    fi

    success "Composer installed"
}

install_nodejs() {
    step "Installing Node.js..."

    # Install Node.js 20 LTS
    if ! command -v node &> /dev/null; then
        curl -fsSL https://deb.nodesource.com/setup_20.x | bash - >> "$LOG_FILE" 2>&1
        apt-get install -y -qq nodejs >> "$LOG_FILE" 2>&1
    fi

    success "Node.js $(node -v) installed"
}

install_nginx() {
    step "Installing Nginx..."

    apt-get install -y -qq nginx >> "$LOG_FILE" 2>&1
    systemctl enable nginx >> "$LOG_FILE" 2>&1

    success "Nginx installed"
}

install_redis() {
    step "Installing Redis..."

    apt-get install -y -qq redis-server >> "$LOG_FILE" 2>&1
    systemctl enable redis-server >> "$LOG_FILE" 2>&1
    systemctl start redis-server >> "$LOG_FILE" 2>&1

    success "Redis installed"
}

install_supervisor() {
    step "Installing Supervisor..."

    apt-get install -y -qq supervisor >> "$LOG_FILE" 2>&1
    systemctl enable supervisor >> "$LOG_FILE" 2>&1

    success "Supervisor installed"
}

install_mysql() {
    step "Installing MySQL 8.0..."

    apt-get install -y -qq mysql-server >> "$LOG_FILE" 2>&1
    systemctl enable mysql >> "$LOG_FILE" 2>&1
    systemctl start mysql >> "$LOG_FILE" 2>&1

    success "MySQL 8.0 installed"
}

install_mariadb() {
    step "Installing MariaDB..."

    apt-get install -y -qq mariadb-server >> "$LOG_FILE" 2>&1
    systemctl enable mariadb >> "$LOG_FILE" 2>&1
    systemctl start mariadb >> "$LOG_FILE" 2>&1

    success "MariaDB installed"
}

configure_database() {
    step "Configuring database..."

    # Generate random password for sitekit db user
    DB_PASSWORD=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 32)
    DB_NAME="sitekit"
    DB_USER="sitekit"

    if [[ "$DATABASE" == "mysql" ]]; then
        mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >> "$LOG_FILE" 2>&1
        mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';" >> "$LOG_FILE" 2>&1
        mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';" >> "$LOG_FILE" 2>&1
        mysql -e "FLUSH PRIVILEGES;" >> "$LOG_FILE" 2>&1
    else
        mariadb -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >> "$LOG_FILE" 2>&1
        mariadb -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';" >> "$LOG_FILE" 2>&1
        mariadb -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';" >> "$LOG_FILE" 2>&1
        mariadb -e "FLUSH PRIVILEGES;" >> "$LOG_FILE" 2>&1
    fi

    success "Database configured"
}

install_sitekit() {
    step "Installing SiteKit..."

    info "Cloning repository..."
    git clone --branch "$SITEKIT_VERSION" --depth 1 "$SITEKIT_REPO" "$INSTALL_DIR" >> "$LOG_FILE" 2>&1

    cd "$INSTALL_DIR"

    info "Installing PHP dependencies..."
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction >> "$LOG_FILE" 2>&1

    info "Installing Node.js dependencies..."
    npm ci >> "$LOG_FILE" 2>&1

    info "Building assets..."
    npm run build >> "$LOG_FILE" 2>&1

    # Generate app key
    APP_KEY=$(php -r "echo 'base64:' . base64_encode(random_bytes(32));")

    # Create .env file
    info "Configuring environment..."
    cat > .env << EOF
APP_NAME=SiteKit
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL=https://${DOMAIN}

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}

BROADCAST_CONNECTION=reverb
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
CACHE_STORE=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_FROM_ADDRESS="${ADMIN_EMAIL}"
MAIL_FROM_NAME="\${APP_NAME}"
EOF

    info "Running database migrations..."
    php artisan migrate --force >> "$LOG_FILE" 2>&1

    info "Creating admin user..."
    php artisan tinker --execute="
        \$user = \App\Models\User::create([
            'name' => 'Admin',
            'email' => '${ADMIN_EMAIL}',
            'password' => bcrypt('${ADMIN_PASSWORD}'),
            'email_verified_at' => now(),
        ]);
        \$team = \$user->ownedTeams()->create(['name' => 'Default Team', 'personal_team' => true]);
        \$user->current_team_id = \$team->id;
        \$user->save();
    " >> "$LOG_FILE" 2>&1

    info "Optimizing application..."
    php artisan config:cache >> "$LOG_FILE" 2>&1
    php artisan route:cache >> "$LOG_FILE" 2>&1
    php artisan view:cache >> "$LOG_FILE" 2>&1
    php artisan icons:cache >> "$LOG_FILE" 2>&1

    # Set permissions
    info "Setting permissions..."
    chown -R www-data:www-data "$INSTALL_DIR"
    chmod -R 755 "$INSTALL_DIR"
    chmod -R 775 "$INSTALL_DIR/storage" "$INSTALL_DIR/bootstrap/cache"

    success "SiteKit installed"
}

configure_nginx() {
    step "Configuring Nginx..."

    cat > /etc/nginx/sites-available/sitekit << EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};
    root ${INSTALL_DIR}/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

    # Enable site
    ln -sf /etc/nginx/sites-available/sitekit /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/default

    # Test and reload
    nginx -t >> "$LOG_FILE" 2>&1
    systemctl reload nginx

    success "Nginx configured"
}

configure_supervisor() {
    step "Configuring queue workers..."

    cat > /etc/supervisor/conf.d/sitekit-worker.conf << EOF
[program:sitekit-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${INSTALL_DIR}/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=${INSTALL_DIR}/storage/logs/worker.log
stopwaitsecs=3600
EOF

    supervisorctl reread >> "$LOG_FILE" 2>&1
    supervisorctl update >> "$LOG_FILE" 2>&1
    supervisorctl start sitekit-worker:* >> "$LOG_FILE" 2>&1

    success "Queue workers configured"
}

install_ssl() {
    step "Installing SSL certificate..."

    info "Installing Certbot..."
    apt-get install -y -qq certbot python3-certbot-nginx >> "$LOG_FILE" 2>&1

    info "Obtaining SSL certificate..."
    certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$ADMIN_EMAIL" --redirect >> "$LOG_FILE" 2>&1 || {
        warning "SSL certificate installation failed. You can retry manually with:"
        echo "  certbot --nginx -d $DOMAIN"
        return
    }

    # Setup auto-renewal
    systemctl enable certbot.timer >> "$LOG_FILE" 2>&1
    systemctl start certbot.timer >> "$LOG_FILE" 2>&1

    success "SSL certificate installed"
}

setup_firewall() {
    step "Configuring firewall..."

    if command -v ufw &> /dev/null; then
        ufw allow 'Nginx Full' >> "$LOG_FILE" 2>&1
        ufw allow OpenSSH >> "$LOG_FILE" 2>&1

        if ufw status | grep -q inactive; then
            info "UFW is inactive. Enable it with: ufw enable"
        fi
    fi

    success "Firewall configured"
}

# =============================================================================
# Main
# =============================================================================
main() {
    # Initialize log file
    mkdir -p "$(dirname "$LOG_FILE")"
    echo "SiteKit Installation Log - $(date)" > "$LOG_FILE"

    print_banner

    echo -e "${BOLD}Welcome to the SiteKit installer!${NC}"
    echo "This script will install SiteKit and all required dependencies."
    echo ""

    step "Running pre-flight checks..."
    check_tty
    check_root
    check_os
    check_memory
    check_disk
    check_existing_installation

    get_user_input

    # Install all components
    install_dependencies
    install_php
    install_composer
    install_nodejs
    install_nginx
    install_redis
    install_supervisor

    # Install selected database
    if [[ "$DATABASE" == "mysql" ]]; then
        install_mysql
    else
        install_mariadb
    fi

    configure_database
    install_sitekit
    configure_nginx
    configure_supervisor
    install_ssl
    setup_firewall

    # Final summary
    echo ""
    echo -e "${GREEN}╔═══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║          SiteKit Installation Complete!                       ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${BOLD}Access your SiteKit panel:${NC}"
    echo -e "  URL:      ${CYAN}https://${DOMAIN}${NC}"
    echo -e "  Email:    ${CYAN}${ADMIN_EMAIL}${NC}"
    echo -e "  Password: ${CYAN}(the password you entered)${NC}"
    echo ""
    echo -e "${BOLD}Installation details:${NC}"
    echo -e "  Install path:  ${INSTALL_DIR}"
    echo -e "  Database:      ${DATABASE}"
    echo -e "  PHP version:   ${PHP_VERSION}"
    echo -e "  Log file:      ${LOG_FILE}"
    echo ""
    echo -e "${BOLD}Useful commands:${NC}"
    echo -e "  View logs:     ${CYAN}tail -f ${INSTALL_DIR}/storage/logs/laravel.log${NC}"
    echo -e "  Queue status:  ${CYAN}supervisorctl status${NC}"
    echo -e "  Restart queue: ${CYAN}supervisorctl restart sitekit-worker:*${NC}"
    echo ""
    echo -e "${YELLOW}Important: Save your database password from ${LOG_FILE}${NC}"
    echo ""

    success "Installation completed successfully!"
}

# Run main function
main "$@"
