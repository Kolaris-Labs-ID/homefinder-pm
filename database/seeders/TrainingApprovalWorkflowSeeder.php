<?php

namespace Database\Seeders;

use App\Models\Hris\ApprovalWorkflow;
use Illuminate\Database\Seeder;

class TrainingApprovalWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        ApprovalWorkflow::firstOrCreate(
            ['for_module' => ApprovalWorkflow::MODULE_TRAINING],
            [
                'name' => 'Training Record Approval',
                'steps' => [
                    ['order' => 1, 'type' => 'role', 'value' => 'hr-admin'],
                ],
                'is_active' => true,
            ]
        );
    }
}
