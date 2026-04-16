<?php

namespace Tests\Feature\Api\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\TrainingRecord;
use App\Models\User;
use App\Services\Hris\PgpService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PgpSummaryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function pgp_summary_requires_authentication(): void
    {
        $this->getJson('/api/hris/pgp/summary')->assertUnauthorized();
    }

    #[Test]
    public function pgp_summary_requires_employee_profile(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/hris/pgp/summary')->assertNotFound();
    }

    #[Test]
    public function pgp_summary_without_query_uses_server_current_quarter(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-16 12:00:00', 'UTC'));

        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        TrainingRecord::factory()
            ->forEmployee($employee)
            ->freeTraining()
            ->approvedInQuarter(2, 2026, TrainingRecord::PGP_FREE_TRAINING)
            ->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/hris/pgp/summary')
            ->assertOk()
            ->assertJsonPath('data.year', 2026)
            ->assertJsonPath('data.quarter', 2)
            ->assertJsonPath('data.points', TrainingRecord::PGP_FREE_TRAINING)
            ->assertJsonPath('data.period_start', '2026-04-01')
            ->assertJsonPath('data.target', PgpService::PGP_TARGET)
            ->assertJsonPath('data.records_count', 1)
            ->assertJsonStructure([
                'data' => [
                    'quarter',
                    'year',
                    'period_start',
                    'period_end',
                    'points',
                    'target',
                    'progress_percent',
                    'projected_reward_idr',
                    'records_count',
                ],
            ]);
    }

    #[Test]
    public function pgp_summary_sums_multiple_approved_records_in_quarter(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        TrainingRecord::factory()
            ->forEmployee($employee)
            ->freeTraining()
            ->approvedInQuarter(1, 2026, TrainingRecord::PGP_FREE_TRAINING)
            ->create();
        TrainingRecord::factory()
            ->forEmployee($employee)
            ->freeTraining()
            ->approvedInQuarter(1, 2026, TrainingRecord::PGP_FREE_TRAINING)
            ->create();

        Sanctum::actingAs($user);

        $expected = TrainingRecord::PGP_FREE_TRAINING * 2;

        $this->getJson('/api/hris/pgp/summary?year=2026&quarter=1')
            ->assertOk()
            ->assertJsonPath('data.points', $expected)
            ->assertJsonPath('data.records_count', 2);
    }

    #[Test]
    public function pgp_summary_ignores_records_from_other_quarters(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        TrainingRecord::factory()
            ->forEmployee($employee)
            ->freeTraining()
            ->approvedInQuarter(1, 2026, TrainingRecord::PGP_FREE_TRAINING)
            ->create();
        TrainingRecord::factory()
            ->forEmployee($employee)
            ->freeTraining()
            ->approvedInQuarter(2, 2026, TrainingRecord::PGP_FREE_TRAINING)
            ->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/hris/pgp/summary?year=2026&quarter=1')
            ->assertOk()
            ->assertJsonPath('data.points', TrainingRecord::PGP_FREE_TRAINING)
            ->assertJsonPath('data.records_count', 1);
    }

    #[Test]
    public function pgp_summary_ignores_other_employees_records(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $other = Employee::factory()->create();

        TrainingRecord::factory()
            ->forEmployee($employee)
            ->freeTraining()
            ->approvedInQuarter(3, 2026, TrainingRecord::PGP_FREE_TRAINING)
            ->create();
        TrainingRecord::factory()
            ->forEmployee($other)
            ->freeTraining()
            ->approvedInQuarter(3, 2026, TrainingRecord::PGP_FREE_TRAINING)
            ->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/hris/pgp/summary?year=2026&quarter=3')
            ->assertOk()
            ->assertJsonPath('data.points', TrainingRecord::PGP_FREE_TRAINING)
            ->assertJsonPath('data.records_count', 1);
    }

    #[Test]
    public function pgp_summary_excludes_unapproved_training_records(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        TrainingRecord::factory()
            ->forEmployee($employee)
            ->freeTraining()
            ->approvedInQuarter(4, 2026, TrainingRecord::PGP_FREE_TRAINING)
            ->create();
        TrainingRecord::factory()
            ->forEmployee($employee)
            ->completed()
            ->freeTraining()
            ->create([
                'pgp_points' => null,
                'approved_at' => null,
            ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/hris/pgp/summary?year=2026&quarter=4')
            ->assertOk()
            ->assertJsonPath('data.points', TrainingRecord::PGP_FREE_TRAINING)
            ->assertJsonPath('data.records_count', 1);
    }

    #[Test]
    public function pgp_summary_projected_reward_at_target_matches_pgp_service(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $pgp = app(PgpService::class);

        TrainingRecord::factory()
            ->forEmployee($employee)
            ->freeTraining()
            ->approvedInQuarter(1, 2026, PgpService::PGP_TARGET)
            ->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/hris/pgp/summary?year=2026&quarter=1')
            ->assertOk()
            ->assertJsonPath('data.points', PgpService::PGP_TARGET)
            ->assertJsonPath('data.progress_percent', 100)
            ->assertJsonPath('data.projected_reward_idr', $pgp->calculateReward(PgpService::PGP_TARGET));
    }

    #[Test]
    public function pgp_summary_returns_zero_reward_below_target(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        TrainingRecord::factory()
            ->forEmployee($employee)
            ->freeTraining()
            ->approvedInQuarter(1, 2026, TrainingRecord::PGP_FREE_TRAINING)
            ->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/hris/pgp/summary?year=2026&quarter=1')
            ->assertOk()
            ->assertJsonPath('data.projected_reward_idr', 0);
    }

    #[Test]
    public function pgp_summary_validates_quarter_range(): void
    {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->getJson('/api/hris/pgp/summary?year=2026&quarter=0')
            ->assertUnprocessable();

        $this->getJson('/api/hris/pgp/summary?year=2026&quarter=5')
            ->assertUnprocessable();
    }

    #[Test]
    public function pgp_summary_validates_year_range(): void
    {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->getJson('/api/hris/pgp/summary?year=1999&quarter=1')
            ->assertUnprocessable();
    }

    #[Test]
    public function pgp_summary_requires_year_when_quarter_is_provided(): void
    {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->getJson('/api/hris/pgp/summary?quarter=2')
            ->assertUnprocessable();
    }

    #[Test]
    public function pgp_summary_requires_quarter_when_year_is_provided(): void
    {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->getJson('/api/hris/pgp/summary?year=2026')
            ->assertUnprocessable();
    }
}
