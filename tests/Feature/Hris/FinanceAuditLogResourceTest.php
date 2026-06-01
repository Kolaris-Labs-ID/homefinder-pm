<?php

use App\Filament\Resources\FinanceAuditLogResource;
use App\Filament\Resources\FinanceAuditLogResource\Pages\ListFinanceAuditLogs;
use App\Filament\Resources\FinanceAuditLogResource\Pages\ViewFinanceAuditLog;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\FinanceAuditLogger;
use App\Services\Hris\PaymentRequestService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(FinanceRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->admin = User::factory()->create();
    $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->admin->assignRole($role);
});

// --- Access gating ---

it('grants canViewAny to a user with finance_center.view', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole('finance_reviewer');
    \Pest\Laravel\actingAs($reviewer);

    expect(FinanceAuditLogResource::canViewAny())->toBeTrue();
});

it('denies canViewAny to a plain user without finance permissions', function () {
    $plain = User::factory()->create();
    \Pest\Laravel\actingAs($plain);

    expect(FinanceAuditLogResource::canViewAny())->toBeFalse();
});

it('canCreate returns false always', function () {
    expect(FinanceAuditLogResource::canCreate())->toBeFalse();
});

it('canEdit returns false', function () {
    expect(FinanceAuditLogResource::canEdit(null))->toBeFalse();
});

it('canDelete returns false', function () {
    expect(FinanceAuditLogResource::canDelete(null))->toBeFalse();
});

// --- Livewire render tests ---

function auditViewMakePr(User $actor): PaymentRequest
{
    return app(PaymentRequestService::class)->createManual(
        $actor,
        ['payee_name' => 'AuditVendor', 'amount' => 100, 'currency' => 'IDR']
    );
}

it('ListFinanceAuditLogs page renders as super_admin', function () {
    $actor = User::factory()->create();
    $pr = auditViewMakePr($actor);
    app(FinanceAuditLogger::class)->log($pr, 'test.render_list', $actor);
    app(FinanceAuditLogger::class)->log($pr, 'test.render_list_2', $actor);

    Livewire::actingAs($this->admin)
        ->test(ListFinanceAuditLogs::class)
        ->assertOk();
});

it('ViewFinanceAuditLog page renders for a log entry', function () {
    $actor = User::factory()->create();
    $pr = auditViewMakePr($actor);
    $log = app(FinanceAuditLogger::class)->log($pr, 'test.render_view', $actor);

    Livewire::actingAs($this->admin)
        ->test(ViewFinanceAuditLog::class, ['record' => $log->getRouteKey()])
        ->assertOk();
});

it('ViewFinanceAuditLog renders with before/after snapshots', function () {
    $actor = User::factory()->create();
    $pr = auditViewMakePr($actor);
    $log = app(FinanceAuditLogger::class)->log($pr, 'test.snapshots', $actor, [
        'before' => ['status' => 'draft'],
        'after' => ['status' => 'submitted'],
    ]);

    Livewire::actingAs($this->admin)
        ->test(ViewFinanceAuditLog::class, ['record' => $log->getRouteKey()])
        ->assertOk();
});
