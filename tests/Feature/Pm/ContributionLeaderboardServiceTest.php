<?php

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketHistory;
use App\Models\User;
use App\Services\ContributionLeaderboardService;

// Weights: ticket 2, status change 5, comment 2, active day 1.
//
// Activity is dated midday on earlier days, not now(): the window is converted
// to UTC while timestamps are stored in app time, so recent activity late in
// the day falls outside it. That is a separate bug; these tests pin the query.
it('ranks contributors by score within the time range', function () {
    $alice = User::factory()->create(['name' => 'Alice Example']);
    $bob = User::factory()->create(['name' => 'Bob Example']);
    $idle = User::factory()->create(['name' => 'Idle Example']);
    $stale = User::factory()->create(['name' => 'Stale Example']);

    // Alice: 2 tickets on 2 days, 3 status changes → 2*2 + 3*5 + 2 days = 21
    $ticket = Ticket::factory()->create(['created_by' => $alice->id, 'created_at' => now()->subDays(1)->setTime(12, 0)]);
    Ticket::factory()->create(['created_by' => $alice->id, 'created_at' => now()->subDays(2)->setTime(12, 0)]);
    TicketHistory::factory()->count(3)->create(['user_id' => $alice->id, 'ticket_id' => $ticket->id, 'created_at' => now()->subDays(1)->setTime(12, 0)]);

    // Bob: 1 comment → 1*2 + 1 day = 3
    TicketComment::factory()->create(['user_id' => $bob->id, 'ticket_id' => $ticket->id, 'created_at' => now()->subDays(3)->setTime(12, 0)]);

    // Stale: only activity outside the 7-day window, so not listed.
    Ticket::factory()->create(['created_by' => $stale->id, 'created_at' => now()->subDays(20)]);

    $board = app(ContributionLeaderboardService::class)->getLeaderboard('7days', 10);

    expect($board)->toHaveCount(2)
        ->and($board[0]['user']['id'])->toBe($alice->id)
        ->and($board[0]['total_score'])->toBe(21)
        ->and($board[0]['stats'])->toBe(['tickets_created' => 2, 'status_changes' => 3, 'comments_made' => 0, 'active_days' => 2])
        ->and($board[0]['rank'])->toBe(1)
        ->and($board[0]['user']['avatar_initials'])->toBe($alice->avatar_initials)
        ->and($board[1]['user']['id'])->toBe($bob->id)
        ->and($board[1]['total_score'])->toBe(3)
        ->and($board[1]['stats']['comments_made'])->toBe(1)
        ->and($board[1]['rank'])->toBe(2)
        ->and(collect($board)->pluck('user.id'))->not->toContain($idle->id, $stale->id);
});

it('limits the board to the requested size', function () {
    foreach (User::factory()->count(3)->create() as $user) {
        Ticket::factory()->create(['created_by' => $user->id, 'created_at' => now()->subDays(1)->setTime(12, 0)]);
    }

    expect(app(ContributionLeaderboardService::class)->getLeaderboard('7days', 2))->toHaveCount(2);
});
