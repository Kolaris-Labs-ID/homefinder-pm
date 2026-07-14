<?php

namespace App\Observers\Hris;

use App\Exceptions\Hris\PayrollLockedException;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\SalaryStructure;

class SalaryStructureGuardObserver
{
    public function saving(SalaryStructure $structure): void
    {
        $dates = array_filter([
            $structure->effective_date,
            $structure->getOriginal('effective_date'),
        ]);
        foreach ($dates as $date) {
            $this->assertNotLocked($date);
        }
    }

    public function deleting(SalaryStructure $structure): void
    {
        $this->assertNotLocked($structure->effective_date);
    }

    public function saved(SalaryStructure $structure): void
    {
        $this->markIntersectingDraftsStale($structure->effective_date);
    }

    public function deleted(SalaryStructure $structure): void
    {
        $this->markIntersectingDraftsStale($structure->effective_date);
    }

    protected function assertNotLocked($effectiveDate): void
    {
        $date = \Illuminate\Support\Carbon::parse($effectiveDate)->toDateString();

        $locked = PayrunBatch::query()
            ->whereIn('status', [PayrunBatch::STATUS_PENDING_FINANCE, PayrunBatch::STATUS_PAID])
            ->whereDate('period_start_date', '<=', $date)
            ->whereDate('period_end_date', '>=', $date)
            ->first();

        if ($locked) {
            throw new PayrollLockedException(
                "Salary effective date {$date} intersects locked/paid payroll period ".
                "{$locked->period_start_date->toDateString()}..{$locked->period_end_date->toDateString()} (batch {$locked->id})."
            );
        }
    }

    protected function markIntersectingDraftsStale($effectiveDate): void
    {
        $date = \Illuminate\Support\Carbon::parse($effectiveDate)->toDateString();

        PayrunBatch::query()
            ->where('status', PayrunBatch::STATUS_CALCULATED)
            ->whereDate('period_start_date', '<=', $date)
            ->whereDate('period_end_date', '>=', $date)
            ->get()
            ->each(fn (PayrunBatch $b) => $b->update([
                'status' => PayrunBatch::STATUS_DRAFT,
                'calculated_at' => null,
            ]));
    }
}
