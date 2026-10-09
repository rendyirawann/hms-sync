<?php

namespace App\Models;

use App\Support\HotelContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Hotel extends Model
{
    public const APPROVAL_LABELS = [
        'pending' => 'Menunggu persetujuan',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
    ];

    protected $fillable = [
        'name', 'slug', 'address', 'city', 'province', 'description', 'phone', 'email', 'rating', 'image', 'status',
        'check_in_time', 'check_out_time', 'tax_percent', 'service_percent', 'payment_deadline_hours',
        'latitude', 'longitude', 'clock_in_radius_m', 'clock_in_ips', 'late_grace_minutes',
        'approval_status', 'rejection_reason', 'approved_at', 'owner_id', 'commission_percent',
        'payout_bank', 'payout_account_number', 'payout_account_name',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
        'status' => 'boolean',
        'tax_percent' => 'decimal:2',
        'service_percent' => 'decimal:2',
        'commission_percent' => 'decimal:2',
        'payment_deadline_hours' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'clock_in_radius_m' => 'integer',
        'late_grace_minutes' => 'integer',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Hotel $hotel) {
            if (blank($hotel->slug)) {
                $hotel->slug = static::uniqueSlug($hotel->name);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'hotel';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /**
     * The hotel this request works on (staff: their own hotel; Superadmin:
     * the hotel picked in the platform panel). Null on the public site.
     */
    public static function current(): ?self
    {
        return app(HotelContext::class)->hotel();
    }

    /** Hotels shown on the marketplace and open for bookings. */
    public function scopeListed(Builder $query): Builder
    {
        return $query->where('approval_status', 'approved')->where('status', true);
    }

    public function isBookable(): bool
    {
        return $this->approval_status === 'approved' && $this->status;
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function roomTypes()
    {
        return $this->hasMany(RoomType::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function staff()
    {
        return $this->hasMany(User::class);
    }

    public function images()
    {
        return $this->hasMany(HotelImage::class)->orderBy('sort');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function settlements()
    {
        return $this->hasMany(Settlement::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }

    public function getCoverUrlAttribute(): string
    {
        return $this->image_url ?? asset('assets/media/landing/hero-landing.png');
    }

    public function getApprovalLabelAttribute(): string
    {
        return $this->status || $this->approval_status !== 'approved'
            ? (self::APPROVAL_LABELS[$this->approval_status] ?? $this->approval_status)
            : 'Dinonaktifkan';
    }

    public function getApprovalBadgeAttribute(): string
    {
        return match (true) {
            $this->approval_status === 'pending' => 'warning',
            $this->approval_status === 'rejected' => 'danger',
            ! $this->status => 'secondary',
            default => 'success',
        };
    }
}
