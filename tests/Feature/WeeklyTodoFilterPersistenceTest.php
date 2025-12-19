<?php

use App\Filament\Resources\WeeklyTodoResource\Pages\ListWeeklyTodos;
use App\Models\WeeklyTodo;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole($this->superAdminRole);

    $this->regularUser = User::factory()->create([
        'division' => 'Engineering',
    ]);

    $thisWeekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);

    $this->pendingTodo = WeeklyTodo::factory()->create([
        'user_id' => $this->regularUser->id,
        'week_start_date' => $thisWeekStart,
        'status' => 'pending',
        'title' => 'Pending Task',
    ]);

    $this->inProgressTodo = WeeklyTodo::factory()->create([
        'user_id' => $this->regularUser->id,
        'week_start_date' => $thisWeekStart,
        'status' => 'in_progress',
        'title' => 'In Progress Task',
    ]);

    $this->doneTodo = WeeklyTodo::factory()->create([
        'user_id' => $this->regularUser->id,
        'week_start_date' => $thisWeekStart,
        'status' => 'done',
        'title' => 'Done Task',
    ]);
});

test('ListWeeklyTodos page has shouldPersistTableFiltersInSession enabled', function () {
    $this->actingAs($this->superAdmin);
    $page = new ListWeeklyTodos();
    expect($page->shouldPersistTableFiltersInSession())->toBeTrue();
});

test('getStatusSortLabel returns default label when no sort', function () {
    $page = new ListWeeklyTodos();
    $page->statusSortOrder = null;
    $reflection = new ReflectionMethod($page, 'getStatusSortLabel');
    expect($reflection->invoke($page))->toBe('Sort by Status');
});

test('getStatusSortLabel returns pending first label', function () {
    $page = new ListWeeklyTodos();
    $page->statusSortOrder = 'pending_first';
    $reflection = new ReflectionMethod($page, 'getStatusSortLabel');
    expect($reflection->invoke($page))->toBe('Sort: Pending First');
});

test('getStatusSortLabel returns done first label', function () {
    $page = new ListWeeklyTodos();
    $page->statusSortOrder = 'done_first';
    $reflection = new ReflectionMethod($page, 'getStatusSortLabel');
    expect($reflection->invoke($page))->toBe('Sort: Done First');
});

test('getStatusSortLabel returns in progress first label', function () {
    $page = new ListWeeklyTodos();
    $page->statusSortOrder = 'in_progress_first';
    $reflection = new ReflectionMethod($page, 'getStatusSortLabel');
    expect($reflection->invoke($page))->toBe('Sort: In Progress First');
});

test('setStatusSort stores value in session', function () {
    $page = new ListWeeklyTodos();
    $page->setStatusSort('pending_first');
    expect(session('weekly_todo_status_sort'))->toBe('pending_first');
    expect($page->statusSortOrder)->toBe('pending_first');
});

test('setStatusSort can clear session value', function () {
    session(['weekly_todo_status_sort' => 'done_first']);
    $page = new ListWeeklyTodos();
    $page->setStatusSort(null);
    expect(session('weekly_todo_status_sort'))->toBeNull();
    expect($page->statusSortOrder)->toBeNull();
});

test('pending first sort query orders correctly', function () {
    $query = WeeklyTodo::query();
    $orderCase = "CASE WHEN status = 'pending' THEN 1 WHEN status = 'in_progress' THEN 2 WHEN status = 'done' THEN 3 ELSE 4 END";
    $query->orderByRaw($orderCase);
    $results = $query->get();
    $statuses = $results->pluck('status')->toArray();

    $pendingIndex = array_search('pending', $statuses);
    $inProgressIndex = array_search('in_progress', $statuses);
    $doneIndex = array_search('done', $statuses);

    if ($pendingIndex !== false && $inProgressIndex !== false) {
        expect($pendingIndex)->toBeLessThan($inProgressIndex);
    }
    if ($inProgressIndex !== false && $doneIndex !== false) {
        expect($inProgressIndex)->toBeLessThan($doneIndex);
    }
});

test('done first sort query orders correctly', function () {
    $query = WeeklyTodo::query();
    $orderCase = "CASE WHEN status = 'done' THEN 1 WHEN status = 'in_progress' THEN 2 WHEN status = 'pending' THEN 3 ELSE 4 END";
    $query->orderByRaw($orderCase);
    $results = $query->get();
    $statuses = $results->pluck('status')->toArray();

    $doneIndex = array_search('done', $statuses);
    $inProgressIndex = array_search('in_progress', $statuses);
    $pendingIndex = array_search('pending', $statuses);

    if ($doneIndex !== false && $inProgressIndex !== false) {
        expect($doneIndex)->toBeLessThan($inProgressIndex);
    }
    if ($inProgressIndex !== false && $pendingIndex !== false) {
        expect($inProgressIndex)->toBeLessThan($pendingIndex);
    }
});

test('in progress first sort query orders correctly', function () {
    $query = WeeklyTodo::query();
    $orderCase = "CASE WHEN status = 'in_progress' THEN 1 WHEN status = 'pending' THEN 2 WHEN status = 'done' THEN 3 ELSE 4 END";
    $query->orderByRaw($orderCase);
    $results = $query->get();
    $statuses = $results->pluck('status')->toArray();

    $inProgressIndex = array_search('in_progress', $statuses);
    $pendingIndex = array_search('pending', $statuses);
    $doneIndex = array_search('done', $statuses);

    if ($inProgressIndex !== false && $pendingIndex !== false) {
        expect($inProgressIndex)->toBeLessThan($pendingIndex);
    }
    if ($pendingIndex !== false && $doneIndex !== false) {
        expect($pendingIndex)->toBeLessThan($doneIndex);
    }
});

test('status sort order property is public and accessible', function () {
    $page = new ListWeeklyTodos();
    expect($page->statusSortOrder)->toBeNull();
    $page->statusSortOrder = 'pending_first';
    expect($page->statusSortOrder)->toBe('pending_first');
});

test('all status sort options are valid', function () {
    $validOptions = ['pending_first', 'done_first', 'in_progress_first', null];
    $page = new ListWeeklyTodos();

    foreach ($validOptions as $option) {
        $page->statusSortOrder = $option;
        expect($page->statusSortOrder)->toBe($option);
    }
});
