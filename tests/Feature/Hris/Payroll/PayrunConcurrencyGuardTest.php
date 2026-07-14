<?php

use App\Models\Hris\PayrunBatch;
use App\Services\Hris\PayrollCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('rejects a concurrent calculate while the batch lock is held', function () {
    $batch = PayrunBatch::factory()->create(['status' => PayrunBatch::STATUS_DRAFT]);
    $lock = Cache::lock("payrun:calculate:{$batch->id}", 30);
    expect($lock->get())->toBeTrue();

    expect(fn () => app(PayrollCalculationService::class)->calculatePayrun($batch))
        ->toThrow(\RuntimeException::class);

    $lock->release();
});

it('re-checks batch status inside the lock and refuses a non-draft batch', function () {
    $batch = PayrunBatch::factory()->create(['status' => PayrunBatch::STATUS_PENDING_FINANCE]);
    expect(fn () => app(PayrollCalculationService::class)->calculatePayrun($batch))
        ->toThrow(\Exception::class);
});
