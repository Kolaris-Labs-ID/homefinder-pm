<?php

namespace App\Console\Commands;

use App\Models\Hris\AttendanceLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DiagnosePhotos extends Command
{
    protected $signature = 'attendance:diagnose-photos';
    protected $description = 'Diagnose attendance photo storage issues';

    public function handle(): int
    {
        $this->info('=== Attendance Photo Diagnostics ===');
        $this->newLine();

        // 1. Check GD library
        $this->info('1. GD Library:');
        if (extension_loaded('gd')) {
            $this->line('   ✅ GD is installed');
            $gdInfo = gd_info();
            $this->line('   JPEG: ' . ($gdInfo['JPEG Support'] ? '✅' : '❌'));
            $this->line('   PNG: ' . ($gdInfo['PNG Support'] ? '✅' : '❌'));
            $this->line('   WebP: ' . (($gdInfo['WebP Support'] ?? false) ? '✅' : '❌'));
        } else {
            $this->error('   ❌ GD is NOT installed! Photo overlay will FAIL.');
            $this->error('   Run: sudo apt install php-gd && sudo systemctl restart php*-fpm');
        }
        $this->newLine();

        // 2. Check Supabase config
        $this->info('2. Supabase Configuration:');
        $url = config('services.supabase.url');
        $key = config('services.supabase.key');
        $bucket = config('services.supabase.attendance_bucket');
        $this->line('   URL: ' . ($url ?: '❌ NOT SET'));
        $this->line('   Key: ' . ($key ? substr($key, 0, 20) . '...' : '❌ NOT SET'));
        $this->line('   Bucket: ' . ($bucket ?: '❌ NOT SET'));

        if ($url && $key) {
            $this->line('   Supabase configured: ✅');
            
            // Test connectivity
            try {
                $testUrl = "{$url}/storage/v1/bucket/{$bucket}";
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$key}",
                ])->get($testUrl);
                
                if ($response->successful()) {
                    $this->line('   Bucket access: ✅ OK');
                    $bucketData = $response->json();
                    $this->line('   Bucket public: ' . (($bucketData['public'] ?? false) ? '✅' : '❌'));
                } else {
                    $this->error('   Bucket access: ❌ FAILED - Status: ' . $response->status());
                    $this->error('   Response: ' . $response->body());
                }
            } catch (\Exception $e) {
                $this->error('   Connection test: ❌ ' . $e->getMessage());
            }
        } else {
            $this->warn('   Supabase NOT configured - using local storage');
        }
        $this->newLine();

        // 3. Check local storage
        $this->info('3. Local Storage:');
        $localPath = storage_path('app/private/attendance-photos');
        $this->line('   Path: ' . $localPath);
        $this->line('   Exists: ' . (is_dir($localPath) ? '✅' : '❌'));
        if (is_dir($localPath)) {
            $files = glob($localPath . '/*');
            $this->line('   Files: ' . count($files));
        }
        $this->newLine();

        // 4. Check recent attendance logs for photo paths
        $this->info('4. Recent Attendance Logs (last 10):');
        $logs = AttendanceLog::latest()->take(10)->get(['id', 'date', 'check_in_photo_path', 'check_out_photo_path']);
        
        if ($logs->isEmpty()) {
            $this->warn('   No attendance logs found');
        } else {
            $headers = ['ID', 'Date', 'Check-In Photo', 'Check-Out Photo'];
            $rows = $logs->map(function ($log) {
                return [
                    $log->id,
                    $log->date,
                    $log->check_in_photo_path ? substr($log->check_in_photo_path, 0, 60) . '...' : '❌ NULL',
                    $log->check_out_photo_path ? substr($log->check_out_photo_path, 0, 60) . '...' : '❌ NULL',
                ];
            })->toArray();
            $this->table($headers, $rows);
        }
        $this->newLine();

        // 5. Check Laravel log for photo errors
        $this->info('5. Recent Photo Errors in Log:');
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            $lines = explode("\n", $logContent);
            $photoErrors = array_filter($lines, function ($line) {
                return stripos($line, 'photo') !== false && 
                       (stripos($line, 'error') !== false || stripos($line, 'failed') !== false || stripos($line, 'warning') !== false);
            });
            $recentErrors = array_slice($photoErrors, -5);
            if (empty($recentErrors)) {
                $this->line('   No photo-related errors found in log');
            } else {
                foreach ($recentErrors as $error) {
                    $this->warn('   ' . substr(trim($error), 0, 200));
                }
            }
        } else {
            $this->warn('   Log file not found');
        }

        $this->newLine();
        $this->info('=== Diagnostics Complete ===');

        return 0;
    }
}
