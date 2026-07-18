<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeroImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HeroImageController extends Controller
{
    public function index()
    {
        $images = HeroImage::orderBy('sort_order')->orderBy('id')->get();

        return response()->json(['data' => $images]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['image', 'max:4096'],
        ]);

        $nextOrder = (HeroImage::max('sort_order') ?? 0) + 1;

        $created = [];

        foreach ($data['images'] as $file) {
            $created[] = HeroImage::create([
                'path' => $file->store('hero-images', 'public'),
                'sort_order' => $nextOrder++,
            ]);
        }

        return response()->json(['data' => $created], 201);
    }

    public function moveUp(HeroImage $heroImage)
    {
        $previous = HeroImage::where('sort_order', '<', $heroImage->sort_order)
            ->orderByDesc('sort_order')
            ->first();

        if ($previous) {
            [$heroImage->sort_order, $previous->sort_order] = [$previous->sort_order, $heroImage->sort_order];
            $heroImage->save();
            $previous->save();
        }

        return response()->json(['data' => HeroImage::orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function moveDown(HeroImage $heroImage)
    {
        $next = HeroImage::where('sort_order', '>', $heroImage->sort_order)
            ->orderBy('sort_order')
            ->first();

        if ($next) {
            [$heroImage->sort_order, $next->sort_order] = [$next->sort_order, $heroImage->sort_order];
            $heroImage->save();
            $next->save();
        }

        return response()->json(['data' => HeroImage::orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function destroy(HeroImage $heroImage)
    {
        Storage::disk('public')->delete($heroImage->path);

        $heroImage->delete();

        return response()->json(['message' => 'Hero image removed.']);
    }
}
