# Servers

## Connecting a Server

**Requirements:**
- Ubuntu 20.04, 22.04, or 24.04 (fresh install recommended)
- Root SSH access
- Minimum 1 CPU, 1GB RAM, 10GB disk

**Steps:**
1. Navigate to "Servers" → "Add Server"
2. Enter a name for your server
3. Copy the provisioning command shown
4. SSH into your server as root
5. Paste and run the command

**What the Bootstrap Does:**
- Creates the `sitekit` system user
- Downloads and installs the SiteKit agent
- Starts the agent service
- Registers with SiteKit

**After Bootstrap:**
Software installation begins automatically with real-time progress tracking.

---

## Provisioning Process

SiteKit uses a two-phase provisioning approach for reliability:

**Phase 1: Bootstrap**
- Runs the curl command you paste on your server
- Creates system user and directories
- Installs the SiteKit agent
- Agent connects back to SiteKit

**Phase 2: Software Installation**
- Agent receives installation jobs
- Installs components in parallel:
  - **System**: Security updates, UFW firewall, fail2ban
  - **Web Server**: Nginx with security headers
  - **PHP**: Versions 8.1, 8.2, 8.3 with extensions
  - **Database**: MariaDB with secure credentials
  - **Cache**: Redis
  - **Tools**: Composer, Node.js, Supervisor

**Progress Tracking:**
- Real-time progress bar shows completion percentage
- Each step shows status: pending, in progress, completed, or failed
- Failed steps can be retried individually
- Optional steps can be skipped

**If Something Fails:**
1. Check the error message shown for the failed step
2. Click the retry button to try again
3. If the issue persists, check server connectivity
4. Optional components can be skipped

---

## Managing Services

**Installed Services:**
After provisioning, your server includes:
- Nginx (web server)
- PHP 8.1, 8.2, 8.3, 8.4, 8.5 FPM (PHP processor)
- MariaDB (database)
- Redis (cache)
- Supervisor (process manager)

**Service Actions:**
- **Start**: Start a stopped service
- **Stop**: Stop a running service
- **Restart**: Stop and start the service
- **Reload**: Reload configuration without downtime

**Accessing Service Controls:**
1. Go to Servers → Select your server
2. Click on the "Services" tab
3. Click on a service to view details and actions

**Viewing Status:**
Services are listed on the server detail page with real-time status indicators.

---

## When to Restart Services

**Restart PHP-FPM when:**
- Files are updated but changes don't appear (OPcache is caching old code)
- After uploading files via FTP/SFTP
- After changing PHP configuration (php.ini)
- After installing new PHP extensions

**How to restart PHP-FPM:**
1. Go to Services → PHP 8.x (your version)
2. Click the "Restart" button
3. Wait for confirmation

**Why this works:**
PHP-FPM uses OPcache to cache compiled PHP code for performance. Restarting PHP-FPM clears this cache, forcing PHP to read the updated files from disk.

**Restart Nginx when:**
- After manually editing nginx configuration files
- If web apps show 502 Bad Gateway errors
- After SSL certificate changes (though this is usually automatic)

**Restart MariaDB when:**
- After changing database configuration
- If database connections are failing
- To clear query cache

---

## PHP Configuration

**Editing PHP Configuration:**
1. Go to Services → PHP 8.x → View
2. Click "Edit Configuration"
3. Modify settings (memory_limit, upload_max_filesize, etc.)
4. Save and restart PHP-FPM

**Installing PHP Extensions:**
1. Go to Services → PHP 8.x → View
2. Click "Install Extension"
3. Select the extension to install
4. PHP-FPM will restart automatically

**Common Extensions:**
- `gd` - Image processing
- `imagick` - Advanced image manipulation
- `redis` - Redis PHP client
- `memcached` - Memcached client
- `intl` - Internationalization

---

## Service Credentials

Database credentials are stored securely:
- MariaDB root: `/opt/sitekit/config/.mysql_root`
- MariaDB sitekit user: `/opt/sitekit/config/.mysql_sitekit`
- PostgreSQL: `/opt/sitekit/config/.pgsql_sitekit`

---

## Restore Server

The Restore Server feature removes all SiteKit components and resets the server to a clean state.

**What Gets Removed:**
- All web applications and their files
- All databases and database users
- PHP, Nginx, MariaDB, Redis, Supervisor
- Firewall rules and cron jobs
- SSL certificates
- The SiteKit agent

**What Is Preserved:**
- The `sitekit` system user (for re-provisioning)
- SSH access and keys
- Base system packages

**How to Restore:**
1. Go to Server → View
2. Click "Restore Server" button
3. Confirm the options (remove packages, remove data)
4. Type the server name to confirm
5. Wait for the restore to complete

**After Restore:**
The server returns to "pending" status and can be re-provisioned using a new command.
