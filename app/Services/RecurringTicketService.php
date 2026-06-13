<?php

namespace App\Services;

use App\Enums\TicketFrequency;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TicketTemplate;
use App\Models\TicketTemplateGeneration;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecurringTicketService
{
    /**
     * Calculate the next target date for a template based on frequency and day value.
     *
     * Requirements: 3.1, 3.2, 3.3, 4.1, 4.2, 4.3
     *
     * @param  string|TicketFrequency  $frequency  The frequency type (weekly or monthly)
     * @param  int  $dayValue  The day value (1-7 for weekly, 1-31 for monthly)
     * @param  Carbon|null  $fromDate  The reference date (defaults to today)
     * @return Carbon The calculated target date
     */
    public function calculateNextTargetDate(
        string|TicketFrequency $frequency,
        int $dayValue,
        ?Carbon $fromDate = null
    ): Carbon {
        $fromDate = $fromDate ?? Carbon::today();

        if ($frequency instanceof TicketFrequency) {
            $frequency = $frequency->value;
        }

        return match ($frequency) {
            'weekly' => $this->calculateWeeklyTargetDate($dayValue, $fromDate),
            'monthly' => $this->calculateMonthlyTargetDate($dayValue, $fromDate),
            default => throw new \InvalidArgumentException("Invalid frequency: {$frequency}"),
        };
    }

    /**
     * Calculate the next weekly target date.
     *
     * The day_value uses ISO-8601 format: 1=Monday, 7=Sunday.
     * If today is the target day, returns today (not next week).
     * The target_date is always >= fromDate.
     *
     * Requirements: 3.1, 3.2, 3.3
     *
     * @param  int  $dayOfWeek  The target day of week (1=Monday, 7=Sunday)
     * @param  Carbon|null  $fromDate  The reference date (defaults to today)
     * @return Carbon The next occurrence of the specified day
     */
    public function calculateWeeklyTargetDate(
        int $dayOfWeek,
        ?Carbon $fromDate = null
    ): Carbon {
        $fromDate = $fromDate ?? Carbon::today();
        $currentDayOfWeek = $fromDate->dayOfWeekIso;

        // If today is the target day, return today
        if ($currentDayOfWeek === $dayOfWeek) {
            return $fromDate->copy();
        }

        // If target day is later this week
        if ($currentDayOfWeek < $dayOfWeek) {
            return $fromDate->copy()->addDays($dayOfWeek - $currentDayOfWeek);
        }

        // Target day has passed this week, get next week's occurrence
        return $fromDate->copy()->addDays(7 - $currentDayOfWeek + $dayOfWeek);
    }

    /**
     * Calculate the next monthly target date.
     *
     * Handles months with fewer days than the target day (e.g., day 31 in February
     * will use the last day of February). If the target day has passed this month,
     * calculates for next month. The target_date is always >= fromDate.
     *
     * Requirements: 4.1, 4.2, 4.3
     *
     * @param  int  $dayOfMonth  The target day of month (1-31)
     * @param  Carbon|null  $fromDate  The reference date (defaults to today)
     * @return Carbon The next occurrence of the specified day
     */
    public function calculateMonthlyTargetDate(
        int $dayOfMonth,
        ?Carbon $fromDate = null
    ): Carbon {
        $fromDate = $fromDate ?? Carbon::today();
        $currentDay = $fromDate->day;

        // Try current month first
        $daysInCurrentMonth = $fromDate->daysInMonth;
        $effectiveDay = min($dayOfMonth, $daysInCurrentMonth);

        // If target day hasn't passed yet (or is today), use current month
        if ($currentDay <= $effectiveDay) {
            return $fromDate->copy()->day($effectiveDay);
        }

        // Target day has passed, move to next month
        $nextMonth = $fromDate->copy()->addMonth()->startOfMonth();
        $daysInNextMonth = $nextMonth->daysInMonth;
        $effectiveDay = min($dayOfMonth, $daysInNextMonth);

        return $nextMonth->day($effectiveDay);
    }

    /**
     * Check if a template is due to generate a ticket for its upcoming target.
     *
     * Due when today has reached (or passed) the generation date
     * (target_date - lead_time_days). Using ">=" rather than an exact-day match
     * means a lead time longer than the recurrence gap still generates, and a
     * missed daily run is caught up on the next run. Idempotency for the period
     * is enforced separately by hasGeneratedForPeriod().
     *
     * Requirements: 2.2
     *
     * @param  TicketTemplate  $template  The template to check
     * @param  Carbon|null  $today  The reference date (defaults to today)
     * @return bool True if a ticket is due to be generated
     */
    public function shouldGenerateToday(TicketTemplate $template, ?Carbon $today = null): bool
    {
        $today = $today ?? Carbon::today();

        $targetDate = $this->calculateNextTargetDate(
            $template->frequency,
            $template->day_value,
            $today
        );

        $generationDate = $targetDate->copy()->subDays($template->lead_time_days);

        return $today->copy()->startOfDay()->greaterThanOrEqualTo($generationDate->copy()->startOfDay());
    }

    /**
     * Generate a ticket from a template.
     *
     * Creates a new ticket with fields copied from the template:
     * - name, description, project_id, priority_id from template
     * - due_date set to the calculated target_date
     * - ticket_status_id set to initial_status_id (with fallback to project's first status)
     * - Assignees copied from template (only those who are still project members)
     *
     * Also creates a TicketTemplateGeneration record for tracking.
     *
     * Requirements: 2.3, 2.4, 2.5, 2.6, 5.2, 6.3, 7.2
     *
     * @param  TicketTemplate  $template  The template to generate from
     * @param  Carbon|null  $today  The reference date for target calculation
     * @return Ticket The generated ticket
     */
    public function generateTicket(TicketTemplate $template, ?Carbon $today = null): Ticket
    {
        $today = $today ?? Carbon::today();

        $targetDate = $this->calculateNextTargetDate(
            $template->frequency,
            $template->day_value,
            $today
        );

        return DB::transaction(function () use ($template, $targetDate) {
            // Determine the status to use (with fallback)
            $statusId = $this->resolveStatusId($template);

            // Create the ticket
            $ticket = Ticket::create([
                'name' => $template->name,
                'description' => $template->description,
                'project_id' => $template->project_id,
                'priority_id' => $template->priority_id,
                'ticket_status_id' => $statusId,
                'due_date' => $targetDate,
                'start_date' => Carbon::today(),
                'created_by' => $template->created_by,
            ]);

            // Assign valid project members
            $validAssigneeIds = $this->getValidAssigneeIds($template);
            if (! empty($validAssigneeIds)) {
                $ticket->assignees()->attach($validAssigneeIds);
            }

            // Record the generation
            TicketTemplateGeneration::create([
                'ticket_template_id' => $template->id,
                'ticket_id' => $ticket->id,
                'target_date' => $targetDate,
                'generated_at' => now(),
            ]);

            Log::info('Recurring ticket generated', [
                'template_id' => $template->id,
                'ticket_id' => $ticket->id,
                'target_date' => $targetDate->toDateString(),
            ]);

            return $ticket;
        });
    }

    /**
     * Resolve the status ID to use for a generated ticket.
     *
     * Uses the template's initial_status_id if it still exists,
     * otherwise falls back to the project's first status by sort_order.
     *
     * Requirements: 6.3, 6.4
     *
     * @param  TicketTemplate  $template  The template
     * @return int The status ID to use
     */
    protected function resolveStatusId(TicketTemplate $template): int
    {
        // Check if the initial status still exists
        $initialStatus = TicketStatus::find($template->initial_status_id);

        if ($initialStatus) {
            return $initialStatus->id;
        }

        // Fallback to project's first status by sort_order
        $fallbackStatus = TicketStatus::where('project_id', $template->project_id)
            ->orderBy('sort_order', 'asc')
            ->first();

        if ($fallbackStatus) {
            Log::warning('Using fallback status for recurring ticket', [
                'template_id' => $template->id,
                'original_status_id' => $template->initial_status_id,
                'fallback_status_id' => $fallbackStatus->id,
            ]);

            return $fallbackStatus->id;
        }

        throw new \RuntimeException(
            "No valid status found for template {$template->id} in project {$template->project_id}"
        );
    }

    /**
     * Get valid assignee IDs for a template.
     *
     * Filters template assignees to only include users who are
     * currently members of the project.
     *
     * Requirements: 5.2, 5.4
     *
     * @param  TicketTemplate  $template  The template
     * @return array Array of valid user IDs
     */
    protected function getValidAssigneeIds(TicketTemplate $template): array
    {
        $templateAssigneeIds = $template->assignees()->pluck('users.id')->toArray();

        if (empty($templateAssigneeIds)) {
            return [];
        }

        // Get current project member IDs
        $projectMemberIds = $template->project->members()->pluck('users.id')->toArray();

        // Return only assignees who are still project members
        return array_values(array_intersect($templateAssigneeIds, $projectMemberIds));
    }

    /**
     * Process all active templates and generate due tickets.
     *
     * Queries all active templates, checks if each should generate today,
     * skips if already generated for the period, and generates tickets.
     *
     * Requirements: 2.1, 2.8
     *
     * @param  Carbon|null  $today  The reference date (defaults to today)
     * @return array Summary of processing results
     */
    public function processAllTemplates(?Carbon $today = null): array
    {
        $today = $today ?? Carbon::today();

        $summary = [
            'processed' => 0,
            'generated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'details' => [],
        ];

        $templates = TicketTemplate::active()
            ->with(['project', 'assignees', 'initialStatus', 'generations'])
            ->get();

        foreach ($templates as $template) {
            $summary['processed']++;

            try {
                // Check if should generate today
                if (! $this->shouldGenerateToday($template, $today)) {
                    $summary['skipped']++;
                    $summary['details'][] = [
                        'template_id' => $template->id,
                        'status' => 'skipped',
                        'reason' => 'not_due_today',
                    ];

                    continue;
                }

                // Calculate target date for duplicate check
                $targetDate = $this->calculateNextTargetDate(
                    $template->frequency,
                    $template->day_value,
                    $today
                );

                // Check if already generated for this period
                if ($template->hasGeneratedForPeriod($targetDate)) {
                    $summary['skipped']++;
                    $summary['details'][] = [
                        'template_id' => $template->id,
                        'status' => 'skipped',
                        'reason' => 'already_generated',
                        'target_date' => $targetDate->toDateString(),
                    ];

                    Log::debug('Template skipped - already generated for period', [
                        'template_id' => $template->id,
                        'target_date' => $targetDate->toDateString(),
                    ]);

                    continue;
                }

                // Generate the ticket
                $ticket = $this->generateTicket($template, $today);

                $summary['generated']++;
                $summary['details'][] = [
                    'template_id' => $template->id,
                    'status' => 'generated',
                    'ticket_id' => $ticket->id,
                    'target_date' => $targetDate->toDateString(),
                ];
            } catch (\Exception $e) {
                $summary['errors']++;
                $summary['details'][] = [
                    'template_id' => $template->id,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to generate recurring ticket', [
                    'template_id' => $template->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }
}
