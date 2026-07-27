<?php

namespace App\Services\Hris;

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\ClientVisit;
use App\Models\Hris\Employee;
use App\Models\Hris\LateNotice;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\Hris\WeeklyAllowancePayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WeeklyAllowanceService
{
    protected AttendanceService $attendanceService;

    protected CompensationResolver $compensationResolver;

    public function __construct(
        ?AttendanceService $attendanceService = null,
        ?CompensationResolver $compensationResolver = null
    ) {
        $this->attendanceService = $attendanceService ?? app(AttendanceService::class);
        $this->compensationResolver = $compensationResolver ?? app(CompensationResolver::class);
    }

    /**
     * Generate a weekly allowance batch for the given week.
     *
     * @param  Carbon  $weekStart  Monday of the week
     * @param  Carbon  $weekEnd  Friday of the week
     * @param  Carbon  $paymentDate  Payment date (Tuesday)
     * @param  User|null  $generatedBy  User who generated the batch
     */
    public function generateBatch(
        Carbon $weekStart,
        Carbon $weekEnd,
        Carbon $paymentDate,
        ?User $generatedBy = null
    ): WeeklyAllowanceBatch {
        return DB::transaction(function () use ($weekStart, $weekEnd, $paymentDate, $generatedBy) {
            // Check if batch already exists for this week
            $existingBatch = WeeklyAllowanceBatch::forWeek($weekStart)->first();
            if ($existingBatch) {
                throw new \Exception("Batch already exists for week {$weekStart->format('d M Y')}");
            }

            // Create the batch
            $batch = WeeklyAllowanceBatch::create([
                'week_start_date' => $weekStart,
                'week_end_date' => $weekEnd,
                'payment_date' => $paymentDate,
                'status' => WeeklyAllowanceBatch::STATUS_DRAFT,
                'generated_by' => $generatedBy?->id ?? Auth::id(),
                'generated_at' => now(),
            ]);

            // Get all employees with weekly allowance enabled
            $employees = $this->getEligibleEmployees($weekEnd);

            $totalAmount = 0;
            $warningCount = 0;

            foreach ($employees as $employee) {
                $payment = $this->calculateEmployeePayment($employee, $weekStart, $weekEnd);

                if ($payment) {
                    $paymentRecord = WeeklyAllowancePayment::create([
                        'weekly_allowance_batch_id' => $batch->id,
                        'employee_id' => $employee->id,
                        'daily_rate' => $payment['daily_rate'],
                        'days_present' => $payment['days_present'],
                        'days_breakdown' => $payment['days_breakdown'],
                        'total_amount' => $payment['total_amount'],
                        'bank_name' => $employee->bank_name,
                        'bank_account_number' => $employee->bank_account_number,
                        'bank_account_holder' => $employee->bank_account_holder,
                        'has_bank_warning' => $payment['has_bank_warning'],
                        'status' => WeeklyAllowancePayment::STATUS_PENDING,
                    ]);

                    $totalAmount += $payment['total_amount'];
                    if ($payment['has_bank_warning']) {
                        $warningCount++;
                    }
                }
            }

            // Update batch summary and transition to calculated
            $batch->update([
                'total_employees' => $batch->payments()->count(),
                'employees_with_warnings' => $warningCount,
                'total_amount' => $totalAmount,
                'status' => WeeklyAllowanceBatch::STATUS_CALCULATED,
            ]);

            return $batch->fresh();
        });
    }

    /**
     * Get employees eligible for weekly allowance.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getEligibleEmployees(Carbon $asOfDate)
    {
        return Employee::where('is_active', true)
            ->whereHas('salaryStructures', function ($query) use ($asOfDate) {
                $query->where('weekly_allowance_enabled', true)
                    ->where('effective_date', '<=', $asOfDate)
                    ->orderBy('effective_date', 'desc');
            })
            ->get();
    }

    /**
     * Calculate weekly allowance payment for an employee.
     */
    public function calculateEmployeePayment(Employee $employee, Carbon $weekStart, Carbon $weekEnd): ?array
    {
        // Load all structures effective on/before week end once, newest first.
        // Eligibility + the fallback rate use the week-end structure; the per-day
        // rate is resolved from this same set so a mid-week change isn't retroactive.
        $salaryStructures = $this->compensationResolver->structuresAsOf($employee, $weekEnd);
        $salaryStructure = $salaryStructures->first();

        if (! $salaryStructure || ! $salaryStructure->weekly_allowance_enabled) {
            return null;
        }

        $dailyRate = (float) $salaryStructure->daily_allowance_rate;
        if ($dailyRate <= 0) {
            return null;
        }

        // Get attendance for the week (Mon-Fri) with per-day penalty info
        $daysBreakdown = $this->getDaysBreakdown($employee, $weekStart, $weekEnd);
        $daysPresent = $this->countPresentDays($daysBreakdown);

        // Calculate total applying late penalties per day, using the rate that
        // was actually effective on each day (so a mid-week rate change only
        // applies from its effective_date onward, not retroactively to the whole week).
        // Resolve using each day's real work date, which getDaysBreakdown records —
        // robust to any week_start_date the caller passes (a non-Monday start would
        // misalign an offset-based lookup).
        $totalAmount = 0.0;
        foreach ($daysBreakdown as &$day) {
            if (! $day['present']) {
                continue;
            }
            $dayStructure = isset($day['date'])
                ? $this->compensationResolver->resolveFrom($salaryStructures, Carbon::parse($day['date']))
                : null;
            // Days before any structure is effective fall back to the week-end rate.
            $day['daily_rate'] = $dayStructure ? (float) $dayStructure->daily_allowance_rate : $dailyRate;
            $penaltyPercent = $day['penalty_percent'] ?? 0;
            $totalAmount += $day['daily_rate'] * (1 - $penaltyPercent / 100);
        }
        unset($day);
        $totalAmount = round($totalAmount, 2);

        // Check bank info
        $hasBankWarning = empty($employee->bank_name)
            || empty($employee->bank_account_number)
            || empty($employee->bank_account_holder);

        return [
            'daily_rate' => $dailyRate,
            'days_present' => $daysPresent,
            'days_breakdown' => $daysBreakdown,
            'total_amount' => $totalAmount,
            'has_bank_warning' => $hasBankWarning,
        ];
    }

    /**
     * Get breakdown of which days employee was present, with late penalty info.
     *
     * Each entry is an array:
     *   ['present' => bool, 'late' => bool, 'has_late_notice' => bool, 'penalty_percent' => int]
     *
     * @return array<string, array>
     */
    public function getDaysBreakdown(Employee $employee, Carbon $weekStart, Carbon $weekEnd): array
    {
        $logs = AttendanceLog::with('clientVisit')
            ->forEmployee($employee->id)
            ->forDateRange($weekStart, $weekEnd)
            ->get()
            ->keyBy(fn ($log) => $log->date->format('Y-m-d'));

        $needsLateNotices = $logs->contains(
            fn (AttendanceLog $log) => $log->status === AttendanceLog::STATUS_VISIT
        );

        $lateNotices = $needsLateNotices
            ? LateNotice::forEmployee($employee->id)
                ->whereDate('date', '>=', $weekStart->toDateString())
                ->whereDate('date', '<=', $weekEnd->toDateString())
                ->get()
                ->keyBy(fn (LateNotice $notice) => $notice->date->format('Y-m-d'))
            : collect();

        $emptyDay = ['present' => false, 'late' => false, 'has_late_notice' => false, 'penalty_percent' => 0];

        $breakdown = [
            'mon' => $emptyDay,
            'tue' => $emptyDay,
            'wed' => $emptyDay,
            'thu' => $emptyDay,
            'fri' => $emptyDay,
        ];

        $dayMap = [
            1 => 'mon', // Monday
            2 => 'tue', // Tuesday
            3 => 'wed', // Wednesday
            4 => 'thu', // Thursday
            5 => 'fri', // Friday
        ];

        $currentDate = $weekStart->copy();
        while ($currentDate <= $weekEnd) {
            $dayOfWeek = $currentDate->dayOfWeek;

            // Skip weekends
            if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                $dateKey = $currentDate->format('Y-m-d');
                $log = $logs->get($dateKey);

                if ($log) {
                    $lateNotice = $lateNotices->get($dateKey);
                    $isVerifiedVisit = $this->isVerifiedClientVisitAttendance($log);

                    // Count as present if: present, late, quota-based remote
                    // work, or a client visit backed by real check-in evidence.
                    //
                    // Business rule:
                    // HALF_DAY intentionally does NOT receive Daily Allowance.
                    // This behavior is defined by HR policy, not by a technical limitation.
                    // If HR policy changes in the future, revisit this calculation together with
                    // Monthly Payroll and Overtime to keep compensation rules consistent.
                    $isPresent = $log->status === AttendanceLog::STATUS_VISIT
                        ? $isVerifiedVisit
                        : in_array($log->status, [
                            AttendanceLog::STATUS_PRESENT,
                            AttendanceLog::STATUS_LATE,
                        ], true)
                            || in_array($log->mode, AttendanceLog::getQuotaModes(), true);

                    $isLate = $log->status === AttendanceLog::STATUS_LATE
                        || ($isVerifiedVisit && $lateNotice !== null);

                    if (! $isPresent) {
                        $penaltyPercent = 0;
                    } elseif ($log->status === AttendanceLog::STATUS_LATE) {
                        $penaltyPercent = $this->attendanceService->calculateLatePenalty($log);
                    } elseif ($isVerifiedVisit) {
                        $penaltyPercent = $this->getVerifiedVisitLatePenalty($lateNotice);
                    } else {
                        $penaltyPercent = 0;
                    }

                    if ($log->status === AttendanceLog::STATUS_LATE) {
                        $hasLateNotice = $this->attendanceService->hasApprovedLateNotice($log->employee_id, $log->date);
                    } elseif ($isVerifiedVisit) {
                        $hasLateNotice = (bool) $lateNotice?->isApproved();
                    } else {
                        $hasLateNotice = false;
                    }

                    $breakdown[$dayMap[$dayOfWeek]] = [
                        'present' => $isPresent,
                        'late' => $isLate,
                        'has_late_notice' => $hasLateNotice,
                        'penalty_percent' => $penaltyPercent,
                        'date' => $dateKey,
                    ];
                }
            }

            $currentDate->addDay();
        }

        return $breakdown;
    }

    /**
     * A visit marker is not proof of attendance by itself. Count it only after
     * either the daily attendance or the linked client visit has checked in.
     */
    private function isVerifiedClientVisitAttendance(AttendanceLog $log): bool
    {
        if ($log->status !== AttendanceLog::STATUS_VISIT) {
            return false;
        }

        if ($log->check_in_time !== null) {
            return true;
        }

        $visit = $log->clientVisit;

        return $visit !== null
            && (int) $visit->employee_id === (int) $log->employee_id
            && $visit->visit_date?->toDateString() === $log->date->toDateString()
            && $visit->status !== ClientVisit::STATUS_CANCELLED
            && $visit->actual_check_in !== null;
    }

    /**
     * Visit status can replace a previously computed late status. Preserve the
     * reviewed LateNotice snapshot so rejected/pending notices still deduct and
     * approved notices remain waived.
     */
    private function getVerifiedVisitLatePenalty(?LateNotice $lateNotice): int
    {
        if ($lateNotice === null || $lateNotice->isApproved()) {
            return 0;
        }

        return max(0, min(100, (int) ($lateNotice->penalty_percent ?? 0)));
    }

    /**
     * Count present days from breakdown.
     *
     * Accepts both legacy bool values and the new rich array format.
     */
    public function countPresentDays(array $daysBreakdown): int
    {
        return collect($daysBreakdown)->filter(function ($day) {
            return is_array($day) ? ($day['present'] ?? false) : (bool) $day;
        })->count();
    }

    // =====================
    // Reconciliation Methods
    // =====================

    /**
     * Get total weekly allowance paid for an employee in a month.
     */
    public function getMonthlyPaidTotal(Employee $employee, Carbon $monthStart, Carbon $monthEnd): float
    {
        return (float) WeeklyAllowancePayment::forEmployee($employee->id)
            ->where('status', WeeklyAllowancePayment::STATUS_PAID)
            ->whereHas('batch', function ($query) use ($monthStart, $monthEnd) {
                $query->where('status', WeeklyAllowanceBatch::STATUS_PAID)
                    ->whereBetween('week_start_date', [$monthStart, $monthEnd]);
            })
            ->sum('total_amount');
    }

    /**
     * Get weekly payments for an employee in a month.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getMonthlyPayments(Employee $employee, Carbon $monthStart, Carbon $monthEnd)
    {
        return WeeklyAllowancePayment::forEmployee($employee->id)
            ->whereHas('batch', function ($query) use ($monthStart, $monthEnd) {
                $query->whereBetween('week_start_date', [$monthStart, $monthEnd]);
            })
            ->with('batch')
            ->get();
    }

    // =====================
    // Helper Methods
    // =====================

    /**
     * Calculate the previous week's Monday and Friday.
     *
     * @param  Carbon|null  $fromDate  Calculate from this date (default: now)
     * @return array ['start' => Carbon, 'end' => Carbon, 'payment' => Carbon]
     */
    public static function getPreviousWeekDates(?Carbon $fromDate = null): array
    {
        $from = $fromDate ?? Carbon::now();

        // Get Monday of the previous week
        $start = $from->copy()->previous(Carbon::MONDAY);
        if ($from->dayOfWeek === Carbon::MONDAY) {
            $start = $from->copy()->subWeek()->startOfWeek();
        }

        // Get Friday of that week
        $end = $start->copy()->next(Carbon::FRIDAY);
        if ($end < $start) {
            $end = $start->copy()->addDays(4);
        }

        // Payment date is the next Tuesday
        $payment = $end->copy()->next(Carbon::TUESDAY);

        return [
            'start' => $start,
            'end' => $end,
            'payment' => $payment,
        ];
    }

    /**
     * Recalculate a single payment record against current attendance and salary
     * structures. Rejects payments whose batch is locked (pending finance / paid)
     * so approved or paid payroll is never silently rewritten.
     */
    public function recalculatePayment(WeeklyAllowancePayment $payment): WeeklyAllowancePayment
    {
        $batch = $payment->batch;
        $employee = $payment->employee;

        if (! $batch || ! $employee) {
            return $payment;
        }

        if ($batch->isLocked()) {
            throw new \RuntimeException("Cannot recalculate a payment in a {$batch->status} batch.");
        }

        $this->recalculatePaymentValues($payment, $batch, $employee);
        $batch->recalculateSummary();

        return $payment->fresh();
    }

    /**
     * HR-triggered recalculation of every payment in a batch against current
     * attendance and salary structures. Only editable batches (draft / rejected)
     * may be recalculated; locked batches (pending finance / paid) are rejected
     * so finalized payroll stays immutable. Idempotent.
     */
    public function recalculateBatch(WeeklyAllowanceBatch $batch): WeeklyAllowanceBatch
    {
        if ($batch->isLocked()) {
            throw new \RuntimeException("Cannot recalculate a {$batch->status} batch. Batches in finance approval or already paid are immutable.");
        }

        return DB::transaction(function () use ($batch) {
            foreach ($batch->payments()->get() as $payment) {
                if ($employee = $payment->employee) {
                    $this->recalculatePaymentValues($payment, $batch, $employee);
                }
            }

            $batch->recalculateSummary();

            return $batch->fresh();
        });
    }

    /**
     * Recompute a single payment's stored values without touching the batch
     * summary — callers recalculate the summary once, after all payments in
     * scope are updated, instead of once per payment.
     */
    private function recalculatePaymentValues(WeeklyAllowancePayment $payment, WeeklyAllowanceBatch $batch, Employee $employee): void
    {
        $calculated = $this->calculateEmployeePayment(
            $employee,
            $batch->week_start_date,
            $batch->week_end_date
        );

        if ($calculated) {
            $payment->update([
                'daily_rate' => $calculated['daily_rate'],
                'days_present' => $calculated['days_present'],
                'days_breakdown' => $calculated['days_breakdown'],
                'total_amount' => $calculated['total_amount'],
            ]);
        }
    }
}
