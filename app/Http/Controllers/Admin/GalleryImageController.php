<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GalleryImage;
use App\Support\WebpImageStorer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GalleryImageController extends Controller
{
    public function index()
    {
        $images = GalleryImage::orderBy('sort_order')->orderBy('id')->get();

        return view('admin.gallery-images.index', ['images' => $images]);
    }

    /**
     * Uploads one image per request - the admin page's JS sends a separate
     * request per selected file, one at a time, so a large batch upload
     * doesn't hit request size/timeout limits and progress is visible.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'max:8192'],
        ]);

        $nextOrder = (GalleryImage::max('sort_order') ?? 0) + 1;

        $image = GalleryImage::create([
            'path' => WebpImageStorer::store($data['image'], 'gallery-images'),
            'sort_order' => $nextOrder,
        ]);

        return response()->json([
            'id' => $image->id,
            'url' => $image->url(),
        ]);
    }

    public function moveUp(GalleryImage $galleryImage)
    {
        $previous = GalleryImage::where('sort_order', '<', $galleryImage->sort_order)
            ->orderByDesc('sort_order')
            ->first();

        if ($previous) {
            [$galleryImage->sort_order, $previous->sort_order] = [$previous->sort_order, $galleryImage->sort_order];
            $galleryImage->save();
            $previous->save();
        }

        return back();
    }

    public function moveDown(GalleryImage $galleryImage)
    {
        $next = GalleryImage::where('sort_order', '>', $galleryImage->sort_order)
            ->orderBy('sort_order')
            ->first();

        if ($next) {
            [$galleryImage->sort_order, $next->sort_order] = [$next->sort_order, $galleryImage->sort_order];
            $galleryImage->save();
            $next->save();
        }

        return back();
    }

    public function destroy(GalleryImage $galleryImage)
    {
        Storage::disk('public')->delete($galleryImage->path);

        $galleryImage->delete();

        return back()->with('status', 'Album image removed.');
    }
}
