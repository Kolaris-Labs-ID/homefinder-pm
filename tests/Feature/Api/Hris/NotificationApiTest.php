<?php

namespace Tests\Feature\Api\Hris;

use App\Models\Hris\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationApiTest extends TestCase
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
    public function authenticated_user_can_get_notification_settings()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/hris/notifications/settings');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    #[Test]
    public function authenticated_user_can_update_notification_settings()
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/hris/notifications/settings', [
            'enabled' => true,
            'checkInReminder' => false,
            'checkOutReminder' => true,
            'breakReminder' => false,
            'announcements' => true,
            'leaveUpdates' => true,
            'reimbursementUpdates' => false,
            'payslipNotifications' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_notification_endpoints()
    {
        $response = $this->getJson('/api/hris/notifications');

        $response->assertStatus(401);
    }
}
