<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('leave_types')->updateOrInsert(
            ['code' => 'UNPAID'],
            [
                'name' => 'Unpaid Leave',
                'description' => 'Unpaid leave, available only when annual leave quota is exhausted. Days will prorate basic salary.',
                'requires_approval' => true,
                'requires_attachment' => false,
                'default_annual_quota' => 0,
                'carry_forward_allowed' => false,
                'gender_constraint' => null,
                'is_special' => false,
                'rules' => null,
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('leave_types')->where('code', 'UNPAID')->delete();
    }
};
