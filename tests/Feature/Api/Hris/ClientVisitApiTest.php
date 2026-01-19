<?php

namespace Tests\Feature\Api\Hris;

use App\Models\Hris\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientVisitApiTest extends TestCase
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
    public function authenticated_user_can_get_visit_purposes()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/hris/visits/purposes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'value',
                        'label',
                    ],
                ],
            ]);
    }

    #[Test]
    public function authenticated_user_can_get_visits()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/hris/visits');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    #[Test]
    public function authenticated_user_can_create_visit()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/hris/visits', [
            'client_name' => 'PT ABC Company',
            'client_address' => 'Jl. Sudirman No. 123, Jakarta',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'purpose' => 'tax_consultation',
            'visit_date' => now()->addDays(1)->toDateString(),
            'planned_start_time' => '09:00',
            'planned_end_time' => '11:00',
            'notes' => 'Discuss annual tax filing',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'client_name',
                    'client_address',
                    'purpose',
                    'visit_date',
                    'status',
                    'created_at',
                ],
            ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_visit_endpoints()
    {
        $response = $this->getJson('/api/hris/visits');

        $response->assertStatus(401);
    }
}
