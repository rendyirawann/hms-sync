<?php

namespace App\Http\Controllers\Backend\Hms;

use App\Http\Controllers\Backend\Hms\Concerns\RespondsWithAlert;
use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromoCodeController extends Controller
{
    use RespondsWithAlert;

    public function store(Request $request)
    {
        PromoCode::create($this->validated($request));

        return $this->done($request, 'Kode promo ditambahkan.');
    }

    public function update(Request $request, PromoCode $promoCode)
    {
        $promoCode->update($this->validated($request, $promoCode));

        return $this->done($request, 'Kode promo diperbarui.');
    }

    public function destroy(Request $request, PromoCode $promoCode)
    {
        if ($promoCode->used_count > 0) {
            $promoCode->update(['is_active' => false]);

            return $this->done($request, 'Kode promo sudah pernah dipakai, jadi dinonaktifkan (bukan dihapus).');
        }

        $promoCode->delete();

        return $this->done($request, 'Kode promo dihapus.');
    }

    private function validated(Request $request, ?PromoCode $promo = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->code))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('promo_codes')->ignore($promo?->id)],
            'type' => ['required', Rule::in(['percent', 'amount'])],
            'value' => ['required', 'numeric', 'min:0', $request->type === 'percent' ? 'max:100' : 'max:100000000'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'min_nights' => ['nullable', 'integer', 'min:1', 'max:60'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
        ], ['code.regex' => 'Kode hanya boleh huruf, angka, - dan _.'], [
            'code' => 'kode', 'type' => 'jenis', 'value' => 'nilai', 'max_discount' => 'maks. diskon',
            'min_nights' => 'minimal malam', 'valid_from' => 'berlaku dari', 'valid_until' => 'berlaku sampai', 'max_uses' => 'kuota',
        ]);

        $data['min_nights'] = $data['min_nights'] ?? 1;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
