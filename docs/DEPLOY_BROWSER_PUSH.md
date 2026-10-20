# Deploy: Browser Push Notifications

Data-safety runbook for the `notifications` → `pm_notifications` rename and the
browser-push rollout. Every step is loud-fail: nothing here silently discards rows.

The code landed on `main` (`b4b9ff9` → `f73a7ad` → `93f010b`). It has never been
deployed to production.

## Why this is safe

- `RENAME TABLE` is atomic in MySQL and carries all rows, indexes and the
  `user_id` FK with it. No other table has an FK into `notifications`.
- The rename migration is idempotent (`hasTable` guards) — an interrupted run
  can simply be re-run.
- `down()` archives the framework table (`notifications_rolled_back_<ts>`)
  instead of dropping it, so notifications written after deploy survive a rollback.
- The 2026_07_20 attendance migrations only ADD nullable columns / WIDEN enums —
  no destructive DDL anywhere in this deploy.

## Code prerequisites — all done

`b4b9ff9` carried a "WIP, do not deploy" warning. Every hazard it listed is fixed;
ignore that commit message and trust this section.

- Plugin present: `emuniq/filament-browser-notifications ^1.2` in `composer.json`,
  lock and vendor (`93f010b`). It self-registers on every panel via
  `Panel::configureUsing`, so its absence from `AdminPanelProvider` is correct —
  do not add it there.
- Rename migration is resumable, and `down()` archives instead of drops (`f73a7ad`).

Still coupled by design: `Notification::$table = 'pm_notifications'` only agrees
with the schema after the migration runs. The `artisan down` / `up` bracket in the
deploy order below is what closes that window — do not skip it.

## Operator prerequisites — not done

- **VAPID keys are unset.** `.env.example` declares `VAPID_PUBLIC_KEY`,
  `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` empty. Prod generates its own at step 5.
  Without them the rollout succeeds and delivers no push, silently.
- **`attendance_logs` drift is unverified on prod.** See the preflight below;
  a half-migrated table aborts the migrate run mid-deploy.

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
mysqldump --single-transaction homefinder > pre_deploy_$(date +%F_%H%M).sql   # 1. backup
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
