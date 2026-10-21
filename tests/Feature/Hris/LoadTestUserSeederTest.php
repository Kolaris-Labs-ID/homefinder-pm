<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\User;
use Database\Seeders\LoadTestUserSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);
    putenv('LOADTEST_PASSWORD=correct-horse-battery');
    putenv('LOADTEST_USERS=3');
});

afterEach(function () {
    putenv('LOADTEST_PASSWORD');
    putenv('LOADTEST_USERS');
});

it('refuses to seed without a strong LOADTEST_PASSWORD', function () {
    putenv('LOADTEST_PASSWORD=short');

    (new LoadTestUserSeeder)->run();
})->throws(RuntimeException::class, 'LOADTEST_PASSWORD');

it('seeds members with tagged employees, idempotently', function () {
    (new LoadTestUserSeeder)->run();
    (new LoadTestUserSeeder)->run();

    $users = User::where('email', 'like', 'loadtest%@example.com')->orderBy('email')->get();

    expect($users)->toHaveCount(3)
        ->and($users->pluck('email')->all())->toBe([
            'loadtest001@example.com', 'loadtest002@example.com', 'loadtest003@example.com',
        ])
        ->and($users->every(fn ($u) => $u->hasRole('member')))->toBeTrue()
        ->and(Hash::check('correct-horse-battery', $users->first()->password))->toBeTrue()
        ->and(Employee::where('metadata->seed_batch', LoadTestUserSeeder::BATCH)->count())->toBe(3)
        ->and($users->first()->employee->nik)->toBe('9999000000000001');
});

it('lets a seeded user log in and reach the HRIS API', function () {
    (new LoadTestUserSeeder)->run();

    $token = $this->postJson('/api/auth/login', [
        'email' => 'loadtest001@example.com',
        'password' => 'correct-horse-battery',
        'device_name' => 'loadtest',
    ])->assertOk()->json('token');

    $this->withToken($token)->getJson('/api/hris/attendance/wfh-quota')->assertOk();
});

it('tears down users, employees, tokens, roles and attendance, and nothing else', function () {
    (new LoadTestUserSeeder)->run();
    $bystander = Employee::factory()->create();

    $seeded = User::where('email', 'loadtest001@example.com')->first();
    $seeded->createToken('loadtest');
    AttendanceLog::create([
        'employee_id' => $seeded->employee->id,
        'date' => today()->toDateString(),
        'mode' => AttendanceLog::MODE_OFFICE,
        'check_in_time' => now(),
        'status' => AttendanceLog::STATUS_PRESENT,
    ]);

    $this->artisan('loadtest:teardown', ['--force' => true])->assertSuccessful();

    expect(User::where('email', 'like', 'loadtest%@example.com')->count())->toBe(0)
        ->and(Employee::where('metadata->seed_batch', LoadTestUserSeeder::BATCH)->count())->toBe(0)
        ->and(AttendanceLog::count())->toBe(0)
        ->and(DB::table('personal_access_tokens')->count())->toBe(0)
        ->and(DB::table('model_has_roles')->where('model_id', $seeded->id)->count())->toBe(0)
        ->and(Employee::find($bystander->id))->not->toBeNull();

    $this->artisan('loadtest:teardown', ['--force' => true])
        ->expectsOutputToContain('Nothing to tear down')
        ->assertSuccessful();
});
