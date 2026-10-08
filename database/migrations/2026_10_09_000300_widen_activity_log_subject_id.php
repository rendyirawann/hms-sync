<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * activity_log.subject_id was a UUID column, but HMS models (bookings,
 * reservations, ...) use integer keys. Widen it to varchar so both UUID
 * users and integer HMS records can be audited.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE varchar(36) USING subject_id::text');
        } else {
            Schema::table('activity_log', fn ($table) => $table->string('subject_id', 36)->nullable()->change());
        }
    }

    public function down(): void
    {
        // Integer subject ids cannot be converted back to UUID; intentionally left as varchar.
    }
};
