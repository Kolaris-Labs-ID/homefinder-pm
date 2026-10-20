# Homefinder PM

Homefinder PM is the Laravel API and Filament admin application for ESS, HRIS, payroll, and project management. It serves the Homefinder mobile app in the sibling `homefinder-app` repository.

## Requirements

- PHP 8.2 or newer with the extensions required by Laravel
- Composer
- Node.js and npm
- MySQL 8 or PostgreSQL 12+ (SQLite can be used for tests)

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure the database and external services in `.env`, then run:

```bash
php artisan migrate --seed
npm ci
npm run build
php artisan serve
```

The admin panel is at `http://127.0.0.1:8000/admin` and the API base is `http://127.0.0.1:8000/api`. Set `FRONTEND_URL` and `JOBS_PORTAL_URL` to the deployed frontend origins when configuring production CORS. Keep all credentials in `.env`; do not commit them.

## Build and checks

```bash
composer validate --no-check-publish
npm run build
php artisan test
```

The Pest suite uses an in-memory SQLite database via `phpunit.xml`. PDF, OAuth, file storage, email, and push features need their corresponding integrations configured to exercise against real services.

## Main modules

- **ESS / HRIS:** employee profile, attendance, leave, visits, expenses, overtime, payroll, and payslips.
- **Admin:** employee and payroll administration, approvals, and HR configuration through Filament.
- **Project management:** projects, epics, tickets, boards, comments, reporting, and client portal workflows.

Detailed setup and workflow notes are in [`docs/`](./docs/). Before deployment, replace the example hostnames in deployment docs with the domain assigned to Homefinder.
