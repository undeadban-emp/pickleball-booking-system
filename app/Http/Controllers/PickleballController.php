<?php

namespace App\Http\Controllers;

class PickleballController extends Controller
{
    // BRAND|MODEL, uppercase, matching Build.BRAND|Build.MODEL on the device.
    // TODO: fill in with the real allowed device strings.
    const ALLOWED_DEVICES = [
        // 'SAMSUNG|SM-A325F',
    ];

    public function appVersion(string $code, string $device)
    {
        if (! $this->authorizeDevice($code, $device)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $path = storage_path('app/PICKLEBALL/version.json');

        if (! file_exists($path)) {
            return response()->json(['version_code' => 1, 'version_name' => '1.0.0', 'release_notes' => '']);
        }

        return response()->json(json_decode(file_get_contents($path), true));
    }

    public function appDownload(string $code, string $device)
    {
        if (! $this->authorizeDevice($code, $device)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $path = storage_path('app/PICKLEBALL/app-debug.apk');

        if (! file_exists($path)) {
            return response()->json(['error' => 'APK not found'], 404);
        }

        return response()->download($path, 'pickleball.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Content-Disposition' => 'attachment; filename="pickleball.apk"',
        ]);
    }

    private function authorizeDevice(string $code, string $device): bool
    {
        if (strlen($code) !== 32) {
            return false;
        }

        if (! in_array(strtoupper(trim($device)), self::ALLOWED_DEVICES, true)) {
            return false;
        }

        $result = now()->toDateString();

        for ($i = 0; $i < 100; $i++) {
            $result = md5($result);
        }

        return $code === $result;
    }
}
