# SiteKit

**Modern Server Management Platform**

SiteKit is an open-source server management platform that makes it easy to deploy and manage web applications on your own servers.

**Website:** [www.sitekit.dev](https://www.sitekit.dev)
**By:** [AvanSaber](https://avansaber.com)

## Features

- **One-Click Server Provisioning** - Connect any Ubuntu server and provision it automatically
- **Git-Based Deployments** - Deploy from GitHub, GitLab, or Bitbucket with zero-downtime
- **Free SSL Certificates** - Automatic Let's Encrypt SSL with auto-renewal
- **Database Management** - MySQL, MariaDB, and PostgreSQL support
- **Background Workers** - Supervisor-managed queue workers and daemons
- **Health Monitoring** - Uptime monitoring with multi-channel alerts
- **AI-Powered Assistance** - Built-in AI assistant for troubleshooting and optimization
- **Multi-Tenant Teams** - Team-based access control and collaboration

## Tech Stack

- **Backend:** Laravel 12+ with Filament 3 admin panel
- **Agent:** Go-based server agent for remote execution
- **Frontend:** Livewire, Alpine.js, Tailwind CSS
- **AI:** Multi-provider support (OpenAI, Anthropic Claude, Google Gemini)

## Requirements

- PHP 8.2 or higher
- Composer 2.x
- Node.js 18+ and npm
- MySQL 8.0+ / MariaDB 10.6+ / PostgreSQL 14+ (or SQLite for development)
- Redis (optional, for caching/queues)

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/avansaber/sitekit.git
cd sitekit
```

### 2. Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies and build assets
npm install
npm run build
```

### 3. Configure Environment

```bash
# Copy the example environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

Edit `.env` and configure your database connection:

```env
# For MySQL/MariaDB
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sitekit
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Or for SQLite (development)
DB_CONNECTION=sqlite
# Creates database/database.sqlite automatically
```

### 4. Run Database Migrations

```bash
php artisan migrate
```

### 5. Create Admin User (Optional)

```bash
php artisan make:filament-user
```

### 6. Start the Application

For development, you can use the built-in development command that starts all required services:

```bash
composer dev
```

This starts:
- Laravel development server (http://localhost:8000)
- Queue worker
- Log viewer (Pail)
- Vite dev server for hot reload

**Or start services individually:**

```bash
# Start the web server
php artisan serve

# In a separate terminal, start the queue worker
php artisan queue:work

# In another terminal, run Vite for asset compilation (development only)
npm run dev
```

## Important: Public URL Requirement

SiteKit requires a **publicly accessible URL** for several features to work:

| Feature | Why it needs public URL |
|---------|------------------------|
| OAuth (GitHub/GitLab/Bitbucket) | Provider redirects back to your app |
| Webhooks | Git providers send push events to your app |
| Server Agent | Managed servers call back to report status |

### Local Development with ngrok

For local development, use [ngrok](https://ngrok.com/) or similar tunneling service:

```bash
# Install ngrok (macOS)
brew install ngrok

# Start tunnel to your local app
ngrok http 8000
```

Then update your `.env`:

```env
APP_URL=https://your-subdomain.ngrok-free.app

# OAuth callback URLs will use this automatically
GITHUB_REDIRECT_URI=${APP_URL}/oauth/github/callback
```

**Also update your OAuth app settings** in GitHub/GitLab/Bitbucket to use the ngrok URL for the callback.

> **Note:** With ngrok free tier, the URL changes each restart. Consider ngrok paid or [Cloudflare Tunnel](https://developers.cloudflare.com/cloudflare-one/connections/connect-apps/) for a stable URL.

### Alternative: Laravel Herd/Valet with Expose

If using Laravel Herd or Valet, you can use [Expose](https://expose.dev/) or share via Herd's built-in sharing.

## Architecture Note

SiteKit is a **control plane** that manages OTHER servers. This means:

1. **SiteKit itself** runs on your development machine or a production server
2. **Managed servers** are the VPS/cloud servers that SiteKit provisions and controls

For self-hosted production:
- You need to manually set up the first server where SiteKit runs
- Install PHP, MySQL, Nginx, etc. manually (or use another tool)
- Then SiteKit can manage your other servers

This is the standard pattern for server management tools (Forge, Coolify, CapRover all work this way).

## Production Deployment

### Queue Worker

The queue worker is required for background jobs (server provisioning, deployments, SSL issuance, etc.).

**Using Supervisor (recommended):**

Create `/etc/supervisor/conf.d/sitekit-worker.conf`:

```ini
[program:sitekit-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/sitekit/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/sitekit/storage/logs/worker.log
stopwaitsecs=3600
```

Then run:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start sitekit-worker:*
```

### Cron Job (Task Scheduler)

Laravel's task scheduler handles recurring tasks like SSL renewal checks, health monitoring, etc.

Add this entry to your crontab (`crontab -e`):

```cron
* * * * * cd /path/to/sitekit && php artisan schedule:run >> /dev/null 2>&1
```

### Web Server Configuration

**Nginx Example:**

```nginx
server {
    listen 80;
    server_name sitekit.yourdomain.com;
    root /path/to/sitekit/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Environment Variables for Production

Key environment variables to configure:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sitekit.yourdomain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=sitekit
DB_USERNAME=sitekit
DB_PASSWORD=secure_password

# Queue (use database or redis)
QUEUE_CONNECTION=database

# Cache (use redis for production)
CACHE_STORE=redis

# Session
SESSION_DRIVER=database

# Mail (for notifications)
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=noreply@yourdomain.com

# AI Features (optional)
OPENAI_API_KEY=your-openai-key
ANTHROPIC_API_KEY=your-anthropic-key
GOOGLE_AI_API_KEY=your-google-key
```

## Agent Repository

The SiteKit server agent is available at: [github.com/avansaber/sitekit-agent](https://github.com/avansaber/sitekit-agent)

The agent is automatically downloaded and installed when you provision a new server through the SiteKit UI.

## Running Tests

```bash
# Run PHPUnit tests
php artisan test

# Run browser tests (requires Chrome)
php artisan dusk

# Configure test environment variables in .env:
DUSK_TEST_USER_EMAIL=your-test-user@example.com
DUSK_TEST_SERVER_IP=your-test-server-ip
```

## Development

### Quick Start

```bash
# Start all development services
composer dev
```

### Asset Compilation

```bash
# Development (with hot reload)
npm run dev

# Production build
npm run build
```

### Code Formatting

```bash
# Format PHP code
./vendor/bin/pint
```

## Documentation

Visit [www.sitekit.dev/docs](https://www.sitekit.dev/docs) for comprehensive documentation.

## License

SiteKit is source-available software licensed under the AvanSaber License. See the [LICENSE](LICENSE) file for details.

**Key points:**
- Free for personal use and small businesses
- Limited to 20 servers
- Commercial license required for larger deployments or SaaS usage (revenue > $100K/year)
- Contact licensing@avansaber.com for commercial licensing

## Contributing

Contributions are welcome! Please read our contributing guidelines before submitting pull requests.

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Support

- **Issues:** [GitHub Issues](https://github.com/avansaber/sitekit/issues)
- **Discussions:** [GitHub Discussions](https://github.com/avansaber/sitekit/discussions)
- **Website:** [avansaber.com](https://avansaber.com)

---

Made with ❤️ by [AvanSaber](https://avansaber.com)
