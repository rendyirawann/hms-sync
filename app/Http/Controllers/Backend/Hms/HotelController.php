<?php

namespace App\Http\Controllers\Backend\Hms;

use App\Http\Controllers\Backend\Hms\Concerns\RespondsWithAlert;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HotelController extends Controller
{
    use RespondsWithAlert;

    public function edit()
    {
        return view('backend.hms.hotel.edit', ['hotel' => Hotel::current()]);
    }

    public function update(Request $request)
    {
        $hotel = Hotel::current();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'check_in_time' => ['required', 'date_format:H:i'],
            'check_out_time' => ['required', 'date_format:H:i'],
            'image' => ['nullable', 'image', 'max:2048'],
            'tax_percent' => ['required', 'numeric', 'between:0,100'],
            'service_percent' => ['required', 'numeric', 'between:0,100'],
            'payment_deadline_hours' => ['required', 'integer', 'between:0,720'],
            'bank_accounts' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'clock_in_radius_m' => ['nullable', 'integer', 'between:10,100000'],
            'clock_in_ips' => ['nullable', 'string', 'max:500'],
            'late_grace_minutes' => ['required', 'integer', 'between:0,240'],
        ], [], [
            'tax_percent' => 'pajak', 'service_percent' => 'service charge', 'payment_deadline_hours' => 'batas waktu bayar',
            'bank_accounts' => 'rekening bank', 'clock_in_radius_m' => 'radius clock-in', 'clock_in_ips' => 'IP clock-in',
            'late_grace_minutes' => 'toleransi terlambat',
            'name' => 'nama hotel', 'address' => 'alamat', 'phone' => 'telepon',
            'check_in_time' => 'jam check-in', 'check_out_time' => 'jam check-out', 'image' => 'foto',
        ]);

        if ($request->hasFile('image')) {
            if ($hotel->image) {
                Storage::disk('public')->delete($hotel->image);
            }
            $data['image'] = $request->file('image')->store('hotel', 'public');
        } else {
            unset($data['image']);
        }

        $data['rating'] = $data['rating'] ?? 0;
        $hotel->update($data);

        return $this->done($request, 'Profil hotel berhasil diperbarui.');
    }
}
