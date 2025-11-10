# Supervisor Setup for Laravel Queue Workers

## Configuration File Created

✅ Configuration file created at: `/usr/local/etc/supervisor.d/laravel-worker.ini`

## Configuration Details

- **Program Name**: `laravel-worker`
- **Processes**: 8 worker processes
- **Project Path**: `/Users/arirudiana/Devs/partnershub-pm`
- **Log File**: `/Users/arirudiana/Devs/partnershub-pm/storage/logs/worker.log`
- **User**: `arirudiana` (your macOS user)

## Managing Supervisor on macOS

### Start Supervisor

```bash
# Start supervisord in the foreground (for testing)
supervisord -c /usr/local/etc/supervisord.conf

# Or start as a background daemon
supervisord -c /usr/local/etc/supervisord.conf -d
```

### Check Status

```bash
# Check supervisor status
supervisorctl status

# Check specific worker status
supervisorctl status laravel-worker:*
```

### Reload Configuration

After making changes to the configuration file:

```bash
# Reload configuration (keeps processes running)
supervisorctl reread
supervisorctl update

# Or restart supervisor
supervisorctl restart all
```

### Control Workers

```bash
# Start workers
supervisorctl start laravel-worker:*

# Stop workers
supervisorctl stop laravel-worker:*

# Restart workers
supervisorctl restart laravel-worker:*

# View logs
tail -f /Users/arirudiana/Devs/partnershub-pm/storage/logs/worker.log
```

### Stop Supervisor

```bash
# Stop all processes
supervisorctl stop all

# Shutdown supervisor
supervisorctl shutdown
```

## Auto-start on Boot (macOS)

To start supervisor automatically on system boot, you can create a LaunchDaemon:

### Option 1: Using brew services (Recommended)

```bash
brew services start supervisor
```

### Option 2: Manual LaunchDaemon

Create `/Library/LaunchDaemons/com.supervisor.plist`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>com.supervisor</string>
    <key>ProgramArguments</key>
    <array>
        <string>/usr/local/bin/supervisord</string>
        <string>-c</string>
        <string>/usr/local/etc/supervisord.conf</string>
        <string>-n</string>
    </array>
    <key>RunAtLoad</key>
    <true/>
    <key>KeepAlive</key>
    <true/>
    <key>StandardOutPath</key>
    <string>/usr/local/var/log/supervisord.log</string>
    <key>StandardErrorPath</key>
    <string>/usr/local/var/log/supervisord.log</string>
</dict>
</plist>
```

Then load it:
```bash
sudo launchctl load /Library/LaunchDaemons/com.supervisor.plist
```

## Verification

### Check if workers are running:

```bash
ps aux | grep "queue:work"
```

You should see 8 processes running.

### Check worker logs:

```bash
tail -f /Users/arirudiana/Devs/partnershub-pm/storage/logs/worker.log
```

### Test Queue Processing

```bash
# In Laravel tinker
php artisan tinker
>>> \App\Jobs\YourJob::dispatch();
```

## Troubleshooting

### If supervisor can't connect:

```bash
# Check if supervisord is running
ps aux | grep supervisord

# Check socket file
ls -la /usr/local/var/run/supervisor.sock

# Restart supervisord
supervisorctl shutdown
supervisord -c /usr/local/etc/supervisord.conf -d
```

### If workers fail to start:

1. Check the log file: `tail -f storage/logs/worker.log`
2. Verify PHP path: `which php`
3. Verify artisan file exists: `ls -la artisan`
4. Test command manually:
   ```bash
   php artisan queue:work --sleep=3 --tries=3 --max-time=3600
   ```

### Permission Issues:

If you see permission errors, ensure:
- Log directory is writable: `chmod -R 775 storage/logs`
- User has access to the project directory

## Configuration File Location

- Main config: `/usr/local/etc/supervisord.conf`
- Worker config: `/usr/local/etc/supervisor.d/laravel-worker.ini`

## Next Steps

1. Start supervisor: `supervisord -c /usr/local/etc/supervisord.conf -d`
2. Check status: `supervisorctl status`
3. Monitor logs: `tail -f storage/logs/worker.log`
4. Test queue processing by dispatching a job

