<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('late_pay_compensation_partial_payments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('late_pay_compensation_item_id');
            $table->foreign('late_pay_compensation_item_id', 'lpc_partial_item_fk')
                ->references('id')
                ->on('late_pay_compensation_items')
                ->cascadeOnDelete();

            $table->date('payment_date');
            $table->decimal('paid_percent', 5, 2);

            $table->timestamps();

            $table->unique(
                ['late_pay_compensation_item_id', 'payment_date'],
                'lpc_partial_item_date_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('late_pay_compensation_partial_payments');
    }
};
