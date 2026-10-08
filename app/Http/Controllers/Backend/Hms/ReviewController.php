<?php

namespace App\Http\Controllers\Backend\Hms;

use App\Http\Controllers\Backend\Hms\Concerns\RespondsWithAlert;
use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    use RespondsWithAlert;

    public function index(Request $request)
    {
        $reviews = Review::with(['user:id,name', 'roomType:id,name', 'booking:id,booking_code'])
            ->when($request->filled('rating'), fn ($q) => $q->where('rating', $request->rating))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = Review::selectRaw('count(*) as total, avg(rating) as average')->first();

        return view('backend.hms.reviews.index', compact('reviews', 'stats'));
    }

    public function toggle(Request $request, Review $review)
    {
        $review->update(['is_published' => ! $review->is_published]);

        return $this->done($request, $review->is_published ? 'Ulasan ditampilkan di website.' : 'Ulasan disembunyikan.');
    }
}
