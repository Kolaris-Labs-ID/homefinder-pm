<?php

namespace App\Services\Hris;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GeocodingService
{
    /**
     * Cache duration for geocoding results in seconds (24 hours).
     */
    const CACHE_DURATION = 86400;

    /**
     * The geocoding provider to use.
     */
    protected string $provider;

    /**
     * OpenCage API key (if using OpenCage provider).
     */
    protected ?string $opencageApiKey;

    /**
     * Google Maps API key (if using Google provider).
     */
    protected ?string $googleApiKey;

    public function __construct()
    {
        $this->provider = config('services.geocoding.provider', 'opencage');
        $this->opencageApiKey = config('services.geocoding.opencage_api_key');
        $this->googleApiKey = config('services.geocoding.google_api_key');
    }

    /**
     * Perform reverse geocoding to convert GPS coordinates to a human-readable address.
     *
     * Returns formatted address: "Building/Location, Street, City"
     *
     * @param float $latitude GPS latitude (-90 to 90)
     * @param float $longitude GPS longitude (-180 to 180)
     * @return string Formatted address or fallback string on failure
     */
    public function reverseGeocode(float $latitude, float $longitude): string
    {
        // Validate coordinates
        if (!$this->validateCoordinates($latitude, $longitude)) {
            Log::warning('Invalid GPS coordinates provided for reverse geocoding', [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);
            return $this->formatFallbackAddress($latitude, $longitude);
        }

        // Check cache first
        $cacheKey = $this->getCacheKey($latitude, $longitude);
        $cachedAddress = Cache::get($cacheKey);

        if ($cachedAddress !== null) {
            return $cachedAddress;
        }

        try {
            $address = match ($this->provider) {
                'google' => $this->reverseGeocodeWithGoogle($latitude, $longitude),
                'nominatim' => $this->reverseGeocodeWithNominatim($latitude, $longitude),
                default => $this->reverseGeocodeWithOpenCage($latitude, $longitude),
            };

            // Cache the result
            Cache::put($cacheKey, $address, self::CACHE_DURATION);

            return $address;
        } catch (\Exception $e) {
            Log::warning('Reverse geocoding failed', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'provider' => $this->provider,
                'error' => $e->getMessage(),
            ]);

            return $this->formatFallbackAddress($latitude, $longitude);
        }
    }


    /**
     * Reverse geocode using OpenStreetMap Nominatim API.
     *
     * @param float $latitude
     * @param float $longitude
     * @return string
     * @throws \Exception
     */
    protected function reverseGeocodeWithNominatim(float $latitude, float $longitude): string
    {
        $response = Http::timeout(10)
            ->withHeaders([
                'User-Agent' => config('app.name', 'Laravel') . '/1.0',
            ])
            ->get('https://nominatim.openstreetmap.org/reverse', [
                'lat' => $latitude,
                'lon' => $longitude,
                'format' => 'json',
                'addressdetails' => 1,
                'zoom' => 18,
            ]);

        if (!$response->successful()) {
            throw new \Exception('Nominatim API request failed: ' . $response->status());
        }

        $data = $response->json();

        if (isset($data['error'])) {
            throw new \Exception('Nominatim API error: ' . $data['error']);
        }

        return $this->formatNominatimAddress($data);
    }

    /**
     * Reverse geocode using OpenCage Geocoding API.
     *
     * @param float $latitude
     * @param float $longitude
     * @return string
     * @throws \Exception
     */
    protected function reverseGeocodeWithOpenCage(float $latitude, float $longitude): string
    {
        if (empty($this->opencageApiKey)) {
            throw new \Exception('OpenCage API key not configured');
        }

        $response = Http::timeout(10)
            ->get('https://api.opencagedata.com/geocode/v1/json', [
                'q' => "{$latitude},{$longitude}",
                'key' => $this->opencageApiKey,
                'no_annotations' => 1,
                'limit' => 1,
            ]);

        if (!$response->successful()) {
            throw new \Exception('OpenCage API request failed: ' . $response->status());
        }

        $data = $response->json();

        if (isset($data['status']['code']) && $data['status']['code'] !== 200) {
            throw new \Exception('OpenCage API error: ' . ($data['status']['message'] ?? 'Unknown error'));
        }

        return $this->formatOpenCageAddress($data);
    }

    /**
     * Format address from OpenCage response.
     *
     * @param array $data
     * @return string
     */
    protected function formatOpenCageAddress(array $data): string
    {
        $result = $data['results'][0] ?? null;

        if (!$result) {
            return 'Unknown Location';
        }

        $components = $result['components'] ?? [];

        // Extract building/location name
        $building = $components['building']
            ?? $components['house']
            ?? $components['amenity']
            ?? $components['shop']
            ?? $components['office']
            ?? $components['tourism']
            ?? $components['leisure']
            ?? $components['commercial']
            ?? $components['industrial']
            ?? null;

        // Extract street with house number if available
        $houseNumber = $components['house_number'] ?? null;
        $road = $components['road']
            ?? $components['pedestrian']
            ?? $components['footway']
            ?? $components['street']
            ?? $components['path']
            ?? null;

        $street = $road;
        if ($houseNumber && $road) {
            $street = "{$road} No.{$houseNumber}";
        }

        // Extract neighborhood/district
        $neighborhood = $components['neighbourhood']
            ?? $components['suburb']
            ?? $components['quarter']
            ?? $components['residential']
            ?? null;

        // Extract subdistrict (kelurahan)
        $subdistrict = $components['village']
            ?? $components['subdistrict']
            ?? null;

        // Extract district (kecamatan)
        $district = $components['city_district']
            ?? $components['district']
            ?? $components['county']
            ?? null;

        // Extract city
        $city = $components['city']
            ?? $components['town']
            ?? $components['municipality']
            ?? $components['state_district']
            ?? null;

        // Build address parts in order of specificity
        $parts = array_filter([
            $building,
            $street,
            $neighborhood,
            $subdistrict,
            $district,
            $city,
        ], fn($part) => !empty($part));

        // Remove duplicates while preserving order
        $parts = array_values(array_unique($parts));

        if (empty($parts)) {
            // Fallback to formatted address from OpenCage
            return $result['formatted'] ?? 'Unknown Location';
        }

        return implode(', ', $parts);
    }

    /**
     * Reverse geocode using Google Maps Geocoding API.
     *
     * @param float $latitude
     * @param float $longitude
     * @return string
     * @throws \Exception
     */
    protected function reverseGeocodeWithGoogle(float $latitude, float $longitude): string
    {
        if (empty($this->googleApiKey)) {
            throw new \Exception('Google Maps API key not configured');
        }

        $response = Http::timeout(10)
            ->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => "{$latitude},{$longitude}",
                'key' => $this->googleApiKey,
                'result_type' => 'street_address|premise|subpremise|point_of_interest',
            ]);

        if (!$response->successful()) {
            throw new \Exception('Google Maps API request failed: ' . $response->status());
        }

        $data = $response->json();

        if ($data['status'] !== 'OK') {
            throw new \Exception('Google Maps API error: ' . ($data['error_message'] ?? $data['status']));
        }

        return $this->formatGoogleAddress($data);
    }

    /**
     * Format address from Nominatim response.
     *
     * @param array $data
     * @return string
     */
    protected function formatNominatimAddress(array $data): string
    {
        $address = $data['address'] ?? [];

        // Extract building/location name
        $building = $address['building']
            ?? $address['amenity']
            ?? $address['shop']
            ?? $address['office']
            ?? $address['tourism']
            ?? $address['leisure']
            ?? $address['commercial']
            ?? $address['industrial']
            ?? null;

        // Extract street with house number if available
        $houseNumber = $address['house_number'] ?? null;
        $road = $address['road']
            ?? $address['pedestrian']
            ?? $address['footway']
            ?? $address['street']
            ?? null;

        $street = $road;
        if ($houseNumber && $road) {
            $street = "{$road} No.{$houseNumber}";
        }

        // Extract neighborhood/district
        $neighborhood = $address['neighbourhood']
            ?? $address['suburb']
            ?? $address['quarter']
            ?? $address['residential']
            ?? null;

        // Extract subdistrict (kelurahan)
        $subdistrict = $address['village']
            ?? $address['subdistrict']
            ?? null;

        // Extract district (kecamatan)
        $district = $address['city_district']
            ?? $address['district']
            ?? $address['county']
            ?? null;

        // Extract city
        $city = $address['city']
            ?? $address['town']
            ?? $address['municipality']
            ?? null;

        // Build address parts in order of specificity
        $parts = array_filter([
            $building,
            $street,
            $neighborhood,
            $subdistrict,
            $district,
            $city,
        ], fn($part) => !empty($part));

        // Remove duplicates while preserving order
        $parts = array_values(array_unique($parts));

        if (empty($parts)) {
            // Fallback to display_name from Nominatim
            return $data['display_name'] ?? 'Unknown Location';
        }

        return implode(', ', $parts);
    }

    /**
     * Format address from Google Maps response.
     *
     * @param array $data
     * @return string
     */
    protected function formatGoogleAddress(array $data): string
    {
        $result = $data['results'][0] ?? null;

        if (!$result) {
            return 'Unknown Location';
        }

        $components = collect($result['address_components'] ?? []);

        // Extract building/location name (premise or point of interest)
        $building = $components->first(fn($c) =>
            in_array('premise', $c['types']) ||
            in_array('point_of_interest', $c['types']) ||
            in_array('establishment', $c['types'])
        )['long_name'] ?? null;

        // Extract street
        $street = $components->first(fn($c) =>
            in_array('route', $c['types'])
        )['long_name'] ?? null;

        // Extract city
        $city = $components->first(fn($c) =>
            in_array('locality', $c['types']) ||
            in_array('administrative_area_level_2', $c['types'])
        )['long_name'] ?? null;

        return $this->formatAddressParts($building, $street, $city);
    }

    /**
     * Format address parts into a readable string.
     *
     * @param string|null $building
     * @param string|null $street
     * @param string|null $city
     * @return string
     */
    protected function formatAddressParts(?string $building, ?string $street, ?string $city): string
    {
        $parts = array_filter([$building, $street, $city], fn($part) => !empty($part));

        if (empty($parts)) {
            return 'Unknown Location';
        }

        return implode(', ', $parts);
    }

    /**
     * Generate a fallback address string when geocoding fails.
     *
     * @param float $latitude
     * @param float $longitude
     * @return string
     */
    protected function formatFallbackAddress(float $latitude, float $longitude): string
    {
        return sprintf('Location (%.6f, %.6f)', $latitude, $longitude);
    }

    /**
     * Validate GPS coordinates are within valid ranges.
     *
     * @param float $latitude
     * @param float $longitude
     * @return bool
     */
    public function validateCoordinates(float $latitude, float $longitude): bool
    {
        return $latitude >= -90 && $latitude <= 90
            && $longitude >= -180 && $longitude <= 180;
    }

    /**
     * Generate cache key for coordinates.
     *
     * @param float $latitude
     * @param float $longitude
     * @return string
     */
    protected function getCacheKey(float $latitude, float $longitude): string
    {
        // Round to 5 decimal places (~1.1m precision) for cache key
        $lat = round($latitude, 5);
        $lng = round($longitude, 5);

        return "geocode:{$lat}:{$lng}";
    }

    /**
     * Set the geocoding provider.
     *
     * @param string $provider 'opencage', 'nominatim', or 'google'
     * @return self
     */
    public function setProvider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    /**
     * Set the OpenCage API key.
     *
     * @param string $apiKey
     * @return self
     */
    public function setOpenCageApiKey(string $apiKey): self
    {
        $this->opencageApiKey = $apiKey;
        return $this;
    }

    /**
     * Set the Google API key.
     *
     * @param string $apiKey
     * @return self
     */
    public function setGoogleApiKey(string $apiKey): self
    {
        $this->googleApiKey = $apiKey;
        return $this;
    }
}
