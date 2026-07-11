# Hosting Recommendation & Deployment Guide
## Domainesia VPS for Kanban Project Management System

This guide provides hosting recommendations and step-by-step deployment instructions for deploying the Kanban-based Agile project management system to Domainesia VPS hosting.

### Deployment Target

**Subdomain**: `pm.partnershub.co`  
**Main Domain**: `partnershub.co` (already registered in Domainesia)  
**Deployment Type**: Subdomain-based deployment

> **Important**: This guide is configured for subdomain deployment. The application will be accessible at `https://pm.partnershub.co` after deployment.

---

## Table of Contents

1. [Hosting Plan Recommendation](#hosting-plan-recommendation)
2. [System Requirements](#system-requirements)
3. [Pre-Deployment Checklist](#pre-deployment-checklist)
4. [Domainesia VPS Setup](#domainesia-vps-setup)
5. [Server Configuration](#server-configuration)
6. [Application Deployment](#application-deployment)
7. [Post-Deployment Configuration](#post-deployment-configuration)
8. [Performance Optimization](#performance-optimization)
9. [Monitoring & Maintenance](#monitoring--maintenance)
10. [Troubleshooting](#troubleshooting)

---

## Hosting Plan Recommendation

### Recommended Plan: **Essential VPS**

**Specifications:**
- **CPU**: 2 vCore
- **RAM**: 4 GB
- **Storage**: 80 GB SSD NVMe
- **Bandwidth**: 250 Mbps
- **Price**: ~$34/month (~$408/year)

### Why Essential VPS?

✅ **Perfect for 50 Users:**
- Sufficient CPU and RAM for concurrent Kanban operations
- Handles file uploads, real-time updates, and database queries
- No resource contention issues

✅ **Cost-Effective:**
- Better value than shared hosting for application performance
- Dedicated resources ensure consistent performance
- Room for growth without immediate upgrade

✅ **Scalability:**
- Can upgrade to Comfort VPS (4 vCore, 8 GB RAM) if needed
- Easy to add more resources as user base grows

### Alternative Plans

| Plan | CPU | RAM | Storage | Price | Use Case |
|------|-----|-----|---------|-------|----------|
| **Value VPS** | 1 vCore | 2 GB | 40 GB | ~$16/mo | Budget option, may need optimization |
| **Essential VPS** ⭐ | 2 vCore | 4 GB | 80 GB | ~$34/mo | **Recommended** |
| **Comfort VPS** | 4 vCore | 8 GB | 160 GB | ~$68/mo | Overkill for 50 users |

### Why Not Shared Hosting?

❌ Limited CPU/RAM resources  
❌ No root access for optimization  
❌ Database connection limits  
❌ Slower performance due to shared resources  
❌ Limited control over PHP configuration  

---

## System Requirements

### Minimum Requirements

- **PHP**: 8.2 or higher
- **MySQL**: 8.0 or higher (or PostgreSQL 12+)
- **Composer**: Latest version
- **Node.js**: 18.x or higher (for asset compilation)
- **Nginx/Apache**: Web server
- **SSL Certificate**: Required (for Google OAuth)

### PHP Extensions Required

```bash
php-fpm
php-mysql
php-mbstring
php-xml
php-curl
php-zip
php-gd
php-bcmath
php-intl
php-opcache
```

### Recommended Software

- **Redis** or **Memcached**: For caching (optional but recommended)
- **Supervisor**: For queue workers
- **Git**: For version control and deployment

---

## Pre-Deployment Checklist

### Before Deployment

- [ ] Domain registered (`partnershub.co`) in Domainesia
- [ ] Subdomain DNS A record created (`pm.partnershub.co` → VPS IP)
- [ ] Domainesia VPS account created and activated
- [ ] SSH access to VPS configured
- [ ] Subdomain pointing to VPS IP address
- [ ] DNS propagation verified (can take 5-60 minutes)
- [ ] SSL certificate obtained (Let's Encrypt recommended)
- [ ] Google OAuth credentials ready for production
- [ ] Database credentials prepared
- [ ] Backup strategy planned

### Environment Variables to Prepare

- [ ] `APP_URL=https://pm.partnershub.co` (production subdomain)
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] Database credentials
- [ ] Google OAuth Client ID and Secret (with redirect URI: `https://pm.partnershub.co/auth/google/callback`)
- [ ] Mail configuration (SMTP)
- [ ] Queue connection settings

---

## Domainesia VPS Setup

### Step 0: Configure DNS for Subdomain

Before accessing your VPS, configure the DNS for your subdomain:

1. Log in to Domainesia client area
2. Navigate to **Domain Management** → Select `partnershub.co`
3. Go to **DNS Management** or **DNS Zone Editor**
4. Add a new **A Record**:
   - **Type**: `A`
   - **Name**: `pm` (or `pm.partnershub.co` depending on your DNS panel)
   - **Value/Target**: Your VPS IP address
   - **TTL**: `3600` (1 hour) or default
5. Save the DNS record
6. Wait for DNS propagation (usually 5-60 minutes, can take up to 24 hours)
7. Verify DNS is working:
   ```bash
   ping pm.partnershub.co
   # Should return your VPS IP address
   ```
   Or use:
   ```bash
   nslookup pm.partnershub.co
   ```

### Step 1: Access Your VPS

1. Log in to Domainesia client area
2. Navigate to VPS management
3. Note your VPS IP address and root password
4. Access via SSH:

```bash
ssh root@your-vps-ip
```

### Step 2: Update System

```bash
sudo apt-get update
sudo apt-get upgrade -y
```

### Step 3: Install Required Software

#### Install PHP 8.2 and Extensions

```bash
sudo apt-get install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt-get update

sudo apt-get install -y \
    php8.2-fpm \
    php8.2-mysql \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-curl \
    php8.2-zip \
    php8.2-gd \
    php8.2-bcmath \
    php8.2-intl \
    php8.2-opcache
```

#### Install MySQL

```bash
sudo apt-get install -y mysql-server
sudo mysql_secure_installation
```

#### Install Composer

```bash
cd ~
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

#### Install Node.js and NPM

```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs
```

#### Install Nginx

```bash
sudo apt-get install -y nginx
```

#### Install Git

```bash
sudo apt-get install -y git
```

#### Install Supervisor (for queue workers)

```bash
sudo apt-get install -y supervisor
```

---

## Server Configuration

### Step 1: Configure PHP-FPM

Edit PHP-FPM configuration:

```bash
sudo nano /etc/php/8.2/fpm/php.ini
```

Update these settings:

```ini
memory_limit = 256M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 300
max_input_time = 300
```

Edit PHP-FPM pool configuration:

```bash
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

Update:

```ini
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
```

Restart PHP-FPM:

```bash
sudo systemctl restart php8.2-fpm
```

### Step 2: Configure MySQL Database

1. Create database and user:

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE partnershub_pm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pm_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON partnershub_pm.* TO 'pm_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Step 3: Configure Nginx

Create Nginx configuration:

```bash
sudo nano /etc/nginx/sites-available/partnershub-pm
```

Add configuration:

```nginx
server {
    listen 80;
    server_name pm.partnershub.co;
    root /var/www/partnershub-pm/public;

    # App upload rules allow up to 10MB (overtime evidence); without this nginx's 1MB default 413s uploads.
    client_max_body_size 12m;

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

    # Deny access to .env file
    location ~ /\.env {
        deny all;
        return 404;
    }
}
```

Enable site:

```bash
sudo ln -s /etc/nginx/sites-available/partnershub-pm /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Step 4: Setup SSL Certificate (Let's Encrypt)

Install Certbot:

```bash
sudo apt-get install -y certbot python3-certbot-nginx
```

Obtain certificate:

```bash
sudo certbot --nginx -d pm.partnershub.co
```

**Note**: Since you're using a subdomain, you only need to specify the subdomain. The main domain (`partnershub.co`) can have its own SSL certificate if needed for other services.

Auto-renewal is set up automatically.

---

## Application Deployment

### Step 1: Create Application Directory

```bash
sudo mkdir -p /var/www/partnershub-pm
sudo chown -R $USER:$USER /var/www/partnershub-pm
```

### Step 2: Clone Repository

```bash
cd /var/www/partnershub-pm
git clone https://github.com/your-username/your-repo.git .
```

### Step 3: Install Dependencies

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

### Step 4: Configure Environment

```bash
cp .env.example .env
nano .env
```

Update `.env` file:

```env
APP_NAME="PartnersHub PM"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://pm.partnershub.co

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=partnershub_pm
DB_USERNAME=pm_user
DB_PASSWORD=your_password_here

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=file
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"

GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false
```

Generate application key:

```bash
php artisan key:generate
```

### Step 5: Run Migrations

```bash
php artisan migrate --force
```

### Step 6: Setup Storage Link

```bash
php artisan storage:link
```

### Step 7: Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/partnershub-pm
sudo chmod -R 775 /var/www/partnershub-pm/storage
sudo chmod -R 775 /var/www/partnershub-pm/bootstrap/cache
```

### Step 8: Optimize Application

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

---

## Post-Deployment Configuration

### Step 1: Setup Queue Workers

Create Supervisor configuration:

```bash
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
```

Add:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/partnershub-pm/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/partnershub-pm/storage/logs/worker.log
stopwaitsecs=3600
```

Start Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

### Step 2: Setup Cron Jobs

Edit crontab:

```bash
sudo crontab -e -u www-data
```

Add:

```cron
* * * * * cd /var/www/partnershub-pm && php artisan schedule:run >> /dev/null 2>&1
```

### Step 3: Configure Google OAuth

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Navigate to **APIs & Services** → **Credentials**
3. Edit your OAuth 2.0 Client ID
4. Add to **Authorized redirect URIs**:
   ```
   https://pm.partnershub.co/auth/google/callback
   ```
5. Save changes
6. Update `.env` with production credentials:
   - `GOOGLE_CLIENT_ID`: Your Google OAuth Client ID
   - `GOOGLE_CLIENT_SECRET`: Your Google OAuth Client Secret

### Step 4: Create Initial Admin User

```bash
php artisan make:filament-user
```

Follow prompts to create admin user.

### Step 5: Setup Roles and Permissions

```bash
php artisan shield:setup
php artisan shield:install
php artisan shield:super-admin
```

Or run the RoleSeeder:

```bash
php artisan db:seed --class=RoleSeeder
```

---

## Performance Optimization

### 1. Enable OPcache

Edit PHP configuration:

```bash
sudo nano /etc/php/8.2/fpm/php.ini
```

Enable OPcache:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
```

### 2. Configure Redis (Optional but Recommended)

Install Redis:

```bash
sudo apt-get install -y redis-server
```

Update `.env`:

```env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

### 3. Enable Gzip Compression

Edit Nginx configuration:

```nginx
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss application/json;
```

### 4. Database Optimization

Add indexes to frequently queried columns. Run migrations after adding indexes.

---

## Monitoring & Maintenance

### Regular Tasks

1. **Check Logs:**
   ```bash
   tail -f /var/www/partnershub-pm/storage/logs/laravel.log
   tail -f /var/www/partnershub-pm/storage/logs/worker.log
   ```

2. **Monitor Disk Space:**
   ```bash
   df -h
   ```

3. **Check Queue Status:**
   ```bash
   sudo supervisorctl status laravel-worker:*
   ```

4. **Database Backups:**
   ```bash
   mysqldump -u pm_user -p partnershub_pm > backup_$(date +%Y%m%d).sql
   ```

### Automated Backups

Create backup script:

```bash
sudo nano /usr/local/bin/backup-pm.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/partnershub-pm"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u pm_user -p'your_password' partnershub_pm > $BACKUP_DIR/db_$DATE.sql

# Files backup
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/partnershub-pm

# Keep only last 7 days
find $BACKUP_DIR -type f -mtime +7 -delete
```

Make executable and add to crontab:

```bash
sudo chmod +x /usr/local/bin/backup-pm.sh
sudo crontab -e
```

Add (daily at 2 AM):

```cron
0 2 * * * /usr/local/bin/backup-pm.sh
```

---

## Troubleshooting

### Common Issues

#### 1. 500 Internal Server Error

Check logs:
```bash
tail -f /var/www/partnershub-pm/storage/logs/laravel.log
tail -f /var/log/nginx/error.log
```

Common fixes:
- Check file permissions
- Verify `.env` configuration
- Clear cache: `php artisan cache:clear`

#### 2. Database Connection Error

Verify database credentials in `.env` and test connection:
```bash
php artisan tinker
>>> DB::connection()->getPdo();
```

#### 3. Queue Not Processing

Check Supervisor status:
```bash
sudo supervisorctl status
sudo supervisorctl restart laravel-worker:*
```

#### 4. Permission Denied Errors

Fix permissions:
```bash
sudo chown -R www-data:www-data /var/www/partnershub-pm
sudo chmod -R 775 storage bootstrap/cache
```

#### 5. SSL Certificate Issues

Renew certificate:
```bash
sudo certbot renew
sudo systemctl reload nginx
```

#### 6. Subdomain DNS Not Resolving

**Error**: `pm.partnershub.co` not accessible or not resolving to VPS IP

**Solution**:
1. Verify DNS record exists:
   ```bash
   nslookup pm.partnershub.co
   # Should return your VPS IP address
   ```
2. Check DNS record in Domainesia:
   - Log in to Domainesia client area
   - Navigate to DNS Management for `partnershub.co`
   - Verify A record exists: `pm` → VPS IP
3. Wait for DNS propagation (can take 5-60 minutes, up to 24 hours)
4. Clear local DNS cache:
   ```bash
   # macOS/Linux
   sudo dscacheutil -flushcache; sudo killall -HUP mDNSResponder
   
   # Windows
   ipconfig /flushdns
   ```
5. Test from different network/location
6. Verify Nginx configuration:
   ```bash
   sudo nginx -t
   sudo systemctl status nginx
   ```

#### 7. Google OAuth Redirect URI Mismatch

**Error**: `redirect_uri_mismatch` when logging in with Google

**Solution**:
1. Verify redirect URI in Google Cloud Console:
   - Must be exactly: `https://pm.partnershub.co/auth/google/callback`
   - Check for typos (no trailing slash, correct protocol)
2. Verify `.env` file has correct `APP_URL`:
   ```env
   APP_URL=https://pm.partnershub.co
   ```
3. Clear application cache:
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```
4. Wait a few minutes for Google OAuth changes to propagate

---

## Security Checklist

- [ ] `APP_DEBUG=false` in production
- [ ] Strong database passwords
- [ ] Firewall configured (UFW)
- [ ] SSH key authentication only (disable password)
- [ ] Regular security updates
- [ ] SSL certificate installed and auto-renewing
- [ ] `.env` file not accessible via web
- [ ] File upload size limits configured
- [ ] Database backups automated
- [ ] Regular application updates

---

## Support & Resources

- **Domainesia Support**: 24/7 via live chat, email, or phone
- **Laravel Documentation**: https://laravel.com/docs
- **Filament Documentation**: https://filamentphp.com/docs
- **Nginx Documentation**: https://nginx.org/en/docs/

---

## Quick Reference: Subdomain Configuration

### Key Configuration Values

| Configuration | Value |
|---------------|-------|
| **Subdomain** | `pm.partnershub.co` |
| **Main Domain** | `partnershub.co` |
| **Nginx server_name** | `pm.partnershub.co` |
| **SSL Certificate** | `pm.partnershub.co` |
| **APP_URL** | `https://pm.partnershub.co` |
| **Google OAuth Redirect URI** | `https://pm.partnershub.co/auth/google/callback` |
| **Application Path** | `/var/www/partnershub-pm` |

### DNS Configuration

```
Type: A
Name: pm
Value: [Your VPS IP Address]
TTL: 3600
```

### Verification Commands

```bash
# Check DNS resolution
nslookup pm.partnershub.co
ping pm.partnershub.co

# Check application accessibility
curl -I https://pm.partnershub.co

# Check SSL certificate
openssl s_client -connect pm.partnershub.co:443 -servername pm.partnershub.co
```

---

**Last Updated**: [Current Date]  
**Version**: 1.1 (Updated for subdomain deployment)


