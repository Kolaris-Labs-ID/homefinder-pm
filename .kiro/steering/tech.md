# Technology Stack

## Core Framework

-   **Laravel 12** (PHP 8.2+)
-   **Filament 3.2** - Admin panel framework
-   **MySQL 8.0+ / PostgreSQL 12+** - Database

## Key Dependencies

**Backend**

-   `filament/filament` - Admin panel UI
-   `bezhansalleh/filament-shield` - Role & permission management
-   `spatie/laravel-permission` - Permission system
-   `laravel/sanctum` - API authentication
-   `laravel/socialite` - OAuth (Google login)
-   `laravel/octane` - High-performance server (FrankenPHP)
-   `maatwebsite/excel` - Excel import/export
-   `solution-forest/tab-layout-plugin` - Filament tabs

**Frontend**

-   Tailwind CSS 4.0
-   Vite 6.0
-   Alpine.js (via Filament)
-   Livewire (via Filament)

**Testing**

-   Pest PHP 3.7
-   Pest Laravel plugin

## Common Commands

### Development

```bash
# Start development environment (server + queue + logs + vite)
composer dev

# Or start individually
php artisan serve
php artisan queue:work
npm run dev

# With Octane (FrankenPHP)
composer octane-dev
```

### Setup

```bash
# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate
php artisan db:seed

# Storage
php artisan storage:link

# Create admin user
php artisan make:filament-user

# Setup permissions
php artisan shield:setup
php artisan shield:install
php artisan shield:super-admin
```

### Testing

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit
```

### Queue Management

```bash
# Development
php artisan queue:work

# Production
php artisan queue:work --daemon --tries=3 --timeout=60
```

### Code Quality

```bash
# Format code
./vendor/bin/pint

# View logs
php artisan pail
```

## Build & Deployment

### Assets

```bash
# Development
npm run dev

# Production build
npm run build
```

### Docker

-   `Dockerfile` and `docker-compose.yml` available
-   Nginx configuration in `nginx/conf.d/`
-   PHP configuration in `php/local.ini`
-   Supervisor config for queue workers in `laravel-worker.conf`

## API Authentication

-   **Sanctum tokens** for API access
-   Generate tokens via tinker:
    ```bash
    php artisan tinker
    >>> $user = App\Models\User::first();
    >>> $token = $user->createToken('mobile')->plainTextToken;
    ```
