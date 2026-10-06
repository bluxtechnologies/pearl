# Pearl Framework — Production Deployment & Distribution Guide

This guide details the complete process for deploying PEARL applications to production servers and distributing the framework via Packagist and standalone release archives.

---

## 1. Production Architecture Overview

In production, PEARL operates with zero reliance on runtime compilers or development servers:
- **Web Server:** Nginx, Apache, or Caddy pointing its `DocumentRoot` directly to `public/`.
- **PHP Handler:** PHP-FPM (PHP 8.3+ or 8.4+) with OPcache enabled.
- **Database:** MySQL, PostgreSQL, or SQLite with single-DDL atomic migrations.
- **Session & Cache Storage:** Database (`sessions`, `cache_entries`) or Redis (`SESSION_DRIVER=redis`, `CACHE_DRIVER=redis`) for cluster persistence.
- **Queue Workers:** Dedicated daemons (`php bin/pearl notify:work`) supervised via Systemd or Supervisor.
- **Assets:** Pre-compiled static CSS/JS in `public/build/` served with 1-year immutable caching.

---

## 2. Server Preparation (Ubuntu / Debian Example)

### 2.1 Install System Packages

```bash
sudo apt update
sudo apt install -y php8.4-fpm php8.4-cli php8.4-pdo php8.4-mysql php8.4-pgsql \
                    php8.4-sqlite3 php8.4-mbstring php8.4-xml php8.4-curl \
                    php8.4-zip php8.4-redis nginx certbot python3-certbot-nginx
```

### 2.2 Configure PHP-FPM OPcache

Edit `/etc/php/8.4/fpm/php.ini`:

```ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.save_comments=1
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.4-fpm
```

---

## 3. Application Deployment Step-by-Step

### 3.1 Deploy Codebase

Clone your repository or extract the distribution archive to `/var/www/pearl`:

```bash
sudo mkdir -p /var/www/pearl
sudo chown -R www-data:www-data /var/www/pearl
```

### 3.2 Configure Environment (`.env`)

Copy the production configuration template:

```bash
cp .env.production.example .env
nano .env
```

Ensure the following production invariants:
```ini
APP_ENV=production
APP_URL=https://your-domain.com
DB_ENGINE=mysql
DB_HOST=127.0.0.1
DB_DATABASE=pearl_production
DB_USERNAME=pearl_user
DB_PASSWORD=YOUR_STRONG_PASSWORD
SESSION_DRIVER=database
CACHE_DRIVER=database
QUEUE_DRIVER=database
CSP_ENABLED=true
TRUST_PROXY=true
```

### 3.3 Set Directory Permissions

```bash
sudo chown -R www-data:www-data /var/www/pearl
sudo chmod -R 755 /var/www/pearl
sudo chmod -R 775 /var/www/pearl/storage
```

### 3.4 Execute Automated Deployment Runner

Run PEARL's automated deployment runner:

```bash
sudo -u www-data php bin/pearl deploy
```

This command automatically:
1. Validates PHP 8.3+ and all required extensions (`pdo`, `json`, `mbstring`, `openssl`).
2. Checks storage directory writable permissions.
3. Tests database connectivity and executes pending migrations.
4. Verifies pre-compiled Vite production assets in `public/build/`.
5. Clears stale application caches.

---

## 4. Web Server Configuration

### 4.1 Nginx Setup (Recommended)

Copy the pre-configured Nginx template:

```bash
sudo cp deploy/nginx/pearl.conf /etc/nginx/sites-available/pearl.conf
sudo nano /etc/nginx/sites-available/pearl.conf # (update server_name)
sudo ln -s /etc/nginx/sites-available/pearl.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

Obtain free SSL certificate via Let's Encrypt:

```bash
sudo certbot --nginx -d example.com -d www.example.com
```

### 4.2 Apache VirtualHost Setup

```bash
sudo cp deploy/apache/pearl.conf /etc/apache2/sites-available/pearl.conf
sudo a2enmod rewrite headers ssl proxy_fcgi
sudo a2ensite pearl.conf
sudo systemctl reload apache2
```

### 4.3 Caddy Setup

```bash
sudo cp deploy/caddy/Caddyfile /etc/caddy/Caddyfile
sudo systemctl reload caddy
```

---

## 5. Background Queue Workers

Queue workers handle asynchronous jobs (emails, webhooks, heavy calculations) without slowing down HTTP responses.

### 5.1 Systemd Worker Daemon (Recommended)

Install the systemd unit template:

```bash
sudo cp deploy/systemd/pearl-worker.service /etc/systemd/system/pearl-worker@.service
sudo systemctl daemon-reload

# Start 2 worker processes
sudo systemctl enable --now pearl-worker@1.service pearl-worker@2.service

# Check worker status
sudo systemctl status pearl-worker@1.service
```

### 5.2 Supervisor Alternative

```bash
sudo cp deploy/supervisor/pearl-worker.conf /etc/supervisor/conf.d/pearl-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

---

## 6. Scheduled Maintenance (Cron)

Set up a minimal crontab entry for session pruning and log rotation:

```bash
sudo crontab -u www-data -e
```

Add:
```cron
# Prune expired sessions every hour
0 * * * * /usr/bin/php /var/www/pearl/bin/pearl session:clear > /dev/null 2>&1
```

---

## 7. Packagist & Git Distribution Workflow

### 7.1 Git Repository Setup

Initialize git and create the initial release tag:

```bash
git init
git add .
git commit -m "feat: release pearl/framework v1.0.0"
git tag -a v1.0.0 -m "Release v1.0.0"
git remote add origin git@github.com:bluxtechnologies/pearl.git
git push origin main --tags
```

### 7.2 Submit to Packagist

1. Log in to [Packagist.org](https://packagist.org/).
2. Click **Submit** and enter the repository URL: `https://github.com/bluxtechnologies/pearl`.
3. Configure the GitHub Webhook under repository Settings $\rightarrow$ Webhooks to auto-update Packagist on git push.
4. Users can now install Pearl via Composer:
   ```bash
   composer create-project pearl/framework my-pearl-app
   ```

---

## 8. Standalone ZIP Distribution Packaging

To generate an offline, air-gapped, or manual deployment ZIP archive (per `PEARL_ARCHITECTURE.md` §26):

```bash
php bin/pearl package --output=dist/pearl-v1.0.0.zip
```

This bundles:
- Full engine (`pearl/`), CLI tool (`bin/pearl`), configuration files (`config/`).
- Wire domain handlers (`wire/`), database migrations (`database/`).
- Compiled frontend assets (`public/build/`).
- Documentation, license, and deployment configs.
- Automatically excludes `.git`, `.env`, `node_modules`, `vendor`, and temporary caches.

To verify package integrity:
```bash
sha256sum dist/pearl-v1.0.0.zip
```
