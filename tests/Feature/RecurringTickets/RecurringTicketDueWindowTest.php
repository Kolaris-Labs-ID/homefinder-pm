<?php

/**
 * Regression tests for recurring-ticket due-window / lead-time generation.
 *
 * Bug: tickets were generated only when today == target_date - lead_time_days
 * (strict single-day match against the *next* occurrence). Because the next
 * occurrence is always within the current recurrence window, the gap to it is
 * bounded (<=6 days for weekly), so any lead_time_days >= 7 could never match
 * and the template never generated. A missed daily run also lost the period.
 */

use App\Enums\TicketFrequency;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketTemplate;
use App\Models\TicketTemplateGeneration;
use App\Models\User;
use App\Services\RecurringTicketService;
use Carbon\Carbon;

beforeEach(function () {
    TicketTemplateGeneration::query()->delete();
    TicketTemplate::query()->delete();
    Ticket::query()->delete();

    $this->user = User::factory()->create();
    $this->project = Project::factory()->create();
    $this->project->members()->attach($this->user->id);

    $this->status = TicketStatus::factory()->create([
        'project_id' => $this->project->id,
        'sort_order' => 1,
    ]);

    $this->priority = TicketPriority::firstOrCreate(['name' => 'High'], ['color' => '#ff0000']);

    $this->service = new RecurringTicketService;

    $this->makeTemplate = function (TicketFrequency $frequency, int $dayValue, int $leadTimeDays): TicketTemplate {
        return TicketTemplate::create([
            'name' => 'Recurring '.$frequency->value,
            'description' => 'desc',
            'project_id' => $this->project->id,
            'frequency' => $frequency,
            'day_value' => $dayValue,
            'lead_time_days' => $leadTimeDays,
            'initial_status_id' => $this->status->id,
            'priority_id' => $this->priority->id,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
    };
});

test('weekly template with a 7-day lead time generates within the cycle', function () {
    ($this->makeTemplate)(TicketFrequency::Weekly, 1, 7); // Monday, 7-day lead

    $monday = Carbon::parse('next monday')->startOfDay();

    $generated = 0;
    for ($i = 0; $i < 14; $i++) {
        $generated += $this->service->processAllTemplates($monday->copy()->addDays($i))['generated'];
    }

    expect($generated)->toBeGreaterThan(
        0,
        "Weekly template with lead_time_days=7 generated {$generated} tickets over two weeks; expected at least one"
    );
})->group('recurring-tickets');

test('weekly template generates exactly once per period across several weeks', function () {
    ($this->makeTemplate)(TicketFrequency::Weekly, 3, 2); // Wednesday, 2-day lead

    $monday = Carbon::parse('next monday')->startOfDay();

    $generated = 0;
    for ($i = 0; $i < 21; $i++) {
        $generated += $this->service->processAllTemplates($monday->copy()->addDays($i))['generated'];
    }

    // 21 days spans 3 Wednesdays -> exactly 3 tickets, each with a distinct target_date.
    expect($generated)->toBe(3)
        ->and(TicketTemplateGeneration::distinct('target_date')->count('target_date'))->toBe(3);
})->group('recurring-tickets');

test('a missed daily run is caught up the next day', function () {
    ($this->makeTemplate)(TicketFrequency::Weekly, 3, 2); // Wednesday, 2-day lead -> generation day is Monday

    $monday = Carbon::parse('next monday')->startOfDay();

    // Simulate the scheduler NOT running on Monday (deploy/downtime), then running Tuesday.
    $summary = $this->service->processAllTemplates($monday->copy()->addDay()); // Tuesday

    expect($summary['generated'])->toBe(1, 'Ticket should still be generated the day after a missed run');
    expect(Ticket::count())->toBe(1);
})->group('recurring-tickets');

test('TicketTemplate model fires generation within the lead window for a 7-day weekly lead', function () {
    $template = ($this->makeTemplate)(TicketFrequency::Weekly, 1, 7); // Monday, 7-day lead

    $monday = Carbon::parse('next monday')->startOfDay();

    $firedOnSomeDay = collect(range(0, 6))
        ->contains(fn (int $i) => $template->shouldGenerateToday($monday->copy()->addDays($i)));

    expect($firedOnSomeDay)->toBeTrue('Model::shouldGenerateToday never returned true across a full week for lead=7');
})->group('recurring-tickets');
