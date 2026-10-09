<?php

namespace App\Http\Controllers\Backend\Platform;

use App\Http\Controllers\Backend\Hms\Concerns\RespondsWithAlert;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveHotelContext;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\User;
use App\Notifications\HotelStatusNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Marketplace operator: onboard, approve, suspend and configure hotels. */
class HotelController extends Controller
{
    use RespondsWithAlert;

    public function index(Request $request)
    {
        $hotels = Hotel::query()
            ->with('owner:id,name,email')
            ->withCount(['rooms', 'roomTypes'])
            ->when($request->filled('status'), function ($q) use ($request) {
                match ($request->status) {
                    'pending', 'rejected' => $q->where('approval_status', $request->status),
                    'active' => $q->listed(),
                    'inactive' => $q->where('approval_status', 'approved')->where('status', false),
                    default => null,
                };
            })
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'ilike', '%' . $request->q . '%')
                ->orWhere('city', 'ilike', '%' . $request->q . '%')))
            ->orderByRaw("case approval_status when 'pending' then 0 else 1 end")
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // Bookings and revenue of the last 30 days per hotel, in one query.
        $stats = Booking::withoutGlobalScopes()
            ->whereIn('hotel_id', $hotels->pluck('id'))
            ->whereIn('status', ['Paid', 'Confirmed', 'Completed'])
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('hotel_id')
            ->selectRaw('hotel_id, count(*) as bookings, sum(total_price) as revenue')
            ->get()
            ->keyBy('hotel_id');

        $counts = Hotel::selectRaw("approval_status, count(*) as total")->groupBy('approval_status')->pluck('total', 'approval_status');

        return view('backend.platform.hotels.index', compact('hotels', 'stats', 'counts'));
    }

    public function show(Hotel $hotel)
    {
        $hotel->load(['owner', 'staff.roles', 'roomTypes' => fn ($q) => $q->withoutGlobalScopes()->withCount('rooms')]);
        $settlements = $hotel->settlements()->withoutGlobalScopes()->latest('period_end')->limit(6)->get();

        return view('backend.platform.hotels._show', compact('hotel', 'settlements'));
    }

    /** Platform creates a hotel together with its owner/admin account (already approved). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'commission_percent' => ['required', 'numeric', 'between:0,100'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'owner_password' => ['required', Password::min(8)],
        ], [], [
            'name' => 'nama hotel', 'city' => 'kota', 'address' => 'alamat', 'commission_percent' => 'komisi',
            'owner_name' => 'nama pemilik', 'owner_email' => 'email pemilik', 'owner_password' => 'password pemilik',
        ]);

        $hotel = DB::transaction(function () use ($data) {
            $hotel = Hotel::create([
                'name' => $data['name'],
                'city' => $data['city'],
                'address' => $data['address'],
                'phone' => $data['phone'] ?? null,
                'commission_percent' => $data['commission_percent'],
                'approval_status' => 'approved',
                'approved_at' => now(),
                'status' => true,
            ]);

            $owner = User::create([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => $data['owner_password'],
                'hotel_id' => $hotel->id,
                'is_active' => true,
            ]);
            $owner->forceFill(['email_verified_at' => now()])->save();
            $owner->syncRoles(['admin']);
            $hotel->update(['owner_id' => $owner->id]);

            return $hotel;
        });

        return $this->done($request, 'Hotel ' . $hotel->name . ' dibuat. Pemilik bisa login dengan ' . $data['owner_email'] . '.');
    }

    public function update(Request $request, Hotel $hotel)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'alpha_dash', 'max:150', Rule::unique('hotels', 'slug')->ignore($hotel->id)],
            'city' => ['required', 'string', 'max:100'],
            'commission_percent' => ['required', 'numeric', 'between:0,100'],
            'payout_bank' => ['nullable', 'string', 'max:50'],
            'payout_account_number' => ['nullable', 'string', 'max:50'],
            'payout_account_name' => ['nullable', 'string', 'max:100'],
        ], [], ['commission_percent' => 'komisi', 'payout_bank' => 'bank', 'payout_account_number' => 'nomor rekening', 'payout_account_name' => 'nama pemilik rekening']);

        $hotel->update($data);

        return $this->done($request, 'Data hotel ' . $hotel->name . ' diperbarui.');
    }

    public function approve(Request $request, Hotel $hotel)
    {
        if ($hotel->approval_status === 'approved') {
            return $this->fail($request, 'Hotel ini sudah disetujui.');
        }

        $hotel->update(['approval_status' => 'approved', 'approved_at' => now(), 'status' => true, 'rejection_reason' => null]);
        $hotel->owner?->notify(new HotelStatusNotification($hotel, 'approved'));

        return $this->done($request, $hotel->name . ' disetujui dan sekarang tampil di website.');
    }

    public function reject(Request $request, Hotel $hotel)
    {
        $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'alasan']);

        $hotel->update(['approval_status' => 'rejected', 'status' => false, 'rejection_reason' => $request->reason]);
        $hotel->owner?->notify(new HotelStatusNotification($hotel, 'rejected'));

        return $this->done($request, 'Pendaftaran ' . $hotel->name . ' ditolak.');
    }

    /** Suspend / reactivate an approved hotel (hidden from the site, no new bookings). */
    public function toggle(Request $request, Hotel $hotel)
    {
        if ($hotel->approval_status !== 'approved') {
            return $this->fail($request, 'Hanya hotel yang sudah disetujui yang bisa diaktifkan/nonaktifkan.');
        }

        $hotel->update(['status' => ! $hotel->status]);

        return $this->done($request, $hotel->name . ($hotel->status ? ' diaktifkan kembali.' : ' dinonaktifkan dan disembunyikan dari website.'));
    }

    /** Work inside one hotel's panel (rooms, bookings, staff ...). */
    public function enter(Request $request, Hotel $hotel)
    {
        $request->session()->put(ResolveHotelContext::SESSION_KEY, $hotel->id);

        return redirect()->route('hms.bookings.index')->with('success', 'Sekarang mengelola ' . $hotel->name . '.');
    }

    public function leave(Request $request)
    {
        $request->session()->forget(ResolveHotelContext::SESSION_KEY);

        return redirect()->route('platform.hotels.index');
    }
}
