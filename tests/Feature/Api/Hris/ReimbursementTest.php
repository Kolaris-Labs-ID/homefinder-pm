<?php

use App\Models\Hris\Employee;
use App\Models\Hris\Reimbursement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

describe('Reimbursement create endpoint', function () {
    it('creates a new reimbursement request', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        $response = postJson('/api/hris/reimbursements', [
            'type' => Reimbursement::TYPE_MEDICAL,
            'amount' => 500000,
            'description' => 'Doctor consultation',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'type',
                    'amount',
                    'status',
                ],
            ])
            ->assertJsonPath('data.type', Reimbursement::TYPE_MEDICAL)
            ->assertJsonPath('data.amount', '500000.00')
            ->assertJsonPath('data.status', Reimbursement::STATUS_PENDING);

        // Verify reimbursement was created in database
        $reimbursement = Reimbursement::find($response->json('data.id'));
        expect($reimbursement)->not->toBeNull();
        expect($reimbursement->employee_id)->toBe($employee->id);
        expect($reimbursement->type)->toBe(Reimbursement::TYPE_MEDICAL);
        expect((float) $reimbursement->amount)->toBe(500000.0);
        expect($reimbursement->status)->toBe(Reimbursement::STATUS_PENDING);
    });

    it('creates reimbursement with all types', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        $types = [
            Reimbursement::TYPE_MEDICAL,
            Reimbursement::TYPE_GLASSES,
            Reimbursement::TYPE_TRANSPORT,
            Reimbursement::TYPE_OTHER,
        ];

        foreach ($types as $type) {
            $response = postJson('/api/hris/reimbursements', [
                'type' => $type,
                'amount' => 250000,
                'description' => "Test {$type}",
            ]);

            $response->assertCreated()
                ->assertJsonPath('data.type', $type);
        }

        // Verify all were created
        $count = Reimbursement::where('employee_id', $employee->id)->count();
        expect($count)->toBe(4);
    });

    it('creates reimbursement with optional description', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        $response = postJson('/api/hris/reimbursements', [
            'type' => Reimbursement::TYPE_TRANSPORT,
            'amount' => 100000,
        ]);

        $response->assertCreated();

        $reimbursement = Reimbursement::find($response->json('data.id'));
        expect($reimbursement->description)->toBeNull();
    });

    it('validates required fields', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        // Missing type
        $response = postJson('/api/hris/reimbursements', [
            'amount' => 500000,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('type');

        // Missing amount
        $response = postJson('/api/hris/reimbursements', [
            'type' => Reimbursement::TYPE_MEDICAL,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    });

    it('validates amount is numeric and positive', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        // Non-numeric amount
        $response = postJson('/api/hris/reimbursements', [
            'type' => Reimbursement::TYPE_MEDICAL,
            'amount' => 'invalid',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        // Zero amount
        $response = postJson('/api/hris/reimbursements', [
            'type' => Reimbursement::TYPE_MEDICAL,
            'amount' => 0,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        // Negative amount
        $response = postJson('/api/hris/reimbursements', [
            'type' => Reimbursement::TYPE_MEDICAL,
            'amount' => -100000,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    });

    it('validates type is valid', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        $response = postJson('/api/hris/reimbursements', [
            'type' => 'invalid_type',
            'amount' => 500000,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    });

    it('validates receipt file format', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        // Invalid file type
        $file = UploadedFile::fake()->create('receipt.txt', 100);

        $response = postJson('/api/hris/reimbursements', [
            'type' => Reimbursement::TYPE_MEDICAL,
            'amount' => 500000,
            'receipt' => $file,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('receipt');
    });

    it('validates receipt file size', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        // File too large (> 5MB)
        $file = UploadedFile::fake()->image('receipt.jpg')->size(6000);

        $response = postJson('/api/hris/reimbursements', [
            'type' => Reimbursement::TYPE_MEDICAL,
            'amount' => 500000,
            'receipt' => $file,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('receipt');
    });

    it('requires authentication', function () {
        $response = postJson('/api/hris/reimbursements', [
            'type' => Reimbursement::TYPE_MEDICAL,
            'amount' => 500000,
        ]);

        $response->assertUnauthorized();
    });

    it('returns 404 when user has no employee profile', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = postJson('/api/hris/reimbursements', [
            'type' => Reimbursement::TYPE_MEDICAL,
            'amount' => 500000,
        ]);

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

describe('Reimbursement API integration', function () {
    it('creates and lists reimbursement', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        // Create reimbursement
        $createResponse = postJson('/api/hris/reimbursements', [
            'type' => Reimbursement::TYPE_MEDICAL,
            'amount' => 500000,
            'description' => 'Doctor visit',
        ]);

        $createResponse->assertCreated();
        $reimbursementId = $createResponse->json('data.id');

        // List reimbursements
        $listResponse = getJson('/api/hris/reimbursements');

        $listResponse->assertOk()
            ->assertJsonCount(1, 'data');

        // Verify created reimbursement is in list
        $ids = collect($listResponse->json('data'))->pluck('id')->toArray();
        expect(in_array($reimbursementId, $ids))->toBeTrue();
    });

    it('creates and cancels reimbursement', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        // Create reimbursement
        $createResponse = postJson('/api/hris/reimbursements', [
            'type' => Reimbursement::TYPE_TRANSPORT,
            'amount' => 250000,
        ]);

        $createResponse->assertCreated();
        $reimbursementId = $createResponse->json('data.id');

        // Cancel reimbursement
        $cancelResponse = putJson("/api/hris/reimbursements/{$reimbursementId}/cancel");

        $cancelResponse->assertOk();

        // Verify reimbursement is no longer in list
        $listResponse = getJson('/api/hris/reimbursements');

        $listResponse->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('handles multiple reimbursements workflow', function () {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        // Create multiple reimbursements
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $response = postJson('/api/hris/reimbursements', [
                'type' => Reimbursement::TYPE_MEDICAL,
                'amount' => 100000 * ($i + 1),
                'description' => "Reimbursement {$i}",
            ]);

            $response->assertCreated();
            $ids[] = $response->json('data.id');
        }

        // List all reimbursements
        $listResponse = getJson('/api/hris/reimbursements');

        $listResponse->assertOk()
            ->assertJsonCount(3, 'data');

        // Cancel one reimbursement
        $cancelResponse = putJson("/api/hris/reimbursements/{$ids[0]}/cancel");

        $cancelResponse->assertOk();

        // Verify only 2 remain
        $listResponse = getJson('/api/hris/reimbursements');

        $listResponse->assertOk()
            ->assertJsonCount(2, 'data');

        // Verify correct ones remain
        $remainingIds = collect($listResponse->json('data'))->pluck('id')->toArray();
        expect(in_array($ids[1], $remainingIds))->toBeTrue();
        expect(in_array($ids[2], $remainingIds))->toBeTrue();
        expect(in_array($ids[0], $remainingIds))->toBeFalse();
    });
});
