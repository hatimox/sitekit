# Web Apps

## Creating Web Apps

Create a new web application on any provisioned server.

**Steps:**
1. Navigate to "Web Apps" → "Create Web App"
2. Select a server to deploy to
3. Enter your domain name (e.g., `example.com`)
4. Choose the **Application Type** (PHP, Node.js, or Static)
5. Configure runtime settings
6. Connect your Git repository

**Application Types:**
- **PHP**: Laravel, WordPress, Symfony, or any PHP application
- **Node.js**: Next.js, NestJS, Express, Nuxt.js, or any Node.js application
- **Static**: HTML, React SPA, Vue SPA, or any static site

---

## Node.js Applications

Deploy and manage Node.js applications with full support for modern frameworks.

### Creating a Node.js Web App

1. Navigate to "Web Apps" → "Create Web App"
2. Select "Node.js" as the Application Type
3. Configure Node.js settings:
   - **Node.js Version**: Choose from 18, 20, 22, or 24 LTS
   - **Package Manager**: npm, yarn, or pnpm
   - **Framework**: Select your framework for optimized defaults
   - **Start Command**: How to start your app (e.g., `npm start`)
   - **Build Command**: How to build your app (e.g., `npm run build`)

### Supported Frameworks

| Framework | Default Start Command | Default Build | Static Assets |
|-----------|----------------------|---------------|---------------|
| Next.js | `npm start` | `npm run build` | `/_next/static/` |
| Nuxt.js | `node .output/server/index.mjs` | `npm run build` | `/_nuxt/` |
| NestJS | `node dist/main` | `npm run build` | - |
| Express | `node src/index.js` | - | - |
| Remix | `npm start` | `npm run build` | `/build/` |
| Astro | `node dist/server/entry.mjs` | `npm run build` | `/_astro/` |
| SvelteKit | `node build/index.js` | `npm run build` | `/_app/` |

### Port Configuration

Each Node.js app is automatically assigned a unique port (3000-3999). Nginx reverse proxy routes traffic from port 80/443 to your app's port.

- Port is allocated automatically on app creation
- Port is released when app is deleted
- For monorepos, multiple consecutive ports can be allocated

### Environment Variables

Node.js apps automatically have these environment variables set:
- `NODE_ENV=production`
- `PORT=<assigned port>`
- `HOME=/home/<system user>`

Add custom variables in the Environment Variables section.

### Prisma Support

If your project uses Prisma, add these to your pre-deploy script:
```bash
npx prisma generate
npx prisma migrate deploy
```

### Deploy Hooks

Use pre-deploy and post-deploy scripts for database migrations, cache clearing, etc.

**Pre-Deploy Script** (runs before build):
```bash
# Example: Prisma migrations
npx prisma migrate deploy
npx prisma generate
```

**Post-Deploy Script** (runs after symlink swap):
```bash
# Example: Clear cache
npm run cache:clear
```

---

## PHP Applications

**Web Root:**
The default web root is `/public` for Laravel. Adjust for other frameworks:
- WordPress: `/` (root)
- Symfony: `/public`
- CodeIgniter: `/public`

---

## Domain Aliases

Add additional domains or subdomains that should serve the same web application.

**Common Use Cases:**
- Add `www.example.com` alongside `example.com`
- Add staging subdomain `staging.example.com`
- Add multiple domains pointing to the same app

**Adding Aliases:**
1. Go to Web Apps → Select your app → Edit
2. Find the "Aliases" field
3. Type the additional domain and press Enter
4. Save the web app

**What Happens:**
- Nginx configuration is regenerated with all domains in `server_name`
- SSL certificates (if enabled) will include alias domains
- DNS must point all domains to your server IP

**Example:**
Primary domain: `example.com`
Aliases: `www.example.com`, `app.example.com`

Results in nginx config:
```nginx
server_name example.com www.example.com app.example.com;
```

**Important:**
- Ensure DNS A records exist for all alias domains
- SSL certificates must cover all domains (use "Reissue SSL" after adding aliases)

---

## Clear Cache (FTP/SFTP Uploads)

When you upload files via FTP or SFTP (instead of Git deployment), PHP may serve cached versions of your old files due to OPcache.

**Using Clear Cache:**
1. Upload your files via FTP/SFTP client (FileZilla, Cyberduck, etc.)
2. Go to Web Apps → Select your app
3. Click the **"Clear Cache"** button
4. Confirm to clear the PHP cache

**What it does:**
- Restarts PHP-FPM for your app's PHP version
- Clears OPcache so PHP reads the updated files from disk
- Your changes appear immediately

**When to use:**
- After uploading files via FTP/SFTP
- After manually editing files on the server via SSH
- When file changes don't appear on your site

**Note:** Git deployments automatically handle cache clearing, so you don't need to click this button after using the Deploy feature.

---

## Deployments

Deployments pull code from Git and run your build process.

**Deployment Process:**
1. Creates new release directory
2. Clones repository at specified branch
3. Links shared files (storage, .env)
4. Runs deploy script
5. Updates symlink to new release
6. Removes old releases (keeps last 5)

**Zero-Downtime:**
The symlink switch is atomic, meaning your site experiences no downtime during deployment.

**Triggering Deployments:**
- **Manual**: Click "Deploy" button in SiteKit
- **Webhook**: Use the deploy webhook URL with GitHub/GitLab
- **API**: POST to the deployment endpoint

**Deployment Status:**
- **Pending**: Queued for deployment
- **Running**: Currently deploying
- **Success**: Completed successfully
- **Failed**: An error occurred (check logs)

---

## Deploy Scripts

Deploy scripts run after code is pulled from Git. They typically install dependencies and build assets.

**Default Laravel Script:**
```bash
# Install PHP dependencies
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Install Node dependencies
npm ci
npm run build

# Laravel tasks
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Available Variables:**
- `$RELEASE_PATH` - Current release directory
- `$SHARED_PATH` - Shared files directory
- `$PHP_VERSION` - Configured PHP version

**Tips:**
- Use `--no-dev` for production dependencies
- Cache configs for better performance
- Run migrations manually or add `php artisan migrate --force`

---

## Environment Variables

Environment variables are stored securely and written to your `.env` file during deployment.

**Setting Variables:**
1. Go to Web App → Environment tab
2. Add key-value pairs
3. Deploy to apply changes

**Format:**
```
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
```

**Security Notes:**
- Variables are encrypted at rest
- Never commit `.env` files to Git
- Use unique values for `APP_KEY`

**Laravel Tip:**
Generate a new app key: `php artisan key:generate --show`
