<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrasi data dari user_id ke ticket_users
        $tickets = DB::table('tickets')
            ->whereNotNull('user_id')
            ->select('id', 'user_id')
            ->get();

        foreach ($tickets as $ticket) {
            DB::table('ticket_users')->insert([
                'ticket_id' => $ticket->id,
                'user_id' => $ticket->user_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan data dari ticket_users ke user_id
        $ticketUsers = DB::table('ticket_users')
            ->select('ticket_id', 'user_id')
            ->get();

        foreach ($ticketUsers as $ticketUser) {
            DB::table('tickets')
                ->where('id', $ticketUser->ticket_id)
                ->update(['user_id' => $ticketUser->user_id]);
        }
    }
};