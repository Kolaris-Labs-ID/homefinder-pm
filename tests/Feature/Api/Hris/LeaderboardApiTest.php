<?php

namespace Tests\Feature\Api\Hris;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeaderboardApiTest extends TestCase
{
    use RefreshDatabase;

    private User $actingUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingUser = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function unauthenticated_user_cannot_access_leaderboard(): void
    {
        $this->getJson('/api/hris/leaderboard')
            ->assertStatus(401);
    }

    #[Test]
    public function authenticated_user_gets_leaderboard_json_shape(): void
    {
        Sanctum::actingAs($this->actingUser);

        $response = $this->getJson('/api/hris/leaderboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'time_range',
                    'time_range_label',
                    'limit',
                    'period_start',
                    'period_end',
                ],
            ]);

        $response->assertJsonPath('meta.time_range', '7days');
        $response->assertJsonPath('meta.limit', 10);
        $this->assertIsArray($response->json('data'));
    }

    #[Test]
    public function invalid_time_range_returns_422(): void
    {
        Sanctum::actingAs($this->actingUser);

        $this->getJson('/api/hris/leaderboard?time_range=invalid')
            ->assertStatus(422);
    }

    #[Test]
    public function invalid_limit_returns_422(): void
    {
        Sanctum::actingAs($this->actingUser);

        $this->getJson('/api/hris/leaderboard?limit=0')
            ->assertStatus(422);

        $this->getJson('/api/hris/leaderboard?limit=51')
            ->assertStatus(422);
    }

    #[Test]
    public function query_params_are_applied(): void
    {
        Sanctum::actingAs($this->actingUser);

        $this->getJson('/api/hris/leaderboard?time_range=30days&limit=5')
            ->assertStatus(200)
            ->assertJsonPath('meta.time_range', '30days')
            ->assertJsonPath('meta.limit', 5)
            ->assertJsonPath('meta.time_range_label', 'Last 30 Days');
    }

    #[Test]
    public function user_with_recent_ticket_appears_on_leaderboard(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 14:00:00', config('app.timezone')));

        Sanctum::actingAs($this->actingUser);

        Ticket::factory()->create([
            'created_by' => $this->actingUser->id,
        ]);

        $response = $this->getJson('/api/hris/leaderboard');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $first = $data[0];
        $this->assertSame(1, $first['rank']);
        $this->assertArrayHasKey('total_score', $first);
        $this->assertArrayHasKey('stats', $first);
        $this->assertSame($this->actingUser->id, $first['user']['id']);
        $this->assertGreaterThanOrEqual(2, $first['total_score']);
        $this->assertGreaterThanOrEqual(1, $first['stats']['tickets_created']);
        $this->assertArrayHasKey('avatar_url', $first['user']);
        $this->assertArrayHasKey('avatar_initials', $first['user']);
    }
}
