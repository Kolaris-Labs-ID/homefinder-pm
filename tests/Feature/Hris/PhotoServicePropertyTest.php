<?php

/**
 * Property-Based Tests for PhotoService
 *
 * These tests verify correctness properties defined in the design document
 * using property-based testing approach with random data generation.
 */

use App\Services\Hris\PhotoService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Use fake storage for tests (local fallback mode)
    Storage::fake('local');
});

/**
 * Helper to create a PhotoService configured for local storage testing.
 */
function createLocalPhotoService(): PhotoService
{
    $service = new PhotoService();
    // Clear Supabase config to force local storage
    $service->clearSupabaseConfig();
    return $service;
}

/**
 * **Feature: hris-phase2-attendance, Property 6: Photo storage with timestamp overlay**
 *
 * For any check-in or check-out with a photo provided, the system SHALL store
 * the photo and the resulting file path SHALL be recorded in the AttendanceLog.
 * The overlay SHALL contain the clock type ("Clock in" or "Clock out"),
 * time in HH:MM format, and the readable address.
 *
 * **Validates: Requirements 1.7, 1.8**
 */
test('Property 6: Photo storage with timestamp overlay', function () {
    $photoService = createLocalPhotoService();

    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
        // Generate random clock type
        $clockType = fake()->randomElement(['in', 'out']);

        // Generate random time
        $time = Carbon::now()
            ->setHour(fake()->numberBetween(0, 23))
            ->setMinute(fake()->numberBetween(0, 59))
            ->setSecond(fake()->numberBetween(0, 59));

        // Generate random address components
        $building = fake()->company();
        $street = fake()->streetName();
        $city = fake()->city();
        $address = "{$building}, {$street}, {$city}";

        // Generate random employee ID (optional)
        $employeeId = fake()->boolean(70) ? fake()->numberBetween(1, 1000) : null;

        // Create a fake image file
        $photo = UploadedFile::fake()->image('attendance.jpg', 640, 480);

        // Process the photo (will use local storage in test environment)
        $storedPath = $photoService->processAttendancePhoto($photo, $clockType, $time, $address, $employeeId);

        // Property 1: The stored path/URL should be returned and not empty
        expect($storedPath)->not->toBeEmpty();

        // Property 2: For local storage, the file should exist
        expect(Storage::disk('local')->exists($storedPath))->toBeTrue(
            "Photo should be stored at path: {$storedPath}"
        );

        // Property 3: The path/filename should contain the clock type
        expect($storedPath)->toContain("_{$clockType}_");

        // Property 4: The path/filename should contain the date
        $expectedDate = $time->format('Y-m-d');
        expect($storedPath)->toContain($expectedDate);

        // Property 5: If employee ID provided, path should contain it
        if ($employeeId) {
            expect($storedPath)->toContain("emp_{$employeeId}");
        }

        // Property 6: Verify it's a valid JPEG
        $fileContent = Storage::disk('local')->get($storedPath);
        expect($fileContent)->not->toBeEmpty();

        // Check JPEG magic bytes (FFD8FF)
        $magicBytes = bin2hex(substr($fileContent, 0, 3));
        expect($magicBytes)->toBe('ffd8ff', 'Stored file should be a valid JPEG');

        // Property 7: Verify overlay text generation is correct
        $overlayText = $photoService->getOverlayText($clockType, $time, $address);

        // Clock text should contain correct label
        $expectedLabel = $clockType === 'in' ? 'Clock in' : 'Clock out';
        expect($overlayText['clock_text'])->toContain($expectedLabel);

        // Clock text should contain time in HH:MM format
        $expectedTime = $time->format('H:i');
        expect($overlayText['clock_text'])->toContain($expectedTime);

        // Full clock text format should be "Clock in/out: HH:MM"
        expect($overlayText['clock_text'])->toBe("{$expectedLabel}: {$expectedTime}");

        // Address should be preserved
        expect($overlayText['address'])->toBe($address);

        // Clean up for next iteration
        Storage::disk('local')->delete($storedPath);
    }
})->group('property-test', 'hris-attendance');

/**
 * Test that invalid clock type is rejected.
 */
test('Invalid clock type is rejected', function () {
    $photoService = createLocalPhotoService();
    $photo = UploadedFile::fake()->image('test.jpg', 640, 480);
    $time = Carbon::now();
    $address = 'Test Building, Test Street, Test City';

    // Test with invalid clock types
    $invalidTypes = ['invalid', 'check', 'clock', '', 'IN', 'OUT', 'In', 'Out'];

    foreach ($invalidTypes as $invalidType) {
        expect(fn() => $photoService->processAttendancePhoto($photo, $invalidType, $time, $address))
            ->toThrow(\InvalidArgumentException::class);
    }
})->group('property-test', 'hris-attendance');

/**
 * Test that different image formats are supported.
 */
test('Different image formats are supported', function () {
    $photoService = createLocalPhotoService();
    $time = Carbon::now();
    $address = 'Test Building, Test Street, Test City';

    // Test supported formats
    $formats = ['jpg', 'jpeg', 'png', 'gif'];

    foreach ($formats as $format) {
        $photo = UploadedFile::fake()->image("test.{$format}", 640, 480);
        $storedPath = $photoService->processAttendancePhoto($photo, 'in', $time, $address);

        expect(Storage::disk('local')->exists($storedPath))->toBeTrue(
            "Photo in {$format} format should be stored successfully"
        );

        // Output should always be JPEG
        expect($storedPath)->toEndWith('.jpg');

        Storage::disk('local')->delete($storedPath);
    }
})->group('property-test', 'hris-attendance');

/**
 * Test photo deletion functionality (local storage).
 */
test('Photo can be deleted from local storage', function () {
    $photoService = createLocalPhotoService();
    $photo = UploadedFile::fake()->image('test.jpg', 640, 480);
    $time = Carbon::now();
    $address = 'Test Building, Test Street, Test City';

    // Store a photo
    $storedPath = $photoService->processAttendancePhoto($photo, 'in', $time, $address);
    expect(Storage::disk('local')->exists($storedPath))->toBeTrue();

    // Delete the photo
    $deleted = $photoService->deletePhoto($storedPath);
    expect($deleted)->toBeTrue();
    expect(Storage::disk('local')->exists($storedPath))->toBeFalse();

    // Deleting non-existent photo should return false
    $deletedAgain = $photoService->deletePhoto($storedPath);
    expect($deletedAgain)->toBeFalse();
})->group('property-test', 'hris-attendance');

/**
 * Test photo existence check.
 */
test('Photo existence can be checked', function () {
    $photoService = createLocalPhotoService();
    $photo = UploadedFile::fake()->image('test.jpg', 640, 480);
    $time = Carbon::now();
    $address = 'Test Building, Test Street, Test City';

    // Non-existent path should return false
    expect($photoService->photoExists('non/existent/path.jpg'))->toBeFalse();

    // Store a photo
    $storedPath = $photoService->processAttendancePhoto($photo, 'in', $time, $address);

    // Existing path should return true
    expect($photoService->photoExists($storedPath))->toBeTrue();

    // Clean up
    Storage::disk('local')->delete($storedPath);
})->group('property-test', 'hris-attendance');

/**
 * Test unique filename generation.
 */
test('Each photo gets a unique filename', function () {
    $photoService = createLocalPhotoService();
    $time = Carbon::now();
    $address = 'Test Building, Test Street, Test City';

    $storedPaths = [];

    // Generate multiple photos with same parameters
    for ($i = 0; $i < 50; $i++) {
        $photo = UploadedFile::fake()->image('test.jpg', 640, 480);
        $storedPath = $photoService->processAttendancePhoto($photo, 'in', $time, $address);

        // Each path should be unique
        expect($storedPaths)->not->toContain($storedPath, 'Each photo should have a unique filename');

        $storedPaths[] = $storedPath;
    }

    // Clean up
    foreach ($storedPaths as $path) {
        Storage::disk('local')->delete($path);
    }
})->group('property-test', 'hris-attendance');

/**
 * Test Supabase configuration detection.
 */
test('Supabase configuration is detected correctly', function () {
    $photoService = new PhotoService();

    // Clear config first
    $photoService->clearSupabaseConfig();

    // Without configuration, should return false
    expect($photoService->isSupabaseConfigured())->toBeFalse();

    // With configuration, should return true
    $photoService->setSupabaseConfig(
        'https://test.supabase.co',
        'test-key',
        'test-bucket'
    );
    expect($photoService->isSupabaseConfigured())->toBeTrue();
})->group('property-test', 'hris-attendance');

/**
 * Test file organization by year/month/employee.
 */
test('Files are organized by year, month, and employee', function () {
    $photoService = createLocalPhotoService();
    $time = Carbon::create(2025, 6, 15, 10, 30, 0);
    $address = 'Test Building, Test Street, Test City';
    $employeeId = 123;

    $photo = UploadedFile::fake()->image('test.jpg', 640, 480);
    $storedPath = $photoService->processAttendancePhoto($photo, 'in', $time, $address, $employeeId);

    // Should contain year/month structure
    expect($storedPath)->toContain('2025/06');

    // Should contain employee folder
    expect($storedPath)->toContain('emp_123');

    // Clean up
    Storage::disk('local')->delete($storedPath);
})->group('property-test', 'hris-attendance');
