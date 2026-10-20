# CI/CD Deployment Plan
## GitHub Actions Automated Deployment to Domainesia VPS

This document outlines the complete CI/CD setup for automatically deploying the Kanban Project Management system to Domainesia VPS whenever code is pushed to GitHub.

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Prerequisites](#prerequisites)
4. [Initial Setup](#initial-setup)
5. [GitHub Actions Workflow](#github-actions-workflow)
6. [Deployment Script](#deployment-script)
7. [Environment Configuration](#environment-configuration)
8. [Security Best Practices](#security-best-practices)
9. [Branch Strategy](#branch-strategy)
10. [Testing & Validation](#testing--validation)
11. [Rollback Procedure](#rollback-procedure)
12. [Monitoring & Notifications](#monitoring--notifications)
13. [Troubleshooting](#troubleshooting)

---

## Overview

### What is CI/CD?

**Continuous Integration (CI)**: Automatically test and validate code changes when pushed to the repository.

**Continuous Deployment (CD)**: Automatically deploy code to production server after successful validation.

### Benefits

✅ **Automated Deployment**: Push to GitHub → Automatic deployment  
✅ **Consistency**: Same deployment process every time  
✅ **Speed**: No manual SSH commands needed  
✅ **Safety**: Automated testing before deployment  
✅ **History**: Full deployment logs in GitHub Actions  
✅ **Team Collaboration**: Anyone can deploy by pushing code  

### Workflow

```
Developer pushes code to GitHub
         ↓
GitHub Actions workflow triggered
         ↓
Run tests (if configured)
         ↓
Connect to Domainesia VPS via SSH
         ↓
Pull latest code
         ↓
Install dependencies
         ↓
Build assets
         ↓
Run migrations
         ↓
Clear and cache configurations
         ↓
Restart services
         ↓
Deployment complete ✅
```

---

## Architecture

### Components

1. **GitHub Repository**: Source code repository
2. **GitHub Actions**: CI/CD automation platform
3. **Domainesia VPS**: Production server
4. **SSH Keys**: Secure authentication
5. **Deployment Script**: Automated deployment commands

### Data Flow

```
GitHub (Code) 
    ↓
GitHub Actions (Workflow)
    ↓
SSH Connection
    ↓
Domainesia VPS
    ↓
Application Directory
    ↓
Laravel Application
```

---

## Prerequisites

### Required Access

- [ ] GitHub repository with admin access
- [ ] Domainesia VPS with SSH access
- [ ] Root or sudo access on VPS
- [ ] Git installed on VPS
- [ ] Repository cloned on VPS

### Required Knowledge

- Basic understanding of Git
- Basic understanding of SSH
- Familiarity with Laravel deployment

---

## Initial Setup

### Step 1: Generate SSH Key Pair

On your **local machine**, generate SSH keys for GitHub Actions:

```bash
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/github_actions_deploy
```

This creates:
- Private key: `~/.ssh/github_actions_deploy` (keep secret!)
- Public key: `~/.ssh/github_actions_deploy.pub`

### Step 2: Add Public Key to Domainesia VPS

Copy public key to your VPS:

```bash
ssh-copy-id -i ~/.ssh/github_actions_deploy.pub username@your-vps-ip
```

Or manually:

```bash
# On your local machine, copy the public key
cat ~/.ssh/github_actions_deploy.pub

# On VPS, add to authorized_keys
ssh username@your-vps-ip
mkdir -p ~/.ssh
echo "your-public-key-here" >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
chmod 700 ~/.ssh
```

### Step 3: Test SSH Connection

```bash
ssh -i ~/.ssh/github_actions_deploy username@your-vps-ip
```

If successful, you can proceed.

### Step 4: Configure GitHub Secrets

1. Go to your GitHub repository
2. Navigate to **Settings** → **Secrets and variables** → **Actions**
3. Click **New repository secret**
4. Add the following secrets:

#### Required Secrets

| Secret Name | Description | Example |
|-------------|-------------|---------|
| `DEPLOY_HOST` | VPS IP address (use IP, not subdomain) | `123.456.789.0` |
| `DEPLOY_USER` | SSH username | `root` or `username` |
| `DEPLOY_SSH_KEY` | Private SSH key content | Content of `~/.ssh/github_actions_deploy` |
| `DEPLOY_PATH` | Application directory on server | `/var/www/homefinder-pm` |

**Note**: Use the VPS IP address for `DEPLOY_HOST`, not the subdomain. The subdomain (`your-homefinder-domain.example`) is configured in Nginx and used for web access, while SSH connections use the IP address.

#### How to Get Private Key Content

```bash
cat ~/.ssh/github_actions_deploy
```

Copy the entire output (including `-----BEGIN OPENSSH PRIVATE KEY-----` and `-----END OPENSSH PRIVATE KEY-----`)

### Step 5: Create GitHub Actions Workflow

Create the workflow file in your repository:

**File**: `.github/workflows/deploy.yml`

---

## GitHub Actions Workflow

### Basic Deployment Workflow

Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Domainesia Production

on:
  push:
    branches:
      - main  # Change to 'master' if that's your main branch

jobs:
  deploy:
    name: Deploy to Server
    runs-on: ubuntu-latest
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup SSH
        run: |
          mkdir -p ~/.ssh
          echo "${{ secrets.DEPLOY_SSH_KEY }}" > ~/.ssh/deploy_key
          chmod 600 ~/.ssh/deploy_key
          ssh-keyscan -H ${{ secrets.DEPLOY_HOST }} >> ~/.ssh/known_hosts

      - name: Deploy to production server
        env:
          DEPLOY_HOST: ${{ secrets.DEPLOY_HOST }}
          DEPLOY_USER: ${{ secrets.DEPLOY_USER }}
          DEPLOY_PATH: ${{ secrets.DEPLOY_PATH }}
        run: |
          ssh -i ~/.ssh/deploy_key -o StrictHostKeyChecking=no ${{ secrets.DEPLOY_USER }}@${{ secrets.DEPLOY_HOST }} << 'ENDSSH'
            set -e  # Exit on error
            
            cd ${{ secrets.DEPLOY_PATH }}
            
            echo "🚀 Starting deployment..."
            
            # Pull latest code
            echo "📥 Pulling latest code..."
            git pull origin main
            
            # Install Composer dependencies
            echo "📦 Installing Composer dependencies..."
            composer install --no-dev --optimize-autoloader --no-interaction
            
            # Install NPM dependencies
            echo "📦 Installing NPM dependencies..."
            npm ci
            
            # Build assets
            echo "🏗️ Building assets..."
            npm run build
            
            # Run database migrations
            echo "🗄️ Running database migrations..."
            php artisan migrate --force
            
            # Clear and cache configuration
            echo "⚙️ Optimizing configuration..."
            php artisan config:clear
            php artisan config:cache
            
            # Clear and cache routes
            echo "🛣️ Optimizing routes..."
            php artisan route:clear
            php artisan route:cache
            
            # Clear and cache views
            echo "👁️ Optimizing views..."
            php artisan view:clear
            php artisan view:cache
            
            # Optimize application
            echo "⚡ Optimizing application..."
            php artisan optimize
            
            # Clear application cache
            echo "🧹 Clearing application cache..."
            php artisan cache:clear
            
            # Set proper permissions
            echo "🔐 Setting permissions..."
            sudo chown -R www-data:www-data storage bootstrap/cache
            sudo chmod -R 775 storage bootstrap/cache
            
            # Restart queue workers (if using supervisor)
            echo "🔄 Restarting queue workers..."
            sudo supervisorctl restart laravel-worker:* || true
            
            # Reload PHP-FPM (adjust version as needed)
            echo "🔄 Reloading PHP-FPM..."
            sudo systemctl reload php8.2-fpm || true
            
            echo "✅ Deployment completed successfully!"
          ENDSSH

      - name: Cleanup
        if: always()
        run: |
          rm -f ~/.ssh/deploy_key
          rm -f ~/.ssh/known_hosts
```

### Advanced Workflow with Testing

```yaml
name: Deploy to Domainesia Production

on:
  push:
    branches:
      - main
  workflow_dispatch:  # Allow manual trigger

jobs:
  test:
    name: Run Tests
    runs-on: ubuntu-latest
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, xml, curl, zip, gd, bcmath, intl
          coverage: none

      - name: Install Composer dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Run tests
        run: php artisan test
        env:
          APP_ENV: testing

  deploy:
    name: Deploy to Server
    needs: test  # Only deploy if tests pass
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup SSH
        run: |
          mkdir -p ~/.ssh
          echo "${{ secrets.DEPLOY_SSH_KEY }}" > ~/.ssh/deploy_key
          chmod 600 ~/.ssh/deploy_key
          ssh-keyscan -H ${{ secrets.DEPLOY_HOST }} >> ~/.ssh/known_hosts

      - name: Deploy to production server
        env:
          DEPLOY_HOST: ${{ secrets.DEPLOY_HOST }}
          DEPLOY_USER: ${{ secrets.DEPLOY_USER }}
          DEPLOY_PATH: ${{ secrets.DEPLOY_PATH }}
        run: |
          ssh -i ~/.ssh/deploy_key -o StrictHostKeyChecking=no ${{ secrets.DEPLOY_USER }}@${{ secrets.DEPLOY_HOST }} << 'ENDSSH'
            set -e
            
            cd ${{ secrets.DEPLOY_PATH }}
            
            echo "🚀 Starting deployment..."
            echo "📅 Deployment time: $(date)"
            
            # Pull latest code
            echo "📥 Pulling latest code..."
            git fetch origin
            git reset --hard origin/main
            
            # Install Composer dependencies
            echo "📦 Installing Composer dependencies..."
            composer install --no-dev --optimize-autoloader --no-interaction
            
            # Install NPM dependencies
            echo "📦 Installing NPM dependencies..."
            npm ci --production=false
            
            # Build assets
            echo "🏗️ Building assets..."
            npm run build
            
            # Run database migrations
            echo "🗄️ Running database migrations..."
            php artisan migrate --force
            
            # Clear and cache configuration
            echo "⚙️ Optimizing configuration..."
            php artisan config:clear
            php artisan config:cache
            
            # Clear and cache routes
            echo "🛣️ Optimizing routes..."
            php artisan route:clear
            php artisan route:cache
            
            # Clear and cache views
            echo "👁️ Optimizing views..."
            php artisan view:clear
            php artisan view:cache
            
            # Optimize application
            echo "⚡ Optimizing application..."
            php artisan optimize
            
            # Clear application cache
            echo "🧹 Clearing application cache..."
            php artisan cache:clear
            
            # Set proper permissions
            echo "🔐 Setting permissions..."
            sudo chown -R www-data:www-data storage bootstrap/cache
            sudo chmod -R 775 storage bootstrap/cache
            
            # Restart queue workers
            echo "🔄 Restarting queue workers..."
            sudo supervisorctl restart laravel-worker:* || echo "⚠️ Supervisor not running"
            
            # Reload PHP-FPM
            echo "🔄 Reloading PHP-FPM..."
            sudo systemctl reload php8.2-fpm || echo "⚠️ PHP-FPM reload failed"
            
            echo "✅ Deployment completed successfully!"
            echo "📅 Completed at: $(date)"
          ENDSSH

      - name: Cleanup
        if: always()
        run: |
          rm -f ~/.ssh/deploy_key
          rm -f ~/.ssh/known_hosts
```

---

## Deployment Script

### Alternative: Server-Side Deployment Script

Instead of inline commands, you can use a deployment script on the server.

Create `deploy.sh` on your VPS:

```bash
#!/bin/bash
set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
PROJECT_PATH="/var/www/homefinder-pm"
BRANCH="main"

cd $PROJECT_PATH

echo -e "${GREEN}🚀 Starting deployment...${NC}"
echo -e "${YELLOW}📅 Deployment time: $(date)${NC}"

# Pull latest code
echo -e "${GREEN}📥 Pulling latest code from $BRANCH...${NC}"
git fetch origin
git reset --hard origin/$BRANCH

# Install Composer dependencies
echo -e "${GREEN}📦 Installing Composer dependencies...${NC}"
composer install --no-dev --optimize-autoloader --no-interaction

# Install NPM dependencies
echo -e "${GREEN}📦 Installing NPM dependencies...${NC}"
npm ci --production=false

# Build assets
echo -e "${GREEN}🏗️ Building assets...${NC}"
npm run build

# Run database migrations
echo -e "${GREEN}🗄️ Running database migrations...${NC}"
php artisan migrate --force

# Clear and cache configuration
echo -e "${GREEN}⚙️ Optimizing configuration...${NC}"
php artisan config:clear
php artisan config:cache

# Clear and cache routes
echo -e "${GREEN}🛣️ Optimizing routes...${NC}"
php artisan route:clear
php artisan route:cache

# Clear and cache views
echo -e "${GREEN}👁️ Optimizing views...${NC}"
php artisan view:clear
php artisan view:cache

# Optimize application
echo -e "${GREEN}⚡ Optimizing application...${NC}"
php artisan optimize

# Clear application cache
echo -e "${GREEN}🧹 Clearing application cache...${NC}"
php artisan cache:clear

# Set proper permissions
echo -e "${GREEN}🔐 Setting permissions...${NC}"
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Restart queue workers
echo -e "${GREEN}🔄 Restarting queue workers...${NC}"
sudo supervisorctl restart laravel-worker:* || echo -e "${YELLOW}⚠️ Supervisor not running${NC}"

# Reload PHP-FPM
echo -e "${GREEN}🔄 Reloading PHP-FPM...${NC}"
sudo systemctl reload php8.2-fpm || echo -e "${YELLOW}⚠️ PHP-FPM reload failed${NC}"

echo -e "${GREEN}✅ Deployment completed successfully!${NC}"
echo -e "${YELLOW}📅 Completed at: $(date)${NC}"
```

Make it executable:

```bash
chmod +x /var/www/homefinder-pm/deploy.sh
```

Then update GitHub Actions workflow to use the script:

```yaml
- name: Deploy to production server
  run: |
    ssh -i ~/.ssh/deploy_key ${{ secrets.DEPLOY_USER }}@${{ secrets.DEPLOY_HOST }} \
      "cd ${{ secrets.DEPLOY_PATH }} && ./deploy.sh"
```

---

## Environment Configuration

### Important: Never Commit `.env`

The `.env` file should **never** be committed to Git. It must exist on the server separately.

### Server Environment Setup

1. Create `.env` file on server (first deployment only):

```bash
cd /var/www/homefinder-pm
cp .env.example .env
nano .env
```

2. Configure all required variables (see `HOSTING_DEPLOYMENT.md`)

3. Generate application key:

```bash
php artisan key:generate
```

### Environment Variables Checklist

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL=https://your-homefinder-domain.example` (production subdomain)
- [ ] Database credentials
- [ ] Google OAuth credentials (with redirect URI: `https://your-homefinder-domain.example/auth/google/callback`)
- [ ] Mail configuration
- [ ] Queue connection

---

## Security Best Practices

### 1. SSH Key Security

- ✅ Never commit private keys to Git
- ✅ Use GitHub Secrets for sensitive data
- ✅ Rotate SSH keys periodically
- ✅ Use dedicated deploy user (not root)

### 2. Create Deploy User (Recommended)

Instead of using root, create a dedicated user:

```bash
# On VPS
sudo adduser deployer
sudo usermod -aG www-data deployer
sudo usermod -aG sudo deployer

# Add SSH key for deployer
sudo mkdir -p /home/deployer/.ssh
sudo cp ~/.ssh/authorized_keys /home/deployer/.ssh/
sudo chown -R deployer:deployer /home/deployer/.ssh
sudo chmod 700 /home/deployer/.ssh
sudo chmod 600 /home/deployer/.ssh/authorized_keys

# Configure sudo for specific commands only
sudo visudo
```

Add:

```
deployer ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl restart laravel-worker:*
deployer ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.2-fpm
deployer ALL=(ALL) NOPASSWD: /bin/chown -R www-data:www-data /var/www/homefinder-pm/*
deployer ALL=(ALL) NOPASSWD: /bin/chmod -R 775 /var/www/homefinder-pm/storage
```

### 3. Repository Security

- ✅ Use private repositories for production code
- ✅ Limit access to repository
- ✅ Use branch protection rules
- ✅ Require pull request reviews

### 4. Branch Protection

1. Go to repository **Settings** → **Branches**
2. Add rule for `main` branch:
   - ✅ Require pull request reviews
   - ✅ Require status checks to pass
   - ✅ Require branches to be up to date
   - ✅ Do not allow force pushes

---

## Branch Strategy

### Recommended Strategy

```
main (production)
  ↑
develop (staging)
  ↑
feature/feature-name (development)
```

### Workflow

1. **Development**: Work on `feature/feature-name` branches
2. **Staging**: Merge to `develop` for testing
3. **Production**: Merge to `main` for deployment

### GitHub Actions for Multiple Environments

```yaml
on:
  push:
    branches:
      - main      # Production
      - develop   # Staging
```

Then use conditions:

```yaml
- name: Deploy to production
  if: github.ref == 'refs/heads/main'
  run: |
    # Production deployment commands

- name: Deploy to staging
  if: github.ref == 'refs/heads/develop'
  run: |
    # Staging deployment commands
```

---

## Testing & Validation

### Pre-Deployment Checks

1. **Run Tests Locally:**
   ```bash
   php artisan test
   ```

2. **Check Code Style:**
   ```bash
   ./vendor/bin/pint --test
   ```

3. **Validate Environment:**
   ```bash
   php artisan config:validate
   ```

### Post-Deployment Validation

1. **Check Application Status:**
   ```bash
   curl -I https://your-homefinder-domain.example
   ```
   
   Should return `200 OK` or `302 Found` (redirect to login). Verify the response includes SSL certificate details.

2. **Verify Database:**
   ```bash
   php artisan tinker
   >>> DB::connection()->getPdo();
   ```

3. **Check Logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

---

## Rollback Procedure

### Quick Rollback via Git

If deployment fails, rollback to previous commit:

```bash
# On VPS
cd /var/www/homefinder-pm
git log --oneline -10  # Find previous commit
git reset --hard <previous-commit-hash>
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart laravel-worker:*
```

### Rollback via GitHub Actions

Create rollback workflow (`.github/workflows/rollback.yml`):

```yaml
name: Rollback Production

on:
  workflow_dispatch:
    inputs:
      commit_hash:
        description: 'Commit hash to rollback to'
        required: true

jobs:
  rollback:
    runs-on: ubuntu-latest
    steps:
      - name: Setup SSH
        run: |
          mkdir -p ~/.ssh
          echo "${{ secrets.DEPLOY_SSH_KEY }}" > ~/.ssh/deploy_key
          chmod 600 ~/.ssh/deploy_key
          ssh-keyscan -H ${{ secrets.DEPLOY_HOST }} >> ~/.ssh/known_hosts

      - name: Rollback
        run: |
          ssh -i ~/.ssh/deploy_key ${{ secrets.DEPLOY_USER }}@${{ secrets.DEPLOY_HOST }} << ENDSSH
            cd ${{ secrets.DEPLOY_PATH }}
            git reset --hard ${{ github.event.inputs.commit_hash }}
            composer install --no-dev --optimize-autoloader --no-interaction
            npm ci
            npm run build
            php artisan migrate:rollback --step=1 || true
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            php artisan optimize
            sudo supervisorctl restart laravel-worker:*
          ENDSSH
```

---

## Monitoring & Notifications

### GitHub Actions Notifications

Add notification step to workflow:

```yaml
- name: Notify on success
  if: success()
  run: |
    echo "✅ Deployment successful!"
    # Add Slack/Discord/Email notification here

- name: Notify on failure
  if: failure()
  run: |
    echo "❌ Deployment failed!"
    # Add error notification here
```

### Slack Notification Example

```yaml
- name: Notify Slack
  if: always()
  uses: 8398a7/action-slack@v3
  with:
    status: ${{ job.status }}
    text: 'Deployment to production ${{ job.status }}'
    webhook_url: ${{ secrets.SLACK_WEBHOOK }}
```

---

## Troubleshooting

### Common Issues

#### 1. SSH Connection Failed

**Error**: `Permission denied (publickey)`

**Solution**:
- Verify SSH key in GitHub Secrets
- Check key format (must include `-----BEGIN` and `-----END`)
- Test SSH connection manually
- Verify `authorized_keys` on server

#### 2. Git Pull Failed

**Error**: `fatal: not a git repository`

**Solution**:
- Verify `DEPLOY_PATH` is correct
- Ensure directory contains `.git` folder
- Check Git user permissions

#### 3. Permission Denied Errors

**Error**: `chown: changing ownership of 'storage': Operation not permitted`

**Solution**:
- Use `sudo` for chown/chmod commands
- Verify deploy user has sudo permissions
- Check file ownership

#### 4. Migration Failed

**Error**: `Migration failed`

**Solution**:
- Check database credentials in `.env`
- Verify database user has proper permissions
- Review migration files for errors
- Consider using `--force` flag (use with caution)

#### 5. Assets Not Building

**Error**: `npm: command not found`

**Solution**:
- Install Node.js on server
- Verify Node.js version (18.x recommended)
- Check `PATH` environment variable

### Debugging Tips

1. **Enable Verbose Output:**
   ```yaml
   run: |
     set -x  # Enable debug output
     # Your commands here
   ```

2. **Check Workflow Logs:**
   - Go to GitHub → Actions tab
   - Click on failed workflow
   - Review step logs

3. **Test Commands Locally:**
   - SSH into server manually
   - Run deployment commands step by step
   - Identify failing step

---

## Checklist

### Initial Setup

- [ ] SSH key pair generated
- [ ] Public key added to VPS
- [ ] Private key added to GitHub Secrets
- [ ] All GitHub Secrets configured
- [ ] Workflow file created
- [ ] Tested SSH connection
- [ ] Repository cloned on server
- [ ] `.env` file configured on server

### First Deployment

- [ ] Pushed code to `main` branch
- [ ] Workflow triggered successfully
- [ ] Deployment completed without errors
- [ ] Application accessible via browser
- [ ] Database migrations ran successfully
- [ ] Queue workers running
- [ ] Logs show no errors

### Ongoing Maintenance

- [ ] Regular code pushes trigger deployments
- [ ] Monitor deployment logs
- [ ] Keep dependencies updated
- [ ] Regular backups scheduled
- [ ] Monitor server resources

---

## Additional Resources

- **GitHub Actions Documentation**: https://docs.github.com/en/actions
- **Laravel Deployment Guide**: https://laravel.com/docs/deployment
- **SSH Key Management**: https://docs.github.com/en/authentication/connecting-to-github-with-ssh
- **Domainesia Documentation**: https://www.domainesia.com/panduan/

---

## Quick Reference: Subdomain Deployment

### GitHub Secrets Configuration

| Secret Name | Value Type | Example |
|-------------|------------|---------|
| `DEPLOY_HOST` | VPS IP address | `123.456.789.0` |
| `DEPLOY_USER` | SSH username | `root` or `deployer` |
| `DEPLOY_SSH_KEY` | Private SSH key | Content of `~/.ssh/github_actions_deploy` |
| `DEPLOY_PATH` | Application path | `/var/www/homefinder-pm` |

### Important Notes

- **Use VPS IP for SSH**: `DEPLOY_HOST` should be the VPS IP address, not the subdomain
- **Subdomain for Web**: The subdomain (`your-homefinder-domain.example`) is configured in Nginx for web access
- **APP_URL**: Must be set to `https://your-homefinder-domain.example` in `.env` file on server
- **Google OAuth**: Redirect URI must be `https://your-homefinder-domain.example/auth/google/callback`

---

**Last Updated**: [Current Date]  
**Version**: 1.1 (Updated for subdomain deployment)


