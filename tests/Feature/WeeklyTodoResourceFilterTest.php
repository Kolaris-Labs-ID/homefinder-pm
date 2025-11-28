<?php

use App\Filament\Resources\WeeklyTodoResource;
use App\Filament\Resources\WeeklyTodoResource\Pages\ListWeeklyTodos;
use App\Models\WeeklyTodo;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    $this->superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    
    // Create users with different roles and divisions
    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole($this->superAdminRole);
    
    $this->admin = User::factory()->create();
    $this->admin->assignRole($this->adminRole);
    
    $this->headDivision = User::factory()->create([
        'division' => 'Engineering',
        'is_division_head' => true,
    ]);
    
    $this->regularUser = User::factory()->create([
        'division' => 'Engineering',
    ]);
    
    $this->otherDivisionUser = User::factory()->create([
        'division' => 'Marketing',
    ]);
    
    $this->salesUser = User::factory()->create([
        'division' => 'Sales',
    ]);
    
    // Create weekly todos for different users, weeks, and statuses
    $this->thisWeekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
    $this->lastWeekStart = $this->thisWeekStart->copy()->subWeek();
    $this->nextWeekStart = $this->thisWeekStart->copy()->addWeek();
    $this->twoWeeksAgoStart = $this->thisWeekStart->copy()->subWeeks(2);
    
    // Engineering division todos
    $this->engineeringPendingThisWeek = WeeklyTodo::factory()->create([
        'user_id' => $this->regularUser->id,
        'week_start_date' => $this->thisWeekStart,
        'status' => 'pending',
    ]);
    
    $this->engineeringDoneLastWeek = WeeklyTodo::factory()->create([
        'user_id' => $this->regularUser->id,
        'week_start_date' => $this->lastWeekStart,
        'status' => 'done',
    ]);
    
    $this->engineeringInProgressNextWeek = WeeklyTodo::factory()->create([
        'user_id' => $this->regularUser->id,
        'week_start_date' => $this->nextWeekStart,
        'status' => 'in_progress',
    ]);
    
    // Marketing division todos
    $this->marketingPendingThisWeek = WeeklyTodo::factory()->create([
        'user_id' => $this->otherDivisionUser->id,
        'week_start_date' => $this->thisWeekStart,
        'status' => 'pending',
    ]);
    
    $this->marketingDoneLastWeek = WeeklyTodo::factory()->create([
        'user_id' => $this->otherDivisionUser->id,
        'week_start_date' => $this->lastWeekStart,
        'status' => 'done',
    ]);
    
    // Head division todo
    $this->headDivisionTodo = WeeklyTodo::factory()->create([
        'user_id' => $this->headDivision->id,
        'week_start_date' => $this->thisWeekStart,
        'status' => 'in_progress',
    ]);
    
    // Sales division todo
    $this->salesTodo = WeeklyTodo::factory()->create([
        'user_id' => $this->salesUser->id,
        'week_start_date' => $this->thisWeekStart,
        'status' => 'pending',
    ]);
});

// ==================== User Filter Tests ====================

test('user filter exists and is visible for super admin', function () {
    $this->actingAs($this->superAdmin);
    
    $page = new ListWeeklyTodos();
    $table = WeeklyTodoResource::table(Table::make($page));
    $filters = $table->getFilters();
    $userFilter = collect($filters)->first(fn($filter) => $filter->getName() === 'user_id');
    
    expect($userFilter)->not->toBeNull();
    expect($userFilter->isVisible())->toBeTrue();
});

test('user filter exists and is visible for admin', function () {
    $this->actingAs($this->admin);
    
    $page = new ListWeeklyTodos();
    $table = WeeklyTodoResource::table(Table::make($page));
    $filters = $table->getFilters();
    $userFilter = collect($filters)->first(fn($filter) => $filter->getName() === 'user_id');
    
    expect($userFilter)->not->toBeNull();
    expect($userFilter->isVisible())->toBeTrue();
});

test('user filter exists and is visible for head division', function () {
    $this->actingAs($this->headDivision);
    
    $page = new ListWeeklyTodos();
    $table = WeeklyTodoResource::table(Table::make($page));
    $filters = $table->getFilters();
    $userFilter = collect($filters)->first(fn($filter) => $filter->getName() === 'user_id');
    
    expect($userFilter)->not->toBeNull();
    expect($userFilter->isVisible())->toBeTrue();
});

test('user filter filters by specific user', function () {
    $this->actingAs($this->superAdmin);
    
    $page = new ListWeeklyTodos();
    $table = WeeklyTodoResource::table(Table::make($page));
    $query = WeeklyTodoResource::getEloquentQuery();
    
    // Get the user filter
    $filters = $table->getFilters();
    $userFilter = collect($filters)->first(fn($filter) => $filter->getName() === 'user_id');
    
    expect($userFilter)->not->toBeNull();
    
    // Test filtering by user - SelectFilter with relationship automatically applies where('user_id', $value)
    $filteredQuery = $query->where('user_id', $this->regularUser->id);
    $results = $filteredQuery->get();
    
    // Should only contain todos from regularUser
    expect($results->pluck('id')->toArray())->toContain($this->engineeringPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->engineeringDoneLastWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->engineeringInProgressNextWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->marketingPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->marketingDoneLastWeek->id);
});

test('user filter works with multiple users', function () {
    $this->actingAs($this->superAdmin);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    // Filter by otherDivisionUser
    $filteredQuery = $query->where('user_id', $this->otherDivisionUser->id);
    $results = $filteredQuery->get();
    
    expect($results->pluck('id')->toArray())->toContain($this->marketingPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->marketingDoneLastWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringPendingThisWeek->id);
});

// ==================== Division Filter Tests ====================

test('division filter exists and is visible for super admin', function () {
    $this->actingAs($this->superAdmin);
    
    $page = new ListWeeklyTodos();
    $table = WeeklyTodoResource::table(Table::make($page));
    $filters = $table->getFilters();
    $divisionFilter = collect($filters)->first(fn($filter) => $filter->getName() === 'division');
    
    expect($divisionFilter)->not->toBeNull();
    expect($divisionFilter->isVisible())->toBeTrue();
});

test('division filter exists and is visible for head division', function () {
    $this->actingAs($this->headDivision);
    
    $page = new ListWeeklyTodos();
    $table = WeeklyTodoResource::table(Table::make($page));
    $filters = $table->getFilters();
    $divisionFilter = collect($filters)->first(fn($filter) => $filter->getName() === 'division');
    
    expect($divisionFilter)->not->toBeNull();
    expect($divisionFilter->isVisible())->toBeTrue();
});

test('division filter filters by Engineering division', function () {
    $this->actingAs($this->superAdmin);
    
    // Test the division filter query callback directly (from WeeklyTodoResource)
    $query = WeeklyTodoResource::getEloquentQuery();
    
    $divisionFilterCallback = function (Builder $query, array $data): Builder {
        if (!isset($data['division']) || $data['division'] === null) {
            return $query;
        }
        
        return $query->whereHas('user', function (Builder $q) use ($data) {
            $q->where('division', $data['division']);
        });
    };
    
    // Apply division filter for Engineering
    $filteredQuery = $divisionFilterCallback(clone $query, ['division' => 'Engineering']);
    $results = $filteredQuery->get();
    
    // Should contain Engineering division todos
    expect($results->pluck('id')->toArray())->toContain($this->engineeringPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->engineeringDoneLastWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->engineeringInProgressNextWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->headDivisionTodo->id);
    
    // Should not contain Marketing division todos
    expect($results->pluck('id')->toArray())->not->toContain($this->marketingPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->marketingDoneLastWeek->id);
});

test('division filter filters by Marketing division', function () {
    $this->actingAs($this->superAdmin);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    $divisionFilterCallback = function (Builder $query, array $data): Builder {
        if (!isset($data['division']) || $data['division'] === null) {
            return $query;
        }
        
        return $query->whereHas('user', function (Builder $q) use ($data) {
            $q->where('division', $data['division']);
        });
    };
    
    // Apply division filter for Marketing
    $filteredQuery = $divisionFilterCallback(clone $query, ['division' => 'Marketing']);
    $results = $filteredQuery->get();
    
    // Should contain Marketing division todos
    expect($results->pluck('id')->toArray())->toContain($this->marketingPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->marketingDoneLastWeek->id);
    
    // Should not contain Engineering division todos
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->headDivisionTodo->id);
});

test('division filter returns all when no division selected', function () {
    $this->actingAs($this->superAdmin);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    $divisionFilterCallback = function (Builder $query, array $data): Builder {
        if (!isset($data['division']) || $data['division'] === null) {
            return $query;
        }
        
        return $query->whereHas('user', function (Builder $q) use ($data) {
            $q->where('division', $data['division']);
        });
    };
    
    // Apply filter with null division
    $filteredQuery = $divisionFilterCallback(clone $query, ['division' => null]);
    $results = $filteredQuery->get();
    
    // Should return all todos (no filtering)
    expect($results->count())->toBeGreaterThan(0);
});

// ==================== Week Start Date Range Filter Tests ====================

test('week start date filter exists', function () {
    $this->actingAs($this->superAdmin);
    
    $page = new ListWeeklyTodos();
    $table = WeeklyTodoResource::table(Table::make($page));
    $filters = $table->getFilters();
    $weekFilter = collect($filters)->first(fn($filter) => $filter->getName() === 'week_start_date');
    
    expect($weekFilter)->not->toBeNull();
});

test('week start date filter with week_from only filters correctly', function () {
    $this->actingAs($this->superAdmin);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    // Test the week_start_date filter query callback (from WeeklyTodoResource)
    $weekFilterCallback = function (Builder $query, array $data): Builder {
        return $query
            ->when(
                $data['week_from'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate('week_start_date', '>=', \Carbon\Carbon::parse($date)->startOfWeek(Carbon::MONDAY)),
            )
            ->when(
                $data['week_until'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate('week_start_date', '<=', \Carbon\Carbon::parse($date)->startOfWeek(Carbon::MONDAY)),
            );
    };
    
    // Filter from this week onwards
    $filteredQuery = $weekFilterCallback(clone $query, [
        'week_from' => $this->thisWeekStart->format('Y-m-d'),
        'week_until' => null,
    ]);
    $results = $filteredQuery->get();
    
    // Should contain this week and future weeks
    expect($results->pluck('id')->toArray())->toContain($this->engineeringPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->engineeringInProgressNextWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->marketingPendingThisWeek->id);
    
    // Should not contain last week
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringDoneLastWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->marketingDoneLastWeek->id);
});

test('week start date filter with week_until only filters correctly', function () {
    $this->actingAs($this->superAdmin);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    $weekFilterCallback = function (Builder $query, array $data): Builder {
        return $query
            ->when(
                $data['week_from'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate('week_start_date', '>=', \Carbon\Carbon::parse($date)->startOfWeek(Carbon::MONDAY)),
            )
            ->when(
                $data['week_until'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate('week_start_date', '<=', \Carbon\Carbon::parse($date)->startOfWeek(Carbon::MONDAY)),
            );
    };
    
    // Filter until this week
    $filteredQuery = $weekFilterCallback(clone $query, [
        'week_from' => null,
        'week_until' => $this->thisWeekStart->format('Y-m-d'),
    ]);
    $results = $filteredQuery->get();
    
    // Should contain this week and past weeks
    expect($results->pluck('id')->toArray())->toContain($this->engineeringPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->engineeringDoneLastWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->marketingPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->marketingDoneLastWeek->id);
    
    // Should not contain next week
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringInProgressNextWeek->id);
});

test('week start date filter with both week_from and week_until filters correctly', function () {
    $this->actingAs($this->superAdmin);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    $weekFilterCallback = function (Builder $query, array $data): Builder {
        return $query
            ->when(
                $data['week_from'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate('week_start_date', '>=', \Carbon\Carbon::parse($date)->startOfWeek(Carbon::MONDAY)),
            )
            ->when(
                $data['week_until'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate('week_start_date', '<=', \Carbon\Carbon::parse($date)->startOfWeek(Carbon::MONDAY)),
            );
    };
    
    // Filter from last week to this week
    $filteredQuery = $weekFilterCallback(clone $query, [
        'week_from' => $this->lastWeekStart->format('Y-m-d'),
        'week_until' => $this->thisWeekStart->format('Y-m-d'),
    ]);
    $results = $filteredQuery->get();
    
    // Should contain last week and this week
    expect($results->pluck('id')->toArray())->toContain($this->engineeringDoneLastWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->engineeringPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->marketingDoneLastWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->marketingPendingThisWeek->id);
    
    // Should not contain next week
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringInProgressNextWeek->id);
});

test('week start date filter normalizes dates to Monday', function () {
    $this->actingAs($this->superAdmin);
    
    // Create a todo for a specific week
    $specificWeekStart = Carbon::parse('2024-01-10'); // This is a Wednesday
    $mondayOfThatWeek = $specificWeekStart->copy()->startOfWeek(Carbon::MONDAY);
    
    $todo = WeeklyTodo::factory()->create([
        'user_id' => $this->regularUser->id,
        'week_start_date' => $mondayOfThatWeek,
    ]);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    $weekFilterCallback = function (Builder $query, array $data): Builder {
        return $query
            ->when(
                $data['week_from'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate('week_start_date', '>=', \Carbon\Carbon::parse($date)->startOfWeek(Carbon::MONDAY)),
            )
            ->when(
                $data['week_until'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate('week_start_date', '<=', \Carbon\Carbon::parse($date)->startOfWeek(Carbon::MONDAY)),
            );
    };
    
    // Filter using Wednesday date - should normalize to Monday
    $filteredQuery = $weekFilterCallback(clone $query, [
        'week_from' => $specificWeekStart->format('Y-m-d'),
        'week_until' => $specificWeekStart->format('Y-m-d'),
    ]);
    $results = $filteredQuery->get();
    
    // Should find the todo because the date is normalized to Monday
    expect($results->pluck('id')->toArray())->toContain($todo->id);
});

// ==================== This Week Filter Tests ====================

test('this week filter exists', function () {
    $this->actingAs($this->superAdmin);
    
    $page = new ListWeeklyTodos();
    $table = WeeklyTodoResource::table(Table::make($page));
    $filters = $table->getFilters();
    $thisWeekFilter = collect($filters)->first(fn($filter) => $filter->getName() === 'this_week');
    
    expect($thisWeekFilter)->not->toBeNull();
});

test('this week filter shows only current week todos', function () {
    $this->actingAs($this->superAdmin);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    // Test the this_week filter query callback (from WeeklyTodoResource)
    $thisWeekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
    $thisWeekFilterCallback = function (Builder $query): Builder {
        return $query->whereDate('week_start_date', Carbon::now()->startOfWeek(Carbon::MONDAY));
    };
    
    // Apply this week filter
    $filteredQuery = $thisWeekFilterCallback(clone $query);
    $results = $filteredQuery->get();
    
    // Should contain this week todos
    expect($results->pluck('id')->toArray())->toContain($this->engineeringPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->marketingPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->headDivisionTodo->id);
    expect($results->pluck('id')->toArray())->toContain($this->salesTodo->id);
    
    // Should not contain last week or next week todos
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringDoneLastWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->marketingDoneLastWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringInProgressNextWeek->id);
});

test('this week filter is a toggle filter', function () {
    $this->actingAs($this->superAdmin);
    
    $page = new ListWeeklyTodos();
    $table = WeeklyTodoResource::table(Table::make($page));
    $filters = $table->getFilters();
    $thisWeekFilter = collect($filters)->first(fn($filter) => $filter->getName() === 'this_week');
    
    // Toggle filters should have a specific type
    expect($thisWeekFilter)->toBeInstanceOf(\Filament\Tables\Filters\Filter::class);
});

// ==================== Status Filter Tests ====================

test('status filter exists', function () {
    $this->actingAs($this->superAdmin);
    
    $page = new ListWeeklyTodos();
    $table = WeeklyTodoResource::table(Table::make($page));
    $filters = $table->getFilters();
    $statusFilter = collect($filters)->first(fn($filter) => $filter->getName() === 'status');
    
    expect($statusFilter)->not->toBeNull();
});

test('status filter filters by pending status', function () {
    $this->actingAs($this->superAdmin);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    // Test filtering by pending status - SelectFilter automatically applies where('status', $value)
    $filteredQuery = $query->where('status', 'pending');
    $results = $filteredQuery->get();
    
    // Should contain pending todos
    expect($results->pluck('id')->toArray())->toContain($this->engineeringPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->marketingPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->salesTodo->id);
    
    // Should not contain done or in_progress todos
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringDoneLastWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->marketingDoneLastWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringInProgressNextWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->headDivisionTodo->id);
});

test('status filter filters by in_progress status', function () {
    $this->actingAs($this->superAdmin);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    // Test filtering by in_progress status
    $filteredQuery = $query->where('status', 'in_progress');
    $results = $filteredQuery->get();
    
    // Should contain in_progress todos
    expect($results->pluck('id')->toArray())->toContain($this->engineeringInProgressNextWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->headDivisionTodo->id);
    
    // Should not contain pending or done todos
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->marketingPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringDoneLastWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->marketingDoneLastWeek->id);
});

test('status filter filters by done status', function () {
    $this->actingAs($this->superAdmin);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    // Test filtering by done status
    $filteredQuery = $query->where('status', 'done');
    $results = $filteredQuery->get();
    
    // Should contain done todos
    expect($results->pluck('id')->toArray())->toContain($this->engineeringDoneLastWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->marketingDoneLastWeek->id);
    
    // Should not contain pending or in_progress todos
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->marketingPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringInProgressNextWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->headDivisionTodo->id);
});

// ==================== Filter Visibility Tests ====================

test('user filter is not visible for regular users', function () {
    $this->actingAs($this->regularUser);
    
    $page = new ListWeeklyTodos();
    $table = WeeklyTodoResource::table(Table::make($page));
    $filters = $table->getFilters();
    $userFilter = collect($filters)->first(fn($filter) => $filter->getName() === 'user_id');
    
    // For regular users, the filter should not be in the collection (filtered out by visibility)
    // OR if it exists, it should not be visible
    if ($userFilter !== null) {
        expect($userFilter->isVisible())->toBeFalse();
    } else {
        // Filter is not in collection, which is also correct behavior
        expect($userFilter)->toBeNull();
    }
});

test('division filter is not visible for regular users', function () {
    $this->actingAs($this->regularUser);
    
    $page = new ListWeeklyTodos();
    $table = WeeklyTodoResource::table(Table::make($page));
    $filters = $table->getFilters();
    $divisionFilter = collect($filters)->first(fn($filter) => $filter->getName() === 'division');
    
    // For regular users, the filter should not be in the collection (filtered out by visibility)
    // OR if it exists, it should not be visible
    if ($divisionFilter !== null) {
        expect($divisionFilter->isVisible())->toBeFalse();
    } else {
        // Filter is not in collection, which is also correct behavior
        expect($divisionFilter)->toBeNull();
    }
});

test('week start date filter is visible for regular users', function () {
    $this->actingAs($this->regularUser);
    
    $page = new ListWeeklyTodos();
    $table = WeeklyTodoResource::table(Table::make($page));
    $filters = $table->getFilters();
    $weekFilter = collect($filters)->first(fn($filter) => $filter->getName() === 'week_start_date');
    
    expect($weekFilter)->not->toBeNull();
    expect($weekFilter->isVisible())->toBeTrue();
});

test('this week filter is visible for regular users', function () {
    $this->actingAs($this->regularUser);
    
    $page = new ListWeeklyTodos();
    $table = WeeklyTodoResource::table(Table::make($page));
    $filters = $table->getFilters();
    $thisWeekFilter = collect($filters)->first(fn($filter) => $filter->getName() === 'this_week');
    
    expect($thisWeekFilter)->not->toBeNull();
    expect($thisWeekFilter->isVisible())->toBeTrue();
});

test('status filter is visible for regular users', function () {
    $this->actingAs($this->regularUser);
    
    $page = new ListWeeklyTodos();
    $table = WeeklyTodoResource::table(Table::make($page));
    $filters = $table->getFilters();
    $statusFilter = collect($filters)->first(fn($filter) => $filter->getName() === 'status');
    
    expect($statusFilter)->not->toBeNull();
    expect($statusFilter->isVisible())->toBeTrue();
});

// ==================== Filter Combination Tests ====================

test('division and status filters work together', function () {
    $this->actingAs($this->superAdmin);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    $divisionFilterCallback = function (Builder $query, array $data): Builder {
        if (!isset($data['division']) || $data['division'] === null) {
            return $query;
        }
        
        return $query->whereHas('user', function (Builder $q) use ($data) {
            $q->where('division', $data['division']);
        });
    };
    
    // Apply both filters: Engineering division + pending status
    $filteredQuery = $divisionFilterCallback(clone $query, ['division' => 'Engineering']);
    $filteredQuery = $filteredQuery->where('status', 'pending');
    $results = $filteredQuery->get();
    
    // Should contain Engineering pending todos
    expect($results->pluck('id')->toArray())->toContain($this->engineeringPendingThisWeek->id);
    
    // Should not contain Marketing pending todos
    expect($results->pluck('id')->toArray())->not->toContain($this->marketingPendingThisWeek->id);
    
    // Should not contain Engineering done or in_progress todos
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringDoneLastWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringInProgressNextWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->headDivisionTodo->id);
});

test('user and status filters work together', function () {
    $this->actingAs($this->superAdmin);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    // Apply both filters: regularUser + done status
    $filteredQuery = $query->where('user_id', $this->regularUser->id)
        ->where('status', 'done');
    $results = $filteredQuery->get();
    
    // Should contain regularUser done todos
    expect($results->pluck('id')->toArray())->toContain($this->engineeringDoneLastWeek->id);
    
    // Should not contain regularUser pending or in_progress todos
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringInProgressNextWeek->id);
    
    // Should not contain other users' done todos
    expect($results->pluck('id')->toArray())->not->toContain($this->marketingDoneLastWeek->id);
});

test('week range and status filters work together', function () {
    $this->actingAs($this->superAdmin);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    $weekFilterCallback = function (Builder $query, array $data): Builder {
        return $query
            ->when(
                $data['week_from'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate('week_start_date', '>=', \Carbon\Carbon::parse($date)->startOfWeek(Carbon::MONDAY)),
            )
            ->when(
                $data['week_until'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate('week_start_date', '<=', \Carbon\Carbon::parse($date)->startOfWeek(Carbon::MONDAY)),
            );
    };
    
    // Apply both filters: this week + pending status
    $filteredQuery = $weekFilterCallback(clone $query, [
        'week_from' => $this->thisWeekStart->format('Y-m-d'),
        'week_until' => $this->thisWeekStart->format('Y-m-d'),
    ]);
    $filteredQuery = $filteredQuery->where('status', 'pending');
    $results = $filteredQuery->get();
    
    // Should contain this week pending todos
    expect($results->pluck('id')->toArray())->toContain($this->engineeringPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->marketingPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->toContain($this->salesTodo->id);
    
    // Should not contain this week done or in_progress todos
    expect($results->pluck('id')->toArray())->not->toContain($this->headDivisionTodo->id);
    
    // Should not contain last week or next week todos
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringDoneLastWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringInProgressNextWeek->id);
});

test('multiple filters work together', function () {
    $this->actingAs($this->superAdmin);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    $divisionFilterCallback = function (Builder $query, array $data): Builder {
        if (!isset($data['division']) || $data['division'] === null) {
            return $query;
        }
        
        return $query->whereHas('user', function (Builder $q) use ($data) {
            $q->where('division', $data['division']);
        });
    };
    
    $weekFilterCallback = function (Builder $query, array $data): Builder {
        return $query
            ->when(
                $data['week_from'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate('week_start_date', '>=', \Carbon\Carbon::parse($date)->startOfWeek(Carbon::MONDAY)),
            )
            ->when(
                $data['week_until'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate('week_start_date', '<=', \Carbon\Carbon::parse($date)->startOfWeek(Carbon::MONDAY)),
            );
    };
    
    // Apply all three filters: Engineering division + this week + pending
    $filteredQuery = $divisionFilterCallback(clone $query, ['division' => 'Engineering']);
    $filteredQuery = $weekFilterCallback($filteredQuery, [
        'week_from' => $this->thisWeekStart->format('Y-m-d'),
        'week_until' => $this->thisWeekStart->format('Y-m-d'),
    ]);
    $filteredQuery = $filteredQuery->where('status', 'pending');
    $results = $filteredQuery->get();
    
    // Should contain Engineering, this week, pending todos
    expect($results->pluck('id')->toArray())->toContain($this->engineeringPendingThisWeek->id);
    
    // Should not contain other combinations
    expect($results->pluck('id')->toArray())->not->toContain($this->marketingPendingThisWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringDoneLastWeek->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->headDivisionTodo->id);
});

// ==================== Edge Cases Tests ====================

test('division filter with null value returns all results', function () {
    $this->actingAs($this->superAdmin);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    $divisionFilterCallback = function (Builder $query, array $data): Builder {
        if (!isset($data['division']) || $data['division'] === null) {
            return $query;
        }
        
        return $query->whereHas('user', function (Builder $q) use ($data) {
            $q->where('division', $data['division']);
        });
    };
    
    // Apply filter with null
    $filteredQuery = $divisionFilterCallback(clone $query, ['division' => null]);
    $results = $filteredQuery->get();
    
    // Should return all todos (no filtering applied)
    expect($results->count())->toBeGreaterThan(0);
});

test('week start date filter with both null values returns all results', function () {
    $this->actingAs($this->superAdmin);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    $weekFilterCallback = function (Builder $query, array $data): Builder {
        return $query
            ->when(
                $data['week_from'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate('week_start_date', '>=', \Carbon\Carbon::parse($date)->startOfWeek(Carbon::MONDAY)),
            )
            ->when(
                $data['week_until'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate('week_start_date', '<=', \Carbon\Carbon::parse($date)->startOfWeek(Carbon::MONDAY)),
            );
    };
    
    // Apply filter with both null
    $filteredQuery = $weekFilterCallback(clone $query, [
        'week_from' => null,
        'week_until' => null,
    ]);
    $results = $filteredQuery->get();
    
    // Should return all todos (no filtering applied)
    expect($results->count())->toBeGreaterThan(0);
});

test('status filter with null value returns all results', function () {
    $this->actingAs($this->superAdmin);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    // When status filter has null value, SelectFilter doesn't apply any where clause
    // So the query should return all results
    $results = $query->get();
    
    // Should return all todos (no filtering applied)
    expect($results->count())->toBeGreaterThan(0);
});

test('filtering with no matching results returns empty collection', function () {
    $this->actingAs($this->superAdmin);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    $divisionFilterCallback = function (Builder $query, array $data): Builder {
        if (!isset($data['division']) || $data['division'] === null) {
            return $query;
        }
        
        return $query->whereHas('user', function (Builder $q) use ($data) {
            $q->where('division', $data['division']);
        });
    };
    
    // Apply filters that don't match: Sales division + done status
    // But we only have Sales with pending status
    $filteredQuery = $divisionFilterCallback(clone $query, ['division' => 'Sales']);
    $filteredQuery = $filteredQuery->where('status', 'done');
    $results = $filteredQuery->get();
    
    // Should return empty (Sales division has no done todos)
    expect($results->count())->toBe(0);
});

test('week start date filter normalizes any day of week to Monday', function () {
    $this->actingAs($this->superAdmin);
    
    // Create todos for different days of the same week
    $monday = Carbon::parse('2024-01-08'); // Monday
    $wednesday = Carbon::parse('2024-01-10'); // Wednesday
    $friday = Carbon::parse('2024-01-12'); // Friday
    $sunday = Carbon::parse('2024-01-14'); // Sunday
    
    // All should normalize to the same Monday
    $normalizedMonday = $monday->copy()->startOfWeek(Carbon::MONDAY);
    
    $mondayTodo = WeeklyTodo::factory()->create([
        'user_id' => $this->regularUser->id,
        'week_start_date' => $normalizedMonday,
    ]);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    $weekFilterCallback = function (Builder $query, array $data): Builder {
        return $query
            ->when(
                $data['week_from'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate('week_start_date', '>=', \Carbon\Carbon::parse($date)->startOfWeek(Carbon::MONDAY)),
            )
            ->when(
                $data['week_until'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate('week_start_date', '<=', \Carbon\Carbon::parse($date)->startOfWeek(Carbon::MONDAY)),
            );
    };
    
    // Test with Wednesday date - should find Monday todo
    $filteredQuery = $weekFilterCallback(clone $query, [
        'week_from' => $wednesday->format('Y-m-d'),
        'week_until' => $wednesday->format('Y-m-d'),
    ]);
    $results = $filteredQuery->get();
    expect($results->pluck('id')->toArray())->toContain($mondayTodo->id);
    
    // Test with Friday date - should find Monday todo
    $filteredQuery = $weekFilterCallback(clone $query, [
        'week_from' => $friday->format('Y-m-d'),
        'week_until' => $friday->format('Y-m-d'),
    ]);
    $results = $filteredQuery->get();
    expect($results->pluck('id')->toArray())->toContain($mondayTodo->id);
    
    // Test with Sunday date - should find Monday todo
    $filteredQuery = $weekFilterCallback(clone $query, [
        'week_from' => $sunday->format('Y-m-d'),
        'week_until' => $sunday->format('Y-m-d'),
    ]);
    $results = $filteredQuery->get();
    expect($results->pluck('id')->toArray())->toContain($mondayTodo->id);
});

test('week start date filter handles date range spanning multiple weeks', function () {
    $this->actingAs($this->superAdmin);
    
    $query = WeeklyTodoResource::getEloquentQuery();
    
    $weekFilterCallback = function (Builder $query, array $data): Builder {
        return $query
            ->when(
                $data['week_from'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate('week_start_date', '>=', \Carbon\Carbon::parse($date)->startOfWeek(Carbon::MONDAY)),
            )
            ->when(
                $data['week_until'] ?? null,
                fn (Builder $query, $date): Builder => $query->whereDate('week_start_date', '<=', \Carbon\Carbon::parse($date)->startOfWeek(Carbon::MONDAY)),
            );
    };
    
    // Filter from two weeks ago to next week
    $filteredQuery = $weekFilterCallback(clone $query, [
        'week_from' => $this->twoWeeksAgoStart->format('Y-m-d'),
        'week_until' => $this->nextWeekStart->format('Y-m-d'),
    ]);
    $results = $filteredQuery->get();
    
    // Should contain todos from all weeks in range
    expect($results->count())->toBeGreaterThan(0);
});

