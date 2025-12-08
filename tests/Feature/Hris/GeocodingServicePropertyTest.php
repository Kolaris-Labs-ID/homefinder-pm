<?php

/**
 * Property-Based Tests for GeocodingService
 *
 * These tests verify correctness properties defined in the design document
 * using property-based testing approach with random data generation.
 */

use App\Services\Hris\GeocodingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // Clear cache before each test
    Cache::flush();
});

/**
 * **Feature: hris-phase2-attendance, Property 7: Reverse geocoding produces readable address**
 *
 * For any GPS coordinates provided during check-in or check-out, the system SHALL
 * perform reverse geocoding and store a human-readable address containing
 * building/location name, street, and city.
 *
 * **Validates: Requirements 1.9**
 */
test('Property 7: Reverse geocoding produces readable address', function () {
    // Clear all cache before starting
    Cache::flush();

    // Set up HTTP fake with a consistent response format
    Http::fake([
        '*' => Http::response([
            'results' => [
                [
                    'components' => [
                        'building' => 'Test Building',
                        'road' => 'Test Street',
                        'city' => 'Test City',
                    ],
                ],
            ],
            'status' => ['code' => 200],
        ], 200),
    ]);

    // Create service instance with API key
    $service = new GeocodingService();
    $service->setOpenCageApiKey('test-api-key');

    // Run 100 iterations with random valid GPS coordinates
    for ($i = 0; $i < 100; $i++) {
        // Generate unique GPS coordinates for each iteration
        $latitude = fake()->randomFloat(6, -89, 89);
        $longitude = fake()->randomFloat(6, -179, 179);

        // Clear the specific cache key for these coordinates
        $cacheKey = 'geocode:' . round($latitude, 5) . ':' . round($longitude, 5);
        Cache::forget($cacheKey);

        // Perform reverse geocoding
        $address = $service->reverseGeocode($latitude, $longitude);

        // Property: Result must be a non-empty string
        expect($address)->toBeString();
        expect($address)->not->toBeEmpty();

        // Property: Address should be formatted with comma separators
        // when multiple components are present (Building, Street, City)
        expect($address)->toContain(', ');

        // Property: Address should contain building, street, and city components
        // The format should be "Building, Street, City"
        $parts = explode(', ', $address);
        expect(count($parts))->toBe(3, "Address should have 3 parts separated by ', ': got '$address'");

        // Property: Each part should be non-empty
        foreach ($parts as $part) {
            expect($part)->not->toBeEmpty();
        }

        // Property: Address should match the expected format from mock response
        expect($address)->toBe('Test Building, Test Street, Test City');
    }
})->group('property-test', 'hris-attendance', 'geocoding');

/**
 * Property: Invalid coordinates should return fallback address
 *
 * For any GPS coordinates outside valid ranges, the system SHALL return
 * a fallback address format without throwing an exception.
 */
test('Invalid coordinates return fallback address', function () {
    $service = new GeocodingService();

    // Test invalid latitude values (outside -90 to 90)
    $invalidLatitudes = [
        fake()->randomFloat(2, 91, 180),   // Too high
        fake()->randomFloat(2, -180, -91), // Too low
    ];

    foreach ($invalidLatitudes as $invalidLat) {
        $longitude = fake()->randomFloat(8, -180, 180);
        $address = $service->reverseGeocode($invalidLat, $longitude);

        // Property: Should return fallback format with coordinates
        expect($address)->toBeString();
        expect($address)->toContain('Location');
    }

    // Test invalid longitude values (outside -180 to 180)
    $invalidLongitudes = [
        fake()->randomFloat(2, 181, 360),   // Too high
        fake()->randomFloat(2, -360, -181), // Too low
    ];

    foreach ($invalidLongitudes as $invalidLng) {
        $latitude = fake()->randomFloat(8, -90, 90);
        $address = $service->reverseGeocode($latitude, $invalidLng);

        // Property: Should return fallback format with coordinates
        expect($address)->toBeString();
        expect($address)->toContain('Location');
    }
})->group('property-test', 'hris-attendance', 'geocoding');

/**
 * Property: API failures should be handled gracefully
 *
 * When the geocoding API fails, the system SHALL return a fallback address
 * without throwing an exception.
 */
test('API failures return fallback address gracefully', function () {
    // Run 100 iterations with various API failure scenarios
    for ($i = 0; $i < 100; $i++) {
        $latitude = fake()->randomFloat(8, -90, 90);
        $longitude = fake()->randomFloat(8, -180, 180);

        // Clear cache for this coordinate
        $cacheKey = 'geocode:' . round($latitude, 5) . ':' . round($longitude, 5);
        Cache::forget($cacheKey);

        // Simulate different API failure scenarios
        $failureScenario = fake()->randomElement(['timeout', 'error_500', 'error_response', 'empty_response']);

        Http::fake([
            '*' => match ($failureScenario) {
                'timeout' => Http::response(null, 408),
                'error_500' => Http::response(null, 500),
                'error_response' => Http::response(['status' => ['code' => 400, 'message' => 'Unable to geocode']], 200),
                'empty_response' => Http::response(['results' => [], 'status' => ['code' => 200]], 200),
            },
        ]);

        // Create service with API key
        $service = new GeocodingService();
        $service->setOpenCageApiKey('test-api-key');

        // Property: Should not throw exception
        $address = $service->reverseGeocode($latitude, $longitude);

        // Property: Should return a valid fallback string
        expect($address)->toBeString();
        expect($address)->not->toBeEmpty();

        // Property: Fallback should contain coordinate information or Unknown Location
        $isFallback = str_contains($address, 'Location') || str_contains($address, 'Unknown');
        expect($isFallback)->toBeTrue();
    }
})->group('property-test', 'hris-attendance', 'geocoding');

/**
 * Property: Coordinate validation is accurate
 *
 * The validateCoordinates method SHALL correctly identify valid and invalid
 * GPS coordinate ranges.
 */
test('Coordinate validation is accurate', function () {
    $service = new GeocodingService();

    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Test valid coordinates
        $validLat = fake()->randomFloat(8, -90, 90);
        $validLng = fake()->randomFloat(8, -180, 180);
        expect($service->validateCoordinates($validLat, $validLng))->toBeTrue();

        // Test boundary values (should be valid)
        expect($service->validateCoordinates(-90, -180))->toBeTrue();
        expect($service->validateCoordinates(90, 180))->toBeTrue();
        expect($service->validateCoordinates(0, 0))->toBeTrue();

        // Test invalid latitude (too high)
        $invalidLatHigh = fake()->randomFloat(2, 90.01, 180);
        expect($service->validateCoordinates($invalidLatHigh, $validLng))->toBeFalse();

        // Test invalid latitude (too low)
        $invalidLatLow = fake()->randomFloat(2, -180, -90.01);
        expect($service->validateCoordinates($invalidLatLow, $validLng))->toBeFalse();

        // Test invalid longitude (too high)
        $invalidLngHigh = fake()->randomFloat(2, 180.01, 360);
        expect($service->validateCoordinates($validLat, $invalidLngHigh))->toBeFalse();

        // Test invalid longitude (too low)
        $invalidLngLow = fake()->randomFloat(2, -360, -180.01);
        expect($service->validateCoordinates($validLat, $invalidLngLow))->toBeFalse();
    }
})->group('property-test', 'hris-attendance', 'geocoding');

/**
 * Property: Caching works correctly for geocoding results
 *
 * For any GPS coordinates, subsequent calls with the same coordinates
 * SHALL return cached results without making additional API calls.
 */
test('Geocoding results are cached correctly', function () {
    // Clear all cache first
    Cache::flush();

    // Track API call count
    $apiCallCount = 0;

    // Set up HTTP fake with OpenCage format response that tracks calls
    Http::fake(function ($request) use (&$apiCallCount) {
        $apiCallCount++;
        return Http::response([
            'results' => [
                [
                    'components' => [
                        'building' => 'Cached Building',
                        'road' => 'Cached Street',
                        'city' => 'Cached City',
                    ],
                ],
            ],
            'status' => ['code' => 200],
        ], 200);
    });

    // Use fixed coordinates for this test
    $latitude = 45.12345;
    $longitude = 90.12345;

    // Create service instance with API key
    $service = new GeocodingService();
    $service->setOpenCageApiKey('test-api-key');

    // First call should hit the API
    $firstResult = $service->reverseGeocode($latitude, $longitude);
    expect($apiCallCount)->toBe(1);
    expect($firstResult)->toBe('Cached Building, Cached Street, Cached City');

    // Second call with same coordinates should use cache (no additional API call)
    $secondResult = $service->reverseGeocode($latitude, $longitude);
    expect($apiCallCount)->toBe(1); // Still 1, not 2

    // Property: Both results should be identical
    expect($secondResult)->toBe($firstResult);

    // Run additional iterations with different coordinates to verify caching works
    for ($i = 0; $i < 50; $i++) {
        $lat = 10.0 + $i;
        $lng = 20.0 + $i;

        // Clear cache for this specific coordinate
        $cacheKey = 'geocode:' . round($lat, 5) . ':' . round($lng, 5);
        Cache::forget($cacheKey);

        $callsBefore = $apiCallCount;

        // First call for this coordinate
        $result1 = $service->reverseGeocode($lat, $lng);
        expect($apiCallCount)->toBe($callsBefore + 1);

        // Second call should use cache
        $result2 = $service->reverseGeocode($lat, $lng);
        expect($apiCallCount)->toBe($callsBefore + 1); // No additional call

        // Results should match
        expect($result2)->toBe($result1);
    }
})->group('property-test', 'hris-attendance', 'geocoding');
