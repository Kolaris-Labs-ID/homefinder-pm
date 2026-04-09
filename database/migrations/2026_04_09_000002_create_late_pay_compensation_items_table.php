<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('late_pay_compensation_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('late_pay_compensation_batch_id');
            $table->foreign('late_pay_compensation_batch_id', 'lpc_items_batch_fk')
                ->references('id')
                ->on('late_pay_compensation_batches')
                ->cascadeOnDelete();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->decimal('basic_salary_snapshot', 15, 2);
            $table->unsignedInteger('days_late');
            $table->decimal('rate_percent', 5, 2)->default(1.00);
            $table->decimal('total_amount', 15, 2)->default(0);

            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_holder')->nullable();
            $table->boolean('has_bank_warning')->default(false);

            $table->enum('status', ['pending', 'approved', 'excluded', 'paid'])->default('pending');

            $table->text('hr_notes')->nullable();
            $table->text('finance_notes')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->unique(
                ['late_pay_compensation_batch_id', 'employee_id'],
                'lpc_batch_employee_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('late_pay_compensation_items');
    }
};
