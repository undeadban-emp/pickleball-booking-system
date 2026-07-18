<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Email + password login for any role. Staff/admin accounts may submit
     * `code` instead of `password` — a daily rotating code for shared
     * front-desk devices, so staff don't retype a password every shift.
     *
     * That code is derived only from today's date (no per-user secret), so
     * it must never be accepted for customer accounts, and it only carries
     * any weight because /api already requires the X-Jocos-Token app header.
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['nullable', 'string'],
            'code' => ['nullable', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::where('email', $data['email'])->first();

        $authenticated = false;

        if ($user && filled($data['password'] ?? null)) {
            $authenticated = Hash::check($data['password'], $user->password);
        } elseif ($user && filled($data['code'] ?? null) && in_array($user->role, ['admin', 'staff'], true)) {
            $authenticated = hash_equals($this->dailyStaffCode(), $data['code']);
        }

        if (! $user || ! $authenticated) {
            throw ValidationException::withMessages([
                'email' => ['Those credentials do not match our records.'],
            ]);
        }

        $token = $user->createToken($data['device_name'] ?? 'flutter-app')->plainTextToken;

        return response()->json([
            'data' => $user,
            'token' => $token,
        ]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => 'customer',
        ]);

        event(new Registered($user));

        $token = $user->createToken($data['device_name'] ?? 'flutter-app')->plainTextToken;

        return response()->json([
            'data' => $user,
            'token' => $token,
        ], 201);
    }

    public function me(Request $request)
    {
        return response()->json(['data' => $request->user()]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function deviceToken(Request $request)
    {
        $data = $request->validate([
            'fcm_token' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $request->user()->deviceTokens()->updateOrCreate(
            ['fcm_token' => $data['fcm_token']],
            ['device_name' => $data['device_name'] ?? null, 'last_used_at' => now()]
        );

        return response()->json(['message' => 'Device registered.']);
    }

    protected function dailyStaffCode(): string
    {
        $result = now()->toDateString();

        for ($i = 0; $i < 100; $i++) {
            $result = md5($result);
        }

        return $result;
    }
}
