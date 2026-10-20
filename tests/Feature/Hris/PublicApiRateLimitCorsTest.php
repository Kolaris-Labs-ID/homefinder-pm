<?php

use App\Models\Hris\JobPosting;

beforeEach(function () {
    $this->withHeaders(['Accept' => 'application/json']);
    JobPosting::factory()->publicPortal()->create(['slug' => 'rate-job']);
});

it('rate limits the apply endpoint after 5 requests per minute', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post('/api/public/v1/jobs/rate-job/apply', []);
    }

    $res = $this->post('/api/public/v1/jobs/rate-job/apply', []);

    $res->assertStatus(429);
});

it('allows browsing within the moderate jobs limit', function () {
    $res = $this->getJson('/api/public/v1/jobs');
    $res->assertOk();
    // Throttle headers are present on rate-limited routes.
    expect($res->headers->has('X-RateLimit-Limit'))->toBeTrue();
});

it('echoes the allow-origin header for the configured local frontend origin', function () {
    $res = $this->getJson('/api/public/v1/jobs', ['Origin' => 'http://localhost:3000']);

    $res->assertOk();
    expect($res->headers->get('Access-Control-Allow-Origin'))->toBe('http://localhost:3000');
});
