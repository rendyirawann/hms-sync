<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            // Vendor lock identifier (TTLock lockId) for this room's door.
            $table->string('lock_id', 50)->nullable();
            // Vendor id of the guest code currently programmed on the lock,
            // needed to delete it at check-out.
            $table->string('lock_code_ref', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rooms', fn (Blueprint $table) => $table->dropColumn(['lock_id', 'lock_code_ref']));
    }
};
