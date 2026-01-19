<?php

namespace Tests\Feature\Api\Hris;

use App\Models\Hris\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeamApiTest extends TestCase
{
    use RefreshDatabase;

    public $user;
    public Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->employee = Employee::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function authenticated_user_can_get_team_members()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/hris/team');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'summary',
            ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_team_endpoint()
    {
        $response = $this->getJson('/api/hris/team');

        $response->assertStatus(401);
    }
}
