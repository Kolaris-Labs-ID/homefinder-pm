<?php

namespace App\Services\Hris;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PhotoService
{
    /**
     * Supabase project URL.
     */
    protected ?string $supabaseUrl;

    /**
     * Supabase service role key for server-side uploads.
     */
    protected ?string $supabaseKey;

    /**
     * Supabase storage bucket name.
     */
    protected string $bucketName;

    public function __construct()
    {
        $this->supabaseUrl = config('services.supabase.url') ?: null;
        $this->supabaseKey = config('services.supabase.key') ?: null;
        $this->bucketName = config('services.supabase.attendance_bucket', 'attendance-photos');
    }

    /**
     * Process an attendance photo by adding a timestamp overlay and upload to Supabase.
     *
     * The overlay includes:
     * - Clock type: "Clock in: HH:MM" or "Clock out: HH:MM"
     * - Readable address: "Building name, Street, City"
     *
     * @param UploadedFile $photo The uploaded photo file
     * @param string $clockType 'in' or 'out'
     * @param Carbon $time The timestamp to display
     * @param string $address The readable address to display
     * @param int|null $employeeId Optional employee ID for organizing files
     * @return string The public URL of the stored photo
     * @throws \Exception If Supabase is not configured or photo processing fails
     */
    public function processAttendancePhoto(
        UploadedFile $photo,
        string $clockType,
        Carbon $time,
        string $address,
        ?int $employeeId = null
    ): string {
        // Validate clock type first
        $this->validateClockType($clockType);

        // Check if we should use Supabase or local storage
        $useSupabase = $this->isSupabaseConfigured();

        Log::info('Processing attendance photo', [
            'employee_id' => $employeeId,
            'clock_type' => $clockType,
            'time' => $time->toDateTimeString(),
            'address' => $address,
            'photo_size' => $photo->getSize(),
            'photo_mime' => $photo->getMimeType(),
            'storage_mode' => $useSupabase ? 'supabase' : 'local',
        ]);

        try {
            // Format the overlay text
            $clockLabel = $clockType === 'in' ? 'Clock in' : 'Clock out';
            $timeFormatted = $time->format('H:i');
            $overlayText = "{$clockLabel}: {$timeFormatted}";

            // Process the image with overlay
            $processedImage = $this->addOverlayToImage($photo, $overlayText, $address);

            // Generate unique filename with optional employee folder
            $filename = $this->generateFilename($clockType, $time, $employeeId);

            if ($useSupabase) {
                Log::info('Uploading to Supabase', [
                    'filename' => $filename,
                    'image_size' => strlen($processedImage),
                ]);

                // Upload to Supabase and return the public URL
                $url = $this->uploadToSupabase($processedImage, $filename);

                Log::info('Photo uploaded successfully to Supabase', [
                    'employee_id' => $employeeId,
                    'url' => $url,
                ]);

                return $url;
            } else {
                // Fallback to local storage
                Log::info('Storing to local storage', [
                    'filename' => $filename,
                    'image_size' => strlen($processedImage),
                ]);

                $path = $this->storeToLocal($processedImage, $filename);

                Log::info('Photo stored successfully to local storage', [
                    'employee_id' => $employeeId,
                    'path' => $path,
                ]);

                return $path;
            }
        } catch (\Exception $e) {
            Log::error('Photo processing failed', [
                'clock_type' => $clockType,
                'time' => $time->toDateTimeString(),
                'address' => $address,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \Exception('Failed to process attendance photo: ' . $e->getMessage());
        }
    }

    /**
     * Check if Supabase is properly configured.
     *
     * @return bool
     */
    public function isSupabaseConfigured(): bool
    {
        return !empty($this->supabaseUrl) && !empty($this->supabaseKey);
    }

    /**
     * Store processed image to local storage.
     *
     * @param string $imageData The processed image binary data
     * @param string $filename The filename to use
     * @return string The storage path
     * @throws \Exception If storage fails
     */
    protected function storeToLocal(string $imageData, string $filename): string
    {
        $disk = Storage::disk('local');
        $path = "attendance-photos/{$filename}";

        if (!$disk->put($path, $imageData)) {
            throw new \Exception('Failed to store photo to local storage');
        }

        return $path;
    }

    /**
     * Upload processed image to Supabase Storage.
     *
     * @param string $imageData The processed image binary data
     * @param string $filename The filename to use
     * @return string The public URL of the uploaded file
     * @throws \Exception If upload fails
     */
    protected function uploadToSupabase(string $imageData, string $filename): string
    {
        $uploadUrl = "{$this->supabaseUrl}/storage/v1/object/{$this->bucketName}/{$filename}";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->supabaseKey}",
            'Content-Type' => 'image/jpeg',
            'x-upsert' => 'true', // Overwrite if exists
        ])->withBody($imageData, 'image/jpeg')
          ->post($uploadUrl);

        if (!$response->successful()) {
            Log::error('Supabase upload failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'filename' => $filename,
            ]);
            throw new \Exception('Failed to upload photo to Supabase: ' . $response->body());
        }

        // Return the public URL
        return "{$this->supabaseUrl}/storage/v1/object/public/{$this->bucketName}/{$filename}";
    }

    /**
     * Add text overlay to an image using GD library.
     *
     * @param UploadedFile $photo
     * @param string $clockText The clock in/out text with time
     * @param string $address The address text
     * @return string The encoded image data
     */
    protected function addOverlayToImage(UploadedFile $photo, string $clockText, string $address): string
    {
        // Get image info
        $imageInfo = getimagesize($photo->getPathname());
        if ($imageInfo === false) {
            throw new \Exception('Invalid image file');
        }

        $mimeType = $imageInfo['mime'];

        // Create image resource based on type
        $image = match ($mimeType) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($photo->getPathname()),
            'image/png' => imagecreatefrompng($photo->getPathname()),
            'image/gif' => imagecreatefromgif($photo->getPathname()),
            'image/webp' => imagecreatefromwebp($photo->getPathname()),
            default => throw new \Exception("Unsupported image type: {$mimeType}"),
        };

        if ($image === false) {
            throw new \Exception('Failed to create image resource');
        }

        // Get image dimensions
        $width = imagesx($image);
        $height = imagesy($image);

        // Define colors
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocatealpha($image, 0, 0, 0, 50); // Semi-transparent black

        // Calculate text positioning
        $padding = 10;
        $fontSize = 4; // GD built-in font size (1-5)
        $lineHeight = imagefontheight($fontSize) + 5;

        // Calculate overlay box dimensions
        $boxHeight = ($lineHeight * 2) + ($padding * 2);
        $boxY = $height - $boxHeight;

        // Draw semi-transparent background rectangle
        imagefilledrectangle($image, 0, $boxY, $width, $height, $black);

        // Draw clock text (first line)
        $textY1 = $boxY + $padding;
        imagestring($image, $fontSize, $padding, $textY1, $clockText, $white);

        // Draw address text (second line) - truncate if too long
        $maxAddressLength = (int) (($width - ($padding * 2)) / imagefontwidth($fontSize));
        $truncatedAddress = strlen($address) > $maxAddressLength
            ? substr($address, 0, $maxAddressLength - 3) . '...'
            : $address;

        $textY2 = $textY1 + $lineHeight;
        imagestring($image, $fontSize, $padding, $textY2, $truncatedAddress, $white);

        // Capture output to string
        ob_start();
        imagejpeg($image, null, 85);
        $imageData = ob_get_clean();

        // Free memory
        imagedestroy($image);

        return $imageData;
    }

    /**
     * Generate a unique filename for the attendance photo.
     *
     * @param string $clockType
     * @param Carbon $time
     * @param int|null $employeeId
     * @return string
     */
    protected function generateFilename(string $clockType, Carbon $time, ?int $employeeId = null): string
    {
        $date = $time->format('Y-m-d');
        $timestamp = $time->format('His');
        $uniqueId = uniqid();

        // Organize by year/month/employee for better structure
        $year = $time->format('Y');
        $month = $time->format('m');

        if ($employeeId) {
            return "{$year}/{$month}/emp_{$employeeId}/{$date}_{$clockType}_{$timestamp}_{$uniqueId}.jpg";
        }

        return "{$year}/{$month}/{$date}_{$clockType}_{$timestamp}_{$uniqueId}.jpg";
    }

    /**
     * Delete an attendance photo from storage.
     *
     * @param string $pathOrUrl The storage path or public URL of the photo
     * @return bool True if deleted, false otherwise
     */
    public function deletePhoto(string $pathOrUrl): bool
    {
        // Check if it's a Supabase URL
        if (str_contains($pathOrUrl, '/storage/v1/object/public/')) {
            if (!$this->isSupabaseConfigured()) {
                return false;
            }

            // Extract filename from URL
            $pattern = "/\/storage\/v1\/object\/public\/{$this->bucketName}\/(.+)$/";
            if (!preg_match($pattern, $pathOrUrl, $matches)) {
                return false;
            }

            $filename = $matches[1];
            $deleteUrl = "{$this->supabaseUrl}/storage/v1/object/{$this->bucketName}/{$filename}";

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->supabaseKey}",
            ])->delete($deleteUrl);

            return $response->successful();
        }

        // Try local storage
        $disk = Storage::disk('local');
        if ($disk->exists($pathOrUrl)) {
            return $disk->delete($pathOrUrl);
        }

        return false;
    }

    /**
     * Check if a photo exists in storage.
     *
     * @param string $pathOrUrl The storage path or public URL of the photo
     * @return bool
     */
    public function photoExists(string $pathOrUrl): bool
    {
        // Check if it's a Supabase URL
        if (str_contains($pathOrUrl, '/storage/v1/object/public/')) {
            $response = Http::head($pathOrUrl);
            return $response->successful();
        }

        // Check local storage
        return Storage::disk('local')->exists($pathOrUrl);
    }

    /**
     * Get the overlay text that would be added to a photo.
     *
     * This is useful for testing to verify the overlay content.
     *
     * @param string $clockType 'in' or 'out'
     * @param Carbon $time
     * @param string $address
     * @return array{clock_text: string, address: string}
     */
    public function getOverlayText(string $clockType, Carbon $time, string $address): array
    {
        $clockLabel = $clockType === 'in' ? 'Clock in' : 'Clock out';
        $timeFormatted = $time->format('H:i');

        return [
            'clock_text' => "{$clockLabel}: {$timeFormatted}",
            'address' => $address,
        ];
    }

    /**
     * Set Supabase configuration (useful for testing).
     *
     * @param string $url
     * @param string $key
     * @param string $bucket
     * @return self
     */
    public function setSupabaseConfig(string $url, string $key, string $bucket): self
    {
        $this->supabaseUrl = $url;
        $this->supabaseKey = $key;
        $this->bucketName = $bucket;

        return $this;
    }

    /**
     * Clear Supabase configuration (useful for testing).
     *
     * @return self
     */
    public function clearSupabaseConfig(): self
    {
        $this->supabaseUrl = null;
        $this->supabaseKey = null;

        return $this;
    }

    /**
     * Validate clock type.
     *
     * @param string $clockType
     * @throws \InvalidArgumentException
     */
    protected function validateClockType(string $clockType): void
    {
        if (!in_array($clockType, ['in', 'out'])) {
            throw new \InvalidArgumentException('Clock type must be "in" or "out"');
        }
    }
}
