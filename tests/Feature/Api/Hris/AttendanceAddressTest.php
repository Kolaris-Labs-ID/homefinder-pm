<?php

/**
 * Tests for the address resolution fix.
 *
 * Ensures that check-in and check-out always have an address
 * (either from frontend, backend geocoding, or fallback)
 * and that empty strings from the frontend are treated as null.
 */

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Services\Hris\AttendanceService;
use App\Services\Hris\GeocodingService;
use App\Services\Hris\PhotoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->employee = Employee::factory()->create([
        'hire_date' => now()->subYears(2),
    ]);
});

// =========================================================================
// Address resolution tests
// =========================================================================

describe('Check-in address resolution', function () {
    it('uses backend geocoded address when coordinates provided', function () {
        // Mock geocoding service to return a known address
        $mockGeocodingService = Mockery::mock(GeocodingService::class);
        $mockGeocodingService->shouldReceive('reverseGeocode')
            ->with(-6.2088, 106.8456)
            ->andReturn('Jl. Sudirman No.1, Senayan, Jakarta Selatan');

        $mockPhotoService = Mockery::mock(PhotoService::class);

        $service = new AttendanceService($mockGeocodingService, $mockPhotoService);

        $result = $service->checkIn($this->employee, [
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'address' => 'Frontend Address',
            'source' => AttendanceLog::SOURCE_MOBILE,
            'mode' => AttendanceLog::MODE_OFFICE,
        ]);

        // Backend geocoded address should override frontend address
        expect($result->check_in_address)->toBe('Jl. Sudirman No.1, Senayan, Jakarta Selatan');
    });

    it('falls back to frontend address when geocoding returns coordinate fallback', function () {
        $mockGeocodingService = Mockery::mock(GeocodingService::class);
        $mockGeocodingService->shouldReceive('reverseGeocode')
            ->andReturn('Location (-6.208800, 106.845600)'); // Coordinate fallback

        $mockPhotoService = Mockery::mock(PhotoService::class);

        $service = new AttendanceService($mockGeocodingService, $mockPhotoService);

        $result = $service->checkIn($this->employee, [
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'address' => 'Frontend Address',
            'source' => AttendanceLog::SOURCE_MOBILE,
        ]);

        // Should keep frontend address when geocoding returns coordinate fallback
        expect($result->check_in_address)->toBe('Frontend Address');
    });

    it('treats empty address string from frontend as null', function () {
        // Mock geocoding to return a real address
        $mockGeocodingService = Mockery::mock(GeocodingService::class);
        $mockGeocodingService->shouldReceive('reverseGeocode')
            ->andReturn('Jl. Gatot Subroto, Jakarta');

        $mockPhotoService = Mockery::mock(PhotoService::class);

        $service = new AttendanceService($mockGeocodingService, $mockPhotoService);

        $result = $service->checkIn($this->employee, [
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'address' => '', // Empty string from frontend (the bug)
            'source' => AttendanceLog::SOURCE_MOBILE,
        ]);

        // Should NOT store empty string — geocoded address should be used
        expect($result->check_in_address)->toBe('Jl. Gatot Subroto, Jakarta');
        expect($result->check_in_address)->not->toBe('');
    });

    it('stores null address when no coordinates and empty frontend address', function () {
        $mockGeocodingService = Mockery::mock(GeocodingService::class);
        $mockPhotoService = Mockery::mock(PhotoService::class);

        $service = new AttendanceService($mockGeocodingService, $mockPhotoService);

        $result = $service->checkIn($this->employee, [
            'address' => '', // Empty string, no coordinates
            'source' => AttendanceLog::SOURCE_MOBILE,
        ]);

        // With no coordinates and empty address, should store null
        expect($result->check_in_address)->toBeNull();
    });
});

describe('Check-out address resolution', function () {
    beforeEach(function () {
        // Create a check-in record first
        $this->attendanceLog = AttendanceLog::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::today('Asia/Jakarta'),
            'check_in_time' => Carbon::now('Asia/Jakarta')->subHours(8),
            'check_out_time' => null,
        ]);
    });

    it('uses backend geocoded address for check-out', function () {
        $mockGeocodingService = Mockery::mock(GeocodingService::class);
        $mockGeocodingService->shouldReceive('reverseGeocode')
            ->andReturn('Jl. Thamrin No.5, Jakarta Pusat');

        $mockPhotoService = Mockery::mock(PhotoService::class);

        $service = new AttendanceService($mockGeocodingService, $mockPhotoService);

        $result = $service->checkOut($this->employee, [
            'latitude' => -6.1952,
            'longitude' => 106.8231,
            'address' => 'Frontend Checkout Address',
        ]);

        expect($result->check_out_address)->toBe('Jl. Thamrin No.5, Jakarta Pusat');
    });

    it('treats empty check-out address as null and uses geocoding', function () {
        $mockGeocodingService = Mockery::mock(GeocodingService::class);
        $mockGeocodingService->shouldReceive('reverseGeocode')
            ->andReturn('Menara BCA, Jl. Thamrin, Jakarta');

        $mockPhotoService = Mockery::mock(PhotoService::class);

        $service = new AttendanceService($mockGeocodingService, $mockPhotoService);

        $result = $service->checkOut($this->employee, [
            'latitude' => -6.1952,
            'longitude' => 106.8231,
            'address' => '', // Empty string from frontend (the bug)
        ]);

        // Should NOT store empty string
        expect($result->check_out_address)->toBe('Menara BCA, Jl. Thamrin, Jakarta');
        expect($result->check_out_address)->not->toBe('');
    });

    it('stores null when no coordinates, empty address, and geocoding fails', function () {
        $mockGeocodingService = Mockery::mock(GeocodingService::class);
        $mockPhotoService = Mockery::mock(PhotoService::class);

        $service = new AttendanceService($mockGeocodingService, $mockPhotoService);

        $result = $service->checkOut($this->employee, [
            'address' => '', // Empty string, no coordinates
        ]);

        expect($result->check_out_address)->toBeNull();
    });
});

describe('Check-in API address in response', function () {
    it('always includes address field in check-in response', function () {
        Sanctum::actingAs($this->employee->user);

        $response = postJson('/api/hris/attendance/check-in', [
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'address' => 'Jl. Sudirman, Jakarta',
            'source' => AttendanceLog::SOURCE_MOBILE,
            'mode' => AttendanceLog::MODE_OFFICE,
        ]);

        $response->assertCreated();

        // Address field must exist and not be empty
        $data = $response->json('data');
        expect($data['check_in']['address'])->not->toBeNull();
        expect($data['check_in']['address'])->not->toBe('');
    });

    it('always includes address field in check-out response', function () {
        Sanctum::actingAs($this->employee->user);

        // Create check-in first
        AttendanceLog::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::today('Asia/Jakarta'),
            'check_in_time' => Carbon::now('Asia/Jakarta')->subHours(8),
            'check_out_time' => null,
        ]);

        $response = postJson('/api/hris/attendance/check-out', [
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'address' => 'Jl. Sudirman, Jakarta',
        ]);

        $response->assertOk();

        $data = $response->json('data');
        expect($data['check_out']['address'])->not->toBeNull();
        expect($data['check_out']['address'])->not->toBe('');
    });
});

// =========================================================================
// No phantom check-in tests
// =========================================================================

describe('No phantom attendance records', function () {
    it('daily endpoint returns null when no check-in exists', function () {
        Sanctum::actingAs($this->employee->user);

        $response = \Pest\Laravel\getJson('/api/hris/attendance/daily');

        $response->assertOk()
            ->assertJsonPath('data', null);
    });

    it('check-in creates exactly one database record', function () {
        Sanctum::actingAs($this->employee->user);

        expect(AttendanceLog::count())->toBe(0);

        $response = postJson('/api/hris/attendance/check-in', [
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'source' => AttendanceLog::SOURCE_MOBILE,
            'mode' => AttendanceLog::MODE_OFFICE,
        ]);

        $response->assertCreated();

        // Exactly one record should exist
        expect(AttendanceLog::count())->toBe(1);

        // The returned ID should match the database record
        $returnedId = $response->json('data.id');
        expect(AttendanceLog::find($returnedId))->not->toBeNull();
    });

    it('daily endpoint returns the record after successful check-in', function () {
        Sanctum::actingAs($this->employee->user);

        // Check-in
        $checkInResponse = postJson('/api/hris/attendance/check-in', [
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'source' => AttendanceLog::SOURCE_MOBILE,
            'mode' => AttendanceLog::MODE_OFFICE,
        ]);

        $checkInResponse->assertCreated();
        $checkInId = $checkInResponse->json('data.id');

        // Daily should return the same record
        $dailyResponse = \Pest\Laravel\getJson('/api/hris/attendance/daily');

        $dailyResponse->assertOk();
        expect($dailyResponse->json('data.id'))->toBe($checkInId);
        expect($dailyResponse->json('data.check_in.time'))->not->toBeNull();
    });

    it('failed check-in does not create database record', function () {
        Sanctum::actingAs($this->employee->user);

        // Create existing check-in to force failure
        AttendanceLog::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::today('Asia/Jakarta'),
            'check_in_time' => Carbon::now('Asia/Jakarta'),
        ]);

        expect(AttendanceLog::count())->toBe(1);

        // Attempt duplicate check-in
        $response = postJson('/api/hris/attendance/check-in', [
            'source' => AttendanceLog::SOURCE_MOBILE,
            'mode' => AttendanceLog::MODE_OFFICE,
        ]);

        $response->assertUnprocessable();

        // No extra record should be created
        expect(AttendanceLog::count())->toBe(1);
    });

    it('check-in response ID matches queryable record', function () {
        Sanctum::actingAs($this->employee->user);

        $response = postJson('/api/hris/attendance/check-in', [
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'source' => AttendanceLog::SOURCE_MOBILE,
            'mode' => AttendanceLog::MODE_OFFICE,
        ]);

        $response->assertCreated();

        $id = $response->json('data.id');

        // ID must be a positive integer (not -1 or phantom)
        expect($id)->toBeGreaterThan(0);

        // Record must be queryable from DB
        $log = AttendanceLog::find($id);
        expect($log)->not->toBeNull();
        expect($log->employee_id)->toBe($this->employee->id);
        expect($log->check_in_time)->not->toBeNull();
    });
});
