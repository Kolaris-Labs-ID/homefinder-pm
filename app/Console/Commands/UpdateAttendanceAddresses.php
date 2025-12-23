<?php

namespace App\Console\Commands;

use App\Models\Hris\AttendanceLog;
use App\Services\Hris\GeocodingService;
use Illuminate\Console\Command;

class UpdateAttendanceAddresses extends Command
{
    protected $signature = 'attendance:update-addresses {--limit=100 : Maximum records to process}';
    protected $description = 'Update attendance records with coordinate-based addresses to readable addresses';

    public function handle(): int
    {
        $geocodingService = new GeocodingService();
        $limit = (int) $this->option('limit');

        // Find records with coordinate-based addresses (fallback format)
        $records = AttendanceLog::where(function ($query) {
            $query->where('check_in_address', 'LIKE', 'Location (%')
                ->whereNotNull('check_in_latitude')
                ->whereNotNull('check_in_longitude');
        })->orWhere(function ($query) {
            $query->where('check_out_address', 'LIKE', 'Location (%')
                ->whereNotNull('check_out_latitude')
                ->whereNotNull('check_out_longitude');
        })->limit($limit)->get();

        if ($records->isEmpty()) {
            $this->info('No attendance records with coordinate-based addresses found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$records->count()} records to update.");
        $bar = $this->output->createProgressBar($records->count());
        $bar->start();

        $updated = 0;
        $failed = 0;

        foreach ($records as $record) {
            try {
                $changes = [];

                // Update check-in address if needed
                if ($record->check_in_address &&
                    str_starts_with($record->check_in_address, 'Location (') &&
                    $record->check_in_latitude &&
                    $record->check_in_longitude) {

                    $address = $geocodingService->reverseGeocode(
                        (float) $record->check_in_latitude,
                        (float) $record->check_in_longitude
                    );

                    if (!str_starts_with($address, 'Location (')) {
                        $changes['check_in_address'] = $address;
                    }
                }

                // Update check-out address if needed
                if ($record->check_out_address &&
                    str_starts_with($record->check_out_address, 'Location (') &&
                    $record->check_out_latitude &&
                    $record->check_out_longitude) {

                    $address = $geocodingService->reverseGeocode(
                        (float) $record->check_out_latitude,
                        (float) $record->check_out_longitude
                    );

                    if (!str_starts_with($address, 'Location (')) {
                        $changes['check_out_address'] = $address;
                    }
                }

                if (!empty($changes)) {
                    $record->update($changes);
                    $updated++;
                }
            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->error("Failed to update record {$record->id}: {$e->getMessage()}");
            }

            $bar->advance();

            // Rate limiting to avoid API throttling
            usleep(200000); // 200ms delay between requests
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Updated: {$updated} records");

        if ($failed > 0) {
            $this->warn("Failed: {$failed} records");
        }

        return Command::SUCCESS;
    }
}
