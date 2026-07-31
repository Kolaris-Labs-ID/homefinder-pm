# Deploy: Browser Push Notifications (`feat/browser-push`)

Data-safety runbook for the `notifications` → `pm_notifications` rename and the
browser-push rollout. Every step is loud-fail: nothing here silently discards rows.

## Why this is safe

- `RENAME TABLE` is atomic in MySQL and carries all rows, indexes and the
  `user_id` FK with it. No other table has an FK into `notifications`.
- The rename migration is idempotent (`hasTable` guards) — an interrupted run
  can simply be re-run.
- `down()` archives the framework table (`notifications_rolled_back_<ts>`)
  instead of dropping it, so notifications written after deploy survive a rollback.
- The 2026_07_20 attendance migrations only ADD nullable columns / WIDEN enums —
  no destructive DDL anywhere in this deploy.

## Before resuming this branch (still WIP)

```bash
composer require emuniq/filament-browser-notifications:^1.2   # restores plugin + lock entries
# plugin self-registers on every panel via Panel::configureUsing — no provider edit needed
```

## Preflight (on prod, read-only)

```bash
php artisan migrate:status | grep -i pending      # know exactly what will run
mysql -e "SHOW TABLES LIKE 'pm_notifications'"    # must be empty
mysql -e "SELECT COUNT(*) FROM notifications"     # record row count for post-check
# drift check — all 10 evidence columns must be either ALL present or ALL absent:
mysql -e "SHOW COLUMNS FROM attendance_logs LIKE 'check_%'"
```

If `attendance_logs` is half-migrated (some `check_in_captured_at`-era columns
exist while `2026_07_20_000002` is still pending), STOP — repair drift first as
was done on dev (see memory/`.remember`), or the migrate run aborts mid-deploy.

## Deploy order (order matters — model `$table` and the rename must land together)

```bash
mysqldump --single-transaction partnershub > pre_deploy_$(date +%F_%H%M).sql   # 1. backup
php artisan down                                   # 2. close the code/schema window
git pull && composer install --no-dev              # 3. new code
php artisan migrate --force                        # 4. rename + create + push_subscriptions
php artisan browser-notifications:install          # 5. VAPID keys (prod's own; skips done steps)
php artisan config:cache && php artisan up         # 6.
supervisorctl restart laravel-worker:*             # 7. workers must load the new model
```

## Post-checks

```bash
mysql -e "SELECT COUNT(*) FROM pm_notifications"   # must equal the preflight count
php artisan browser-notifications:test <user-id> --sync
```

## Rollback

`php artisan migrate:rollback --step=1` renames the framework table aside
(`notifications_rolled_back_<timestamp>` — recover rows from there if needed)
and restores `pm_notifications` → `notifications`. Then redeploy the previous
code; the backup dump from step 1 is the last resort.
