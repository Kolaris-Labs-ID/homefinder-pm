<?php

use App\Filament\Resources\WeeklyAllowanceBatchResource;
use App\Filament\Resources\WeeklyAllowanceBatchResource\Pages\CreateWeeklyAllowanceBatch;
use App\Filament\Resources\WeeklyAllowanceBatchResource\Pages\EditWeeklyAllowanceBatch;
use App\Filament\Resources\WeeklyAllowanceBatchResource\Pages\ListWeeklyAllowanceBatches;
use App\Filament\Resources\WeeklyAllowanceBatchResource\Pages\ViewWeeklyAllowanceBatch;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->actingAs($this->admin);
});

describe('WeeklyAllowanceBatchResource Pages', function () {
    it('can render list page', function () {
        Livewire::test(ListWeeklyAllowanceBatches::class)
            ->assertSuccessful();
    });

    it('can render create page', function () {
        Livewire::test(CreateWeeklyAllowanceBatch::class)
            ->assertSuccessful();
    });
});

describe('WeeklyAllowanceBatchResource Table', function () {
    it('can list batches', function () {
        $batch = WeeklyAllowanceBatch::create([
            'week_start_date' => '2026-01-20',
            'week_end_date' => '2026-01-24',
            'payment_date' => '2026-01-28',
            'status' => 'draft',
        ]);

        Livewire::test(ListWeeklyAllowanceBatches::class)
            ->assertCanSeeTableRecords([$batch]);
    });

    it('can filter by status', function () {
        $draftBatch = WeeklyAllowanceBatch::create([
            'week_start_date' => '2026-01-20',
            'week_end_date' => '2026-01-24',
            'payment_date' => '2026-01-28',
            'status' => 'draft',
        ]);

        $approvedBatch = WeeklyAllowanceBatch::create([
            'week_start_date' => '2026-01-13',
            'week_end_date' => '2026-01-17',
            'payment_date' => '2026-01-21',
            'status' => 'approved',
        ]);

        Livewire::test(ListWeeklyAllowanceBatches::class)
            ->filterTable('status', 'draft')
            ->assertCanSeeTableRecords([$draftBatch])
            ->assertCanNotSeeTableRecords([$approvedBatch]);
    });
});

describe('WeeklyAllowanceBatchResource View', function () {
    it('can view batch details', function () {
        $batch = WeeklyAllowanceBatch::create([
            'week_start_date' => '2026-01-20',
            'week_end_date' => '2026-01-24',
            'payment_date' => '2026-01-28',
            'status' => 'draft',
            'total_employees' => 10,
            'total_amount' => 3500000,
        ]);

        Livewire::test(ViewWeeklyAllowanceBatch::class, ['record' => $batch->id])
            ->assertSuccessful();
    });
});

describe('WeeklyAllowanceBatchResource Edit', function () {
    it('can edit draft batch', function () {
        $batch = WeeklyAllowanceBatch::create([
            'week_start_date' => '2026-01-20',
            'week_end_date' => '2026-01-24',
            'payment_date' => '2026-01-28',
            'status' => 'draft',
        ]);

        Livewire::test(EditWeeklyAllowanceBatch::class, ['record' => $batch->id])
            ->assertSuccessful();
    });
});
