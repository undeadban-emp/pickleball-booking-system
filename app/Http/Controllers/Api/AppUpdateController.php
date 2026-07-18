<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use Illuminate\Http\Request;

class AppUpdateController extends Controller
{
    /**
     * Latest published Android build, for the Flutter app's in-app update check.
     */
    public function latest(Request $request)
    {
        $release = AppRelease::where('is_active', true)
            ->orderByDesc('version_code')
            ->first();

        if (! $release) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'version' => $release->version,
                'version_code' => $release->version_code,
                'release_notes' => $release->release_notes,
                'file_size' => $release->file_size,
                'download_url' => asset('storage/'.$release->apk_path),
            ],
        ]);
    }
}
