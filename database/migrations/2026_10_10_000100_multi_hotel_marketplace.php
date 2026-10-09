<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Turns the single-hotel system into a marketplace: every operational row
 * belongs to a hotel, staff belong to one hotel, hotels go through an
 * approval flow and the platform settles collected money to each hotel.
 */
return new class extends Migration
{
    /** Tables that get a hotel_id (filled from their parent for existing rows). */
    private array $scoped = [
        'rooms', 'shifts', 'employee_shifts', 'bookings', 'reservations', 'payments',
        'rate_rules', 'housekeeping_tasks', 'reviews', 'hotel_images',
    ];

    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique();
            $table->string('city', 100)->nullable()->index();
            $table->string('province', 100)->nullable();
            // pending -> approved | rejected; approved hotels can be suspended.
            $table->string('approval_status', 20)->default('approved')->index();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('owner_id')->nullable();
            $table->decimal('commission_percent', 5, 2)->default(10);
            // Where the platform pays the hotel's share.
            $table->string('payout_bank', 50)->nullable();
            $table->string('payout_account_number', 50)->nullable();
            $table->string('payout_account_name', 100)->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('hotel_id')->nullable()->index();
        });

        foreach ($this->scoped as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('hotel_id')->nullable()->index();
            });
        }

        Schema::table('rooms', function (Blueprint $table) {
            // Room 101 can exist in every hotel.
            $table->dropUnique(['room_number']);
        });

        Schema::table('promo_codes', function (Blueprint $table) {
            // null = platform-wide promo funded by the platform.
            $table->foreignId('hotel_id')->nullable()->index();
            $table->dropUnique(['code']);
            $table->unique(['hotel_id', 'code']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->foreignId('settlement_id')->nullable()->index();
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('bookings_count')->default(0);
            $table->decimal('room_revenue', 15, 2)->default(0);      // (room_total - discount) of settled stays
            $table->decimal('gross_amount', 15, 2)->default(0);      // grand total billed to guests
            $table->decimal('collected_by_platform', 15, 2)->default(0);
            $table->decimal('collected_by_hotel', 15, 2)->default(0);
            $table->decimal('commission_amount', 15, 2)->default(0);
            $table->decimal('payout_amount', 15, 2)->default(0);     // collected_by_platform - commission (negative = hotel owes)
            $table->string('status', 20)->default('pending');         // pending | paid
            $table->timestamp('paid_at')->nullable();
            $table->string('reference')->nullable();
            $table->string('note')->nullable();
            $table->foreignUuid('created_by')->nullable();
            $table->timestamps();
            $table->index(['hotel_id', 'period_start']);
        });

        $this->backfill();

        Schema::table('rooms', fn (Blueprint $table) => $table->unique(['hotel_id', 'room_number']));
    }

    /** Existing data belongs to the one hotel that existed before. */
    private function backfill(): void
    {
        foreach (DB::table('hotels')->whereNull('slug')->get(['id', 'name']) as $hotel) {
            $base = Str::slug($hotel->name) ?: 'hotel';
            $slug = $base;
            $i = 2;
            while (DB::table('hotels')->where('slug', $slug)->exists()) {
                $slug = $base . '-' . $i++;
            }
            DB::table('hotels')->where('id', $hotel->id)->update(['slug' => $slug, 'approved_at' => now()]);
        }

        $first = DB::table('hotels')->orderBy('id')->value('id');
        if (! $first) {
            return;
        }

        DB::statement('UPDATE rooms SET hotel_id = rt.hotel_id FROM room_types rt WHERE rooms.room_type_id = rt.id AND rooms.hotel_id IS NULL');
        DB::statement('UPDATE bookings SET hotel_id = rt.hotel_id FROM room_types rt WHERE bookings.room_type_id = rt.id AND bookings.hotel_id IS NULL');
        DB::statement('UPDATE reservations SET hotel_id = b.hotel_id FROM bookings b WHERE reservations.booking_id = b.id AND reservations.hotel_id IS NULL');
        DB::statement('UPDATE payments SET hotel_id = b.hotel_id FROM bookings b WHERE payments.booking_id = b.id AND payments.hotel_id IS NULL');
        DB::statement('UPDATE reviews SET hotel_id = b.hotel_id FROM bookings b WHERE reviews.booking_id = b.id AND reviews.hotel_id IS NULL');
        DB::statement('UPDATE housekeeping_tasks SET hotel_id = r.hotel_id FROM rooms r WHERE housekeeping_tasks.room_id = r.id AND housekeeping_tasks.hotel_id IS NULL');

        foreach (['shifts', 'employee_shifts', 'rate_rules', 'hotel_images', 'bookings', 'rooms'] as $table) {
            DB::table($table)->whereNull('hotel_id')->update(['hotel_id' => $first]);
        }

        // Hotel staff (admin / Pegawai) belong to the existing hotel.
        $staffIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('roles.name', ['admin', 'Pegawai'])
            ->pluck('model_has_roles.model_id');
        DB::table('users')->whereIn('id', $staffIds)->whereNull('hotel_id')->update(['hotel_id' => $first]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');

        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn(['commission_percent', 'settlement_id']));
        Schema::table('promo_codes', function (Blueprint $t) {
            $t->dropUnique(['hotel_id', 'code']);
            $t->dropColumn('hotel_id');
            $t->unique('code');
        });
        foreach (array_merge($this->scoped, ['users']) as $name) {
            Schema::table($name, fn (Blueprint $t) => $t->dropColumn('hotel_id'));
        }
        Schema::table('hotels', fn (Blueprint $t) => $t->dropColumn([
            'slug', 'city', 'province', 'approval_status', 'rejection_reason', 'approved_at', 'owner_id',
            'commission_percent', 'payout_bank', 'payout_account_number', 'payout_account_name',
        ]));
    }
};
