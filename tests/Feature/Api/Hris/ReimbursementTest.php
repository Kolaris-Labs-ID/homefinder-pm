<?php

use App\Models\Hris\Employee;
use App\Models\Hris\Reimbursement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

describe('Reimbursement list endpoint', function () {
    it('returns list of employee reimbursements', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        // Create multiple reimbursements for this employee
        Reimbursement::factory(3)->create([
            'employee_id' => $employee->id,
        ]);

        $response = getJson('/api/hris/reimbursements');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'amount',
                        'description',
                        'status',
                        'rejection_reason',
                        'paid_at',
                        'created_at',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data');
    });

    it('returns empty list when employee has no reimbursements', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        $response = getJson('/api/hris/reimbursements');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('returns only employee own reimbursements', function () {
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();
        Sanctum::actingAs($employee1->user);

        // Create reimbursements for both employees
        Reimbursement::factory(2)->create(['employee_id' => $employee1->id]);
        Reimbursement::factory(3)->create(['employee_id' => $employee2->id]);

        $response = getJson('/api/hris/reimbursements');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        // Verify all returned reimbursements belong to employee1
        $returnedIds = collect($response->json('data'))->pluck('id')->toArray();
        $employee1ReimbursementIds = Reimbursement::where('employee_id', $employee1->id)
            ->pluck('id')
            ->toArray();

        foreach ($returnedIds as $id) {
            expect(in_array($id, $employee1ReimbursementIds))->toBeTrue();
        }
    });

    it('returns reimbursements ordered by most recent first', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        // Create reimbursements with delays to ensure different timestamps
        $reimbursement1 = Reimbursement::factory()->create(['employee_id' => $employee->id]);
        sleep(1);
        $reimbursement2 = Reimbursement::factory()->create(['employee_id' => $employee->id]);
        sleep(1);
        $reimbursement3 = Reimbursement::factory()->create(['employee_id' => $employee->id]);

        $response = getJson('/api/hris/reimbursements');

        $response->assertOk()
            ->assertJsonCount(3, 'data');

        // Should be ordered by most recent first
        $returnedIds = collect($response->json('data'))->pluck('id')->toArray();
        expect($returnedIds[0])->toBe($reimbursement3->id);
        expect($returnedIds[1])->toBe($reimbursement2->id);
        expect($returnedIds[2])->toBe($reimbursement1->id);
    });

    it('returns reimbursements with all statuses', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        // Create reimbursements with different statuses
        Reimbursement::factory()->pending()->create(['employee_id' => $employee->id]);
        Reimbursement::factory()->approved()->create(['employee_id' => $employee->id]);
        Reimbursement::factory()->rejected()->create(['employee_id' => $employee->id]);
        Reimbursement::factory()->paid()->create(['employee_id' => $employee->id]);

        $response = getJson('/api/hris/reimbursements');

        $response->assertOk()
            ->assertJsonCount(4, 'data');

        $statuses = collect($response->json('data'))->pluck('status')->toArray();
        expect($statuses)->toContain('pending');
        expect($statuses)->toContain('approved');
        expect($statuses)->toContain('rejected');
        expect($statuses)->toContain('paid');
    });

    it('requires authentication', function () {
        $response = getJson('/api/hris/reimbursements');

        $response->assertUnauthorized();
    });

    it('returns 404 when user has no employee profile', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = getJson('/api/hris/reimbursements');

        $response->assertNotFound()
            ->assertJsonPath('message', 'Employee profile not found');
    });
});

describe('Reimbursement cancel endpoint', function () {
    it('cancels a pending reimbursement', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        $reimbursement = Reimbursement::factory()->pending()->create([
            'employee_id' => $employee->id,
        ]);

        $response = putJson("/api/hris/reimbursements/{$reimbursement->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('message', 'Reimbursement cancelled successfully');

        // Verify reimbursement was deleted
        expect(Reimbursement::find($reimbursement->id))->toBeNull();
    });

    it('prevents cancelling approved reimbursement', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        $reimbursement = Reimbursement::factory()->approved()->create([
            'employee_id' => $employee->id,
        ]);

        $response = putJson("/api/hris/reimbursements/{$reimbursement->id}/cancel");

        $response->assertBadRequest()
            ->assertJsonPath('message', "Cannot cancel reimbursement with status: {$reimbursement->status}");

        // Verify reimbursement still exists
        expect(Reimbursement::find($reimbursement->id))->not->toBeNull();
    });

    it('prevents cancelling rejected reimbursement', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        $reimbursement = Reimbursement::factory()->rejected()->create([
            'employee_id' => $employee->id,
        ]);

        $response = putJson("/api/hris/reimbursements/{$reimbursement->id}/cancel");

        $response->assertBadRequest();

        // Verify reimbursement still exists
        expect(Reimbursement::find($reimbursement->id))->not->toBeNull();
    });

    it('prevents cancelling paid reimbursement', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        $reimbursement = Reimbursement::factory()->paid()->create([
            'employee_id' => $employee->id,
        ]);

        $response = putJson("/api/hris/reimbursements/{$reimbursement->id}/cancel");

        $response->assertBadRequest();

        // Verify reimbursement still exists
        expect(Reimbursement::find($reimbursement->id))->not->toBeNull();
    });

    it('prevents cancelling other employee reimbursement', function () {
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();
        Sanctum::actingAs($employee1->user);

        $reimbursement = Reimbursement::factory()->pending()->create([
            'employee_id' => $employee2->id,
        ]);

        $response = putJson("/api/hris/reimbursements/{$reimbursement->id}/cancel");

        $response->assertForbidden()
            ->assertJsonPath('message', 'Unauthorized');

        // Verify reimbursement still exists
        expect(Reimbursement::find($reimbursement->id))->not->toBeNull();
    });

    it('requires authentication', function () {
        $reimbursement = Reimbursement::factory()->pending()->create();

        $response = putJson("/api/hris/reimbursements/{$reimbursement->id}/cancel");

        $response->assertUnauthorized();
    });

    it('returns 404 when user has no employee profile', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $reimbursement = Reimbursement::factory()->pending()->create();

        $response = putJson("/api/hris/reimbursements/{$reimbursement->id}/cancel");

        $response->assertNotFound()
            ->assertJsonPath('message', 'Employee profile not found');
    });

    it('returns 404 when reimbursement does not exist', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        $response = putJson('/api/hris/reimbursements/99999/cancel');

        $response->assertNotFound();
    });
});

describe('Reimbursement create endpoint (retired)', function () {
    it('returns 405: reimbursement creation is retired in favor of expense requests', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        postJson('/api/hris/reimbursements', [
            'type' => Reimbursement::TYPE_MEDICAL,
            'amount' => 500000,
        ])->assertMethodNotAllowed();
    });
});
