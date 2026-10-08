<?php

namespace App\Http\Controllers\Backend\Hms;

use App\Http\Controllers\Backend\Hms\Concerns\RespondsWithAlert;
use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Models\RateRule;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Seasonal / weekend pricing rules and promo codes (one "Harga & Promo" page). */
class RateRuleController extends Controller
{
    use RespondsWithAlert;

    public function index()
    {
        return view('backend.hms.pricing.index', [
            'rules' => RateRule::with('roomType:id,name')->orderByDesc('is_active')->orderByDesc('priority')->get(),
            'promos' => PromoCode::orderByDesc('is_active')->latest()->get(),
            'roomTypes' => RoomType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        RateRule::create($this->validated($request));

        return $this->done($request, 'Aturan harga ditambahkan.');
    }

    public function update(Request $request, RateRule $rateRule)
    {
        $rateRule->update($this->validated($request));

        return $this->done($request, 'Aturan harga diperbarui.');
    }

    public function destroy(Request $request, RateRule $rateRule)
    {
        $rateRule->delete();

        return $this->done($request, 'Aturan harga dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'room_type_id' => ['nullable', 'exists:room_types,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'between:0,6'],
            'adjust_type' => ['required', Rule::in(array_keys(RateRule::TYPES))],
            'value' => ['required', 'numeric', 'between:-100000000,100000000'],
            'priority' => ['nullable', 'integer', 'between:0,1000'],
        ], [], [
            'name' => 'nama aturan', 'room_type_id' => 'tipe kamar', 'start_date' => 'tanggal mulai',
            'end_date' => 'tanggal akhir', 'days_of_week' => 'hari', 'adjust_type' => 'jenis', 'value' => 'nilai',
        ]);

        $data['days_of_week'] = array_values(array_map('intval', $data['days_of_week'] ?? [])) ?: null;
        $data['priority'] = $data['priority'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
