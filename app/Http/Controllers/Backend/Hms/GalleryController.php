<?php

namespace App\Http\Controllers\Backend\Hms;

use App\Http\Controllers\Backend\Hms\Concerns\RespondsWithAlert;
use App\Http\Controllers\Controller;
use App\Models\HotelImage;
use App\Models\RoomType;
use App\Models\RoomTypeImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Photo galleries: several photos per room type plus hotel facility photos. */
class GalleryController extends Controller
{
    use RespondsWithAlert;

    private const MAX_PER_UPLOAD = 10;

    public function index()
    {
        return view('backend.hms.gallery.index', [
            'roomTypes' => RoomType::with('images')->orderBy('name')->get(['id', 'name', 'image']),
            'hotelImages' => HotelImage::orderBy('sort')->get(),
        ]);
    }

    public function storeRoomType(Request $request, RoomType $roomType)
    {
        $this->validateUpload($request);
        $sort = (int) $roomType->images()->max('sort');

        foreach ($request->file('photos') as $file) {
            $roomType->images()->create(['path' => $file->store('room-types/gallery', 'public'), 'sort' => ++$sort]);
        }

        return $this->done($request, count($request->file('photos')) . ' foto ditambahkan ke ' . $roomType->name . '.');
    }

    public function destroyRoomType(Request $request, RoomTypeImage $image)
    {
        Storage::disk('public')->delete($image->path);
        $image->delete();

        return $this->done($request, 'Foto dihapus.');
    }

    public function storeHotel(Request $request)
    {
        $this->validateUpload($request);
        $request->validate(['caption' => ['nullable', 'string', 'max:100']]);
        $sort = (int) HotelImage::max('sort');

        foreach ($request->file('photos') as $file) {
            HotelImage::create([
                'path' => $file->store('hotel/gallery', 'public'),
                'caption' => $request->input('caption'),
                'sort' => ++$sort,
            ]);
        }

        return $this->done($request, count($request->file('photos')) . ' foto fasilitas ditambahkan.');
    }

    public function destroyHotel(Request $request, HotelImage $image)
    {
        Storage::disk('public')->delete($image->path);
        $image->delete();

        return $this->done($request, 'Foto dihapus.');
    }

    private function validateUpload(Request $request): void
    {
        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:' . self::MAX_PER_UPLOAD],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ], [], ['photos' => 'foto', 'photos.*' => 'foto']);
    }
}
