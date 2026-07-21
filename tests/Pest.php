<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

function projectMemberWith(\App\Models\Project $project, array $permissions = []): \App\Models\User
{
    $user = \App\Models\User::factory()->create();
    $project->members()->attach($user->id);
    foreach ($permissions as $permission) {
        $user->givePermissionTo($permission);
    }

    return $user;
}

// Lives here, not in a test file: it is used from more than one file, and under
// --parallel those files can land in different processes.
function createAttendance(\App\Models\Hris\Employee $employee, int $days, string $startDate = '2025-10-01'): void
{
    $daysCreated = 0;
    $date = \Carbon\Carbon::parse($startDate);
    while ($daysCreated < $days) {
        if (! $date->isWeekend()) {
            \App\Models\Hris\AttendanceLog::create([
                'employee_id' => $employee->id,
                'date' => $date->copy(),
                'status' => \App\Models\Hris\AttendanceLog::STATUS_PRESENT,
                'mode' => \App\Models\Hris\AttendanceLog::MODE_OFFICE,
                'source' => \App\Models\Hris\AttendanceLog::SOURCE_WEB,
            ]);
            $daysCreated++;
        }
        $date->addDay();
    }
}
