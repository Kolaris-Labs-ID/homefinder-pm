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
            'ticketUpdates' => false,
            'activityUpdates' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function updating_notification_settings_persists_ticket_and_activity_updates()
    {
        Sanctum::actingAs($this->user);

        $this->putJson('/api/hris/notifications/settings', [
            'enabled' => true,
            'checkInReminder' => true,
            'checkOutReminder' => false,
            'breakReminder' => true,
            'announcements' => true,
            'leaveUpdates' => true,
            'reimbursementUpdates' => true,
            'payslipNotifications' => true,
            'ticketUpdates' => false,
            'activityUpdates' => false,
        ])->assertStatus(200);

        $this->assertDatabaseHas('hris_notification_settings', [
            'employee_id' => $this->employee->id,
            'check_out_reminder' => false,
            'ticket_updates' => false,
            'activity_updates' => false,
        ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_notification_endpoints()
    {
        $response = $this->getJson('/api/hris/notifications');

        $response->assertStatus(401);
    }
}
