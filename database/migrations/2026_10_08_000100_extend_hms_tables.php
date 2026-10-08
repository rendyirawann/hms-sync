<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->time('check_in_time')->default('14:00');
            $table->time('check_out_time')->default('12:00');
        });

        Schema::table('room_types', function (Blueprint $table) {
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedSmallInteger('guests')->default(1);
            $table->string('guest_phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->index(['room_type_id', 'check_in_date', 'check_out_date']);
        });

        // One roster entry per employee per day.
        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->unique(['user_id', 'date']);
        });

        // Accounts that existed before email activation was introduced were
        // created by an administrator — treat them as already activated.
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);

        // Customers must be able to sign up from the public site.
        DB::table('settings')->updateOrInsert(
            ['key' => 'allow_registration'],
            ['value' => '1', 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        Schema::table('employee_shifts', fn (Blueprint $table) => $table->dropUnique(['user_id', 'date']));

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['room_type_id', 'check_in_date', 'check_out_date']);
            $table->dropColumn(['guests', 'guest_phone', 'notes', 'cancel_reason']);
        });

        Schema::table('room_types', fn (Blueprint $table) => $table->dropColumn(['image', 'is_active']));
        Schema::table('hotels', fn (Blueprint $table) => $table->dropColumn(['check_in_time', 'check_out_time']));
    }
};
