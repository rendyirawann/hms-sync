<?php

namespace App\Http\Controllers\Backend\Platform;

use App\Http\Controllers\Backend\Hms\Concerns\RespondsWithAlert;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Settlement;
use App\Services\SettlementService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    use RespondsWithAlert;

    /** Platform: all hotels, plus what is waiting to be settled this period. */
    public function index(Request $request, SettlementService $service)
    {
        [$from, $to] = $this->period($request);

        $settlements = Settlement::with('hotel:id,name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('period_end')
            ->paginate(25)
            ->withQueryString();

        $hotels = Hotel::where('approval_status', 'approved')->orderBy('name')->get();
        $previews = $hotels->mapWithKeys(fn (Hotel $h) => [$h->id => $service->preview($h, $from, $to)])
            ->filter(fn ($p) => $p['bookings_count'] > 0);

        $summary = [
            'unpaid' => (float) Settlement::where('status', 'pending')->where('payout_amount', '>', 0)->sum('payout_amount'),
            'owed' => (float) -Settlement::where('status', 'pending')->where('payout_amount', '<', 0)->sum('payout_amount'),
            'commission_month' => (float) Settlement::whereBetween('period_end', [now()->startOfMonth(), now()->endOfMonth()])->sum('commission_amount'),
        ];

        return view('backend.platform.settlements.index', compact('settlements', 'hotels', 'previews', 'from', 'to', 'summary'));
    }

    public function store(Request $request, SettlementService $service)
    {
        $data = $request->validate([
            'hotel_ids' => ['required', 'array', 'min:1'],
            'hotel_ids.*' => ['integer', 'exists:hotels,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start', 'before_or_equal:today'],
        ], [], ['hotel_ids' => 'hotel', 'period_start' => 'awal periode', 'period_end' => 'akhir periode']);

        $from = Carbon::parse($data['period_start']);
        $to = Carbon::parse($data['period_end']);
        $made = 0;

        foreach (Hotel::whereIn('id', $data['hotel_ids'])->get() as $hotel) {
            $made += $service->create($hotel, $from, $to, $request->user()->id) ? 1 : 0;
        }

        return $made
            ? $this->done($request, $made . ' setoran dibuat untuk periode ' . $from->format('d/m/Y') . ' - ' . $to->format('d/m/Y') . '.')
            : $this->fail($request, 'Tidak ada stay selesai yang belum disetor pada periode itu.');
    }

    public function show(Settlement $settlement)
    {
        $settlement->load(['hotel', 'bookings' => fn ($q) => $q->withoutGlobalScopes()->with(['payments' => fn ($p) => $p->withoutGlobalScopes(), 'roomType' => fn ($r) => $r->withoutGlobalScopes()])]);

        return view('backend.platform.settlements._show', compact('settlement'));
    }

    public function markPaid(Request $request, Settlement $settlement)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['reference' => 'nomor referensi transfer']);

        if ($settlement->status === 'paid') {
            return $this->fail($request, 'Setoran ini sudah ditandai dibayar.');
        }

        $settlement->update($data + ['status' => 'paid', 'paid_at' => now()]);

        return $this->done($request, 'Setoran ' . $settlement->hotel->name . ' ditandai sudah dibayar.');
    }

    /** Hotel admin: own settlements, read-only. */
    public function hotelIndex(Request $request, SettlementService $service)
    {
        [$from, $to] = $this->period($request);
        $hotel = Hotel::current();

        return view('backend.hms.settlements.index', [
            'hotel' => $hotel,
            'settlements' => Settlement::latest('period_end')->paginate(20),
            'preview' => $service->preview($hotel, $from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} default: last month */
    private function period(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->from) : now()->subMonthNoOverflow()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->to) : now()->subMonthNoOverflow()->endOfMonth();

        return [$from->startOfDay(), $to->startOfDay()];
    }
}
