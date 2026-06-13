<?php

namespace Database\Seeders;

use App\Models\Hris\Department;
use App\Models\Hris\LeaveType;
use App\Models\Hris\Position;
use Illuminate\Database\Seeder;

class HrisSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDepartments();
        $this->seedPositions();
        $this->seedLeaveTypes();
        $this->call(FinanceRolePermissionSeeder::class);
        $this->call(PaymentBundleRolePermissionSeeder::class);
        $this->call(RecruitmentScreeningPermissionSeeder::class);
        $this->call(ProjectDocumentPermissionSeeder::class);
    }

    protected function seedDepartments(): void
    {
        $departments = [
            ['code' => 'HR', 'name' => 'Human Resources'],
            ['code' => 'ENG', 'name' => 'Engineering'],
            ['code' => 'FIN', 'name' => 'Finance'],
            ['code' => 'MKT', 'name' => 'Marketing'],
        ];

        foreach ($departments as $department) {
            Department::firstOrCreate(
                ['code' => $department['code']],
                ['name' => $department['name']]
            );
        }
    }

    protected function seedPositions(): void
    {
        $positions = [
            ['code' => 'STAFF', 'title' => 'Staff', 'level' => 'staff'],
            ['code' => 'LEAD', 'title' => 'Team Lead', 'level' => 'lead'],
            ['code' => 'MAN', 'title' => 'Manager', 'level' => 'manager'],
            ['code' => 'DIR', 'title' => 'Director', 'level' => 'director'],
        ];

        foreach ($positions as $position) {
            Position::firstOrCreate(
                ['code' => $position['code']],
                ['title' => $position['title'], 'level' => $position['level']]
            );
        }
    }

    protected function seedLeaveTypes(): void
    {
        $leaveTypes = [
            [
                'code' => 'ANNUAL',
                'name' => 'Annual Leave',
                'description' => '12 days per year after 1 year tenure, no carry over.',
                'default_annual_quota' => 12,
                'carry_forward_allowed' => false,
                'requires_approval' => true,
                'requires_attachment' => false,
                'is_special' => false,
            ],
            [
                'code' => 'SICK',
                'name' => 'Sick Leave',
                'description' => 'Sick leave is unlimited but requires a doctor\'s letter / medical certificate.',
                'default_annual_quota' => null,
                'carry_forward_allowed' => false,
                'requires_approval' => true,
                'requires_attachment' => true,
                'is_special' => false,
            ],
            [
                'code' => 'MATERNITY',
                'name' => 'Maternity Leave',
                'description' => '6 weeks before and 6 weeks after delivery.',
                'default_annual_quota' => null,
                'carry_forward_allowed' => false,
                'requires_approval' => true,
                'requires_attachment' => true,
                'gender_constraint' => 'female',
                'is_special' => true,
                'rules' => [
                    'pre_weeks' => 6,
                    'post_weeks' => 6,
                ],
            ],
            [
                'code' => 'PATERNITY',
                'name' => 'Paternity Leave',
                'description' => '3 days leave for fathers-to-be.',
                'default_annual_quota' => 3,
                'carry_forward_allowed' => false,
                'requires_approval' => true,
                'requires_attachment' => false,
                'gender_constraint' => 'male',
                'is_special' => true,
            ],
            [
                'code' => 'UNPAID',
                'name' => 'Unpaid Leave',
                'description' => 'Unpaid leave, available only when annual leave quota is exhausted. Days will prorate basic salary.',
                'default_annual_quota' => 0,
                'carry_forward_allowed' => false,
                'requires_approval' => true,
                'requires_attachment' => false,
                'is_special' => false,
            ],
        ];

        foreach ($leaveTypes as $leaveType) {
            LeaveType::firstOrCreate(
                ['code' => $leaveType['code']],
                $leaveType
            );
        }
    }
}
