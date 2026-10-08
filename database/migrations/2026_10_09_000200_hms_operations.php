<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payments, pricing, folio, housekeeping, reviews, galleries and the extra
 * booking fields they need (walk-in guests, tax/service, no-show, expiry).
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Booking status becomes a plain string (adds NoShow / Expired).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_status_check');
            DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_status_check');
            DB::statement('ALTER TABLE bookings ALTER COLUMN user_id DROP NOT NULL');
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('status', 20)->default('Pending')->change();
            $table->string('source', 20)->default('online');          // online | walkin
            $table->string('guest_name')->nullable();                  // walk-in without account
            $table->string('guest_email')->nullable();
            $table->decimal('room_total', 15, 2)->default(0);          // nights x nightly rate
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('service_charge', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->foreignId('promo_code_id')->nullable();
            $table->timestamp('payment_due_at')->nullable();
            $table->foreignUuid('created_by')->nullable();
            $table->index(['status', 'payment_due_at']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            // Encrypted door code no longer fits in varchar(255) comfortably.
            $table->text('door_passcode')->nullable()->change();
            $table->string('status', 20)->default('Active')->change();
        });

        Schema::table('hotels', function (Blueprint $table) {
            $table->decimal('tax_percent', 5, 2)->default(11);
            $table->decimal('service_percent', 5, 2)->default(10);
            $table->unsignedSmallInteger('payment_deadline_hours')->default(24);
            $table->string('bank_accounts', 1000)->nullable();        // shown for manual transfer
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('clock_in_radius_m')->nullable();  // null = no geofence
            $table->string('clock_in_ips', 500)->nullable();           // comma separated, null = any
            $table->unsignedSmallInteger('late_grace_minutes')->default(10);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('method', 20);                 // transfer | tripay | cash
            $table->string('channel', 50)->nullable();    // Tripay channel code (BRIVA, QRIS...)
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('pending'); // pending | paid | rejected | expired | failed
            $table->string('reference')->nullable()->unique();  // Tripay reference
            $table->string('merchant_ref')->nullable()->unique();
            $table->string('checkout_url', 500)->nullable();
            $table->string('proof_path')->nullable();     // manual transfer receipt
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignUuid('verified_by')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['booking_id', 'status']);
            $table->index('status');
        });

        Schema::create('rate_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->nullable()->constrained()->cascadeOnDelete(); // null = all types
            $table->string('name');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('days_of_week')->nullable();     // [0..6], 0 = Sunday; null = every day
            $table->string('adjust_type', 10);            // percent | amount | fixed
            $table->decimal('value', 15, 2);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'start_date', 'end_date']);
        });

        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('type', 10);                   // percent | amount
            $table->decimal('value', 15, 2);
            $table->decimal('max_discount', 15, 2)->nullable();
            $table->unsignedSmallInteger('min_nights')->default(1);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('booking_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('category', 30);               // minibar | laundry | restaurant | late_checkout | extend | other
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->foreignUuid('created_by')->nullable();
            $table->timestamps();
            $table->index('booking_id');
        });

        Schema::create('room_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_room_id')->constrained('rooms');
            $table->foreignId('to_room_id')->constrained('rooms');
            $table->string('reason')->nullable();
            $table->foreignUuid('moved_by')->nullable();
            $table->timestamps();
        });

        Schema::create('housekeeping_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('assigned_to')->nullable();
            $table->string('type', 20)->default('checkout');   // checkout | stayover | inspection | maintenance
            $table->string('status', 20)->default('pending');  // pending | in_progress | done
            $table->string('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignUuid('created_by')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['room_id', 'status']);
            $table->index('assigned_to');
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->index(['is_published', 'created_at']);
        });

        Schema::create('room_type_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['room_type_id', 'sort']);
        });

        Schema::create('hotel_images', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('caption')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Existing bookings: split total into room_total so invoices add up.
        DB::table('bookings')->where('room_total', 0)->update(['room_total' => DB::raw('total_price')]);
    }

    public function down(): void
    {
        foreach (['hotel_images', 'room_type_images', 'reviews', 'housekeeping_tasks', 'room_moves', 'booking_charges', 'promo_codes', 'rate_rules', 'payments'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('hotels', fn (Blueprint $t) => $t->dropColumn([
            'tax_percent', 'service_percent', 'payment_deadline_hours', 'bank_accounts',
            'latitude', 'longitude', 'clock_in_radius_m', 'clock_in_ips', 'late_grace_minutes',
        ]));

        Schema::table('bookings', function (Blueprint $t) {
            $t->dropIndex(['status', 'payment_due_at']);
            $t->dropColumn([
                'source', 'guest_name', 'guest_email', 'room_total', 'discount', 'service_charge', 'tax',
                'promo_code_id', 'payment_due_at', 'created_by',
            ]);
        });
    }
};
