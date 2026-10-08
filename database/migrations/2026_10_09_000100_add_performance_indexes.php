<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL does not index foreign keys on its own, so every relation and
 * the status/date columns the HMS screens filter on get an index here.
 */
return new class extends Migration
{
    /** Columns searched with ILIKE '%term%' — only a trigram index helps those. */
    private array $trigram = [
        'bookings' => ['booking_code'],
        'users' => ['name', 'email'],
        'rooms' => ['room_number'],
    ];

    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);    // "Reservasi Saya"
            $table->index(['status', 'created_at']);     // admin list filtered by status
            $table->index('check_in_date');              // arrivals / date filter
            $table->index('created_at');                 // admin list, latest first
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->index('booking_id');
            $table->index(['room_id', 'status']);        // "is this room occupied?"
            $table->index('status');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->index(['room_type_id', 'status']);   // availability + assignable rooms
            $table->index('status');
        });

        Schema::table('room_types', function (Blueprint $table) {
            $table->index('hotel_id');
            $table->index(['is_active', 'base_price']);  // public listing
        });

        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->index('date');                       // weekly roster
            $table->index('shift_id');
            $table->index(['user_id', 'status']);        // running shift lookup
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->index('created_at');                 // dashboard trend + log list
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

            foreach ($this->trigram as $table => $columns) {
                foreach ($columns as $column) {
                    DB::statement("CREATE INDEX IF NOT EXISTS {$table}_{$column}_trgm ON {$table} USING gin ({$column} gin_trgm_ops)");
                }
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach ($this->trigram as $table => $columns) {
                foreach ($columns as $column) {
                    DB::statement("DROP INDEX IF EXISTS {$table}_{$column}_trgm");
                }
            }
        }

        Schema::table('activity_log', fn (Blueprint $t) => $t->dropIndex(['created_at']));
        Schema::table('employee_shifts', function (Blueprint $t) {
            $t->dropIndex(['date']);
            $t->dropIndex(['shift_id']);
            $t->dropIndex(['user_id', 'status']);
        });
        Schema::table('room_types', function (Blueprint $t) {
            $t->dropIndex(['hotel_id']);
            $t->dropIndex(['is_active', 'base_price']);
        });
        Schema::table('rooms', function (Blueprint $t) {
            $t->dropIndex(['room_type_id', 'status']);
            $t->dropIndex(['status']);
        });
        Schema::table('reservations', function (Blueprint $t) {
            $t->dropIndex(['booking_id']);
            $t->dropIndex(['room_id', 'status']);
            $t->dropIndex(['status']);
        });
        Schema::table('bookings', function (Blueprint $t) {
            $t->dropIndex(['user_id', 'created_at']);
            $t->dropIndex(['status', 'created_at']);
            $t->dropIndex(['check_in_date']);
            $t->dropIndex(['created_at']);
        });
    }
};
