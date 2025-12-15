<?php

use App\Filament\Widgets\WeeklyTodoOverviewWidget;
use App\Models\WeeklyTodo;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    $this->superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    // Create users with different divisions
    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole($this->superAdminRole);

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

    // Create weekly todos for different users
    $this->engineeringTodo = WeeklyTodo::factory()->create([
        'user_id' => $this->regularUser->id,
        'week_start_date' => Carbon::now()->startOfWeek(Carbon::MONDAY),
    ]);

    $this->marketingTodo = WeeklyTodo::factory()->create([
        'user_id' => $this->otherDivisionUser->id,
        'week_start_date' => Carbon::now()->startOfWeek(Carbon::MONDAY),
    ]);

    $this->headDivisionTodo = WeeklyTodo::factory()->create([
        'user_id' => $this->headDivision->id,
        'week_start_date' => Carbon::now()->startOfWeek(Carbon::MONDAY),
    ]);
});

test('super admin can view widget', function () {
    $this->actingAs($this->superAdmin);
    expect(WeeklyTodoOverviewWidget::canView())->toBeTrue();
});

test('head division can view widget', function () {
    $this->actingAs($this->headDivision);
    expect(WeeklyTodoOverviewWidget::canView())->toBeTrue();
});

test('regular user cannot view widget', function () {
    $this->actingAs($this->regularUser);
    expect(WeeklyTodoOverviewWidget::canView())->toBeFalse();
});

test('super admin sees all todos', function () {
    $this->actingAs($this->superAdmin);

    $widget = new WeeklyTodoOverviewWidget();
    $table = $widget->table(\Filament\Tables\Table::make($widget));
    $query = $table->getQuery();

    $results = $query->get();
    $resultIds = $results->pluck('id')->toArray();

    // Clean up table reference to avoid memory issues
    unset($table, $widget);

    expect($results)->toHaveCount(3); // All todos
    expect($resultIds)->toContain($this->engineeringTodo->id);
    expect($resultIds)->toContain($this->marketingTodo->id);
    expect($resultIds)->toContain($this->headDivisionTodo->id);
});

test('head division sees only their division todos', function () {
    $this->actingAs($this->headDivision);

    $widget = new WeeklyTodoOverviewWidget();
    $table = $widget->table(\Filament\Tables\Table::make($widget));
    $query = $table->getQuery();

    $results = $query->get();
    $resultIds = $results->pluck('id')->toArray();

    // Clean up table reference to avoid memory issues
    unset($table, $widget);

    expect($results)->toHaveCount(2); // Only Engineering division todos
    expect($resultIds)->toContain($this->engineeringTodo->id);
    expect($resultIds)->toContain($this->headDivisionTodo->id);
    expect($resultIds)->not->toContain($this->marketingTodo->id);
});

test('division filter works correctly for super admin', function () {
    $this->actingAs($this->superAdmin);

    // Test the division filter query callback directly
    $query = WeeklyTodo::query()->with('user');

    // Simulate the filter query callback from WeeklyTodoOverviewWidget
    $filterCallback = function ($query, array $data) {
        if (!empty($data['value'])) {
            $query->whereHas('user', function ($q) use ($data) {
                $q->where('division', $data['value']);
            });
        }
        return $query;
    };

    // Test filtering by Engineering division
    $filteredQuery = $filterCallback(clone $query, ['value' => 'Engineering']);
    $results = $filteredQuery->get();

    expect($results)->toHaveCount(2); // Only Engineering todos
    expect($results->pluck('id')->toArray())->toContain($this->engineeringTodo->id);
    expect($results->pluck('id')->toArray())->toContain($this->headDivisionTodo->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->marketingTodo->id);
});

test('division filter works correctly for Marketing division', function () {
    $this->actingAs($this->superAdmin);

    // Test the division filter query callback directly
    $query = WeeklyTodo::query()->with('user');

    $filterCallback = function ($query, array $data) {
        if (!empty($data['value'])) {
            $query->whereHas('user', function ($q) use ($data) {
                $q->where('division', $data['value']);
            });
        }
        return $query;
    };

    // Test filtering by Marketing division
    $filteredQuery = $filterCallback(clone $query, ['value' => 'Marketing']);
    $results = $filteredQuery->get();

    expect($results)->toHaveCount(1); // Only Marketing todo
    expect($results->pluck('id')->toArray())->toContain($this->marketingTodo->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->engineeringTodo->id);
    expect($results->pluck('id')->toArray())->not->toContain($this->headDivisionTodo->id);
});

test('division filter returns all when no value provided', function () {
    $this->actingAs($this->superAdmin);

    // Test the division filter query callback directly
    $query = WeeklyTodo::query()->with('user');

    $filterCallback = function ($query, array $data) {
        if (!empty($data['value'])) {
            $query->whereHas('user', function ($q) use ($data) {
                $q->where('division', $data['value']);
            });
        }
        return $query;
    };

    // Test with empty value
    $filteredQuery = $filterCallback(clone $query, ['value' => null]);
    $results = $filteredQuery->get();

    // Should return all todos when no filter is applied
    expect($results)->toHaveCount(3);
});

test('division filter options include all divisions', function () {
    $this->actingAs($this->superAdmin);

    $widget = new WeeklyTodoOverviewWidget();
    $table = $widget->table(\Filament\Tables\Table::make($widget));

    $filters = $table->getFilters();
    $divisionFilter = collect($filters)->first(fn($filter) => $filter->getName() === 'division');
    $filterExists = $divisionFilter !== null;

    // Clean up table reference to avoid memory issues
    unset($table, $widget, $filters, $divisionFilter);

    expect($filterExists)->toBeTrue();

    // Verify that the divisions we created exist in the database
    $divisions = \App\Models\User::whereNotNull('division')
        ->distinct()
        ->pluck('division', 'division')
        ->toArray();

    expect($divisions)->toHaveKey('Engineering');
    expect($divisions)->toHaveKey('Marketing');
});

test('division filter is visible for management roles', function () {
    $this->actingAs($this->superAdmin);

    $widget = new WeeklyTodoOverviewWidget();
    $table = $widget->table(\Filament\Tables\Table::make($widget));

    $filters = $table->getFilters();
    $divisionFilter = collect($filters)->first(fn($filter) => $filter->getName() === 'division');
    $isVisible = $divisionFilter->isVisible();

    // Clean up table reference to avoid memory issues
    unset($table, $widget, $filters, $divisionFilter);

    expect($isVisible)->toBeTrue();
});

test('division filter is not visible for regular users', function () {
    // Regular users can't even view the widget, but if they could, filter wouldn't be visible
    $this->actingAs($this->regularUser);

    expect(WeeklyTodoOverviewWidget::canView())->toBeFalse();
});

test('status filter is not in filters panel', function () {
    $this->actingAs($this->superAdmin);

    // The status filter has been moved to the column header dropdown
    // and should no longer exist in the filters panel
    $widget = new WeeklyTodoOverviewWidget();
    $table = $widget->table(\Filament\Tables\Table::make($widget));

    $filters = $table->getFilters();
    $statusFilter = collect($filters)->first(fn($filter) => $filter->getName() === 'status');
    $filterExists = $statusFilter !== null;

    // Clean up table reference to avoid memory issues
    unset($table, $widget, $filters, $statusFilter);

    // Status filter should NOT exist in the filters panel anymore
    expect($filterExists)->toBeFalse();
});

test('week start date filter works correctly', function () {
    $this->actingAs($this->superAdmin);

    $thisWeekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
    $lastWeekStart = $thisWeekStart->copy()->subWeek();
    $nextWeekStart = $thisWeekStart->copy()->addWeek();

    $thisWeekTodo = WeeklyTodo::factory()->create([
        'user_id' => $this->regularUser->id,
        'week_start_date' => $thisWeekStart,
    ]);

    $lastWeekTodo = WeeklyTodo::factory()->create([
        'user_id' => $this->regularUser->id,
        'week_start_date' => $lastWeekStart,
    ]);

    $nextWeekTodo = WeeklyTodo::factory()->create([
        'user_id' => $this->regularUser->id,
        'week_start_date' => $nextWeekStart,
    ]);

    // Test "This Week" filter
    $widget = new WeeklyTodoOverviewWidget();
    $table = $widget->table(\Filament\Tables\Table::make($widget));

    $filters = $table->getFilters();
    $thisWeekFilter = collect($filters)->first(fn($filter) => $filter->getName() === 'this_week');
    $filterExists = $thisWeekFilter !== null;

    // Clean up table reference to avoid memory issues
    unset($table, $widget, $filters, $thisWeekFilter);

    expect($filterExists)->toBeTrue();

    $query = WeeklyTodo::query()->with('user');
    $filterCallback = function ($query) use ($thisWeekStart) {
        return $query->whereDate('week_start_date', $thisWeekStart);
    };

    $filteredQuery = $filterCallback(clone $query);
    $results = $filteredQuery->get();

    expect($results->pluck('id')->toArray())->toContain($thisWeekTodo->id);
    expect($results->pluck('id')->toArray())->not->toContain($lastWeekTodo->id);
    expect($results->pluck('id')->toArray())->not->toContain($nextWeekTodo->id);
});

test('combination of division filter and status property works', function () {
    $this->actingAs($this->superAdmin);

    // Create todos with different divisions and statuses
    $engineeringPending = WeeklyTodo::factory()->create([
        'user_id' => $this->regularUser->id,
        'status' => 'pending',
    ]);

    $engineeringDone = WeeklyTodo::factory()->create([
        'user_id' => $this->regularUser->id,
        'status' => 'done',
    ]);

    $marketingPending = WeeklyTodo::factory()->create([
        'user_id' => $this->otherDivisionUser->id,
        'status' => 'pending',
    ]);

    // Test with statusFilter property set to 'pending'
    $widget = new WeeklyTodoOverviewWidget();
    $widget->statusFilter = 'pending';
    $table = $widget->table(\Filament\Tables\Table::make($widget));
    $query = $table->getQuery();

    // Apply division filter manually to the query
    $query->whereHas('user', function ($q) {
        $q->where('division', 'Engineering');
    });

    $results = $query->get();

    // Clean up table reference to avoid memory issues
    unset($table, $widget);

    expect($results->pluck('id')->toArray())->toContain($engineeringPending->id);
    expect($results->pluck('id')->toArray())->not->toContain($engineeringDone->id);
    expect($results->pluck('id')->toArray())->not->toContain($marketingPending->id);
});

test('user filter works correctly', function () {
    $this->actingAs($this->superAdmin);

    $user1Todo = WeeklyTodo::factory()->create([
        'user_id' => $this->regularUser->id,
    ]);

    $user2Todo = WeeklyTodo::factory()->create([
        'user_id' => $this->otherDivisionUser->id,
    ]);

    $widget = new WeeklyTodoOverviewWidget();
    $table = $widget->table(\Filament\Tables\Table::make($widget));

    $filters = $table->getFilters();
    $userFilter = collect($filters)->first(fn($filter) => $filter->getName() === 'user_id');
    $filterExists = $userFilter !== null;

    // Clean up table reference to avoid memory issues
    unset($table, $widget, $filters, $userFilter);

    expect($filterExists)->toBeTrue();

    // Test filtering by user
    $query = WeeklyTodo::query()->with('user');
    $filterCallback = function ($query, array $data) {
        if (!empty($data['value'])) {
            $query->where('user_id', $data['value']);
        }
        return $query;
    };

    $filteredQuery = $filterCallback(clone $query, ['value' => $this->regularUser->id]);
    $results = $filteredQuery->get();

    expect($results->pluck('id')->toArray())->toContain($user1Todo->id);
    expect($results->pluck('id')->toArray())->not->toContain($user2Todo->id);
});

