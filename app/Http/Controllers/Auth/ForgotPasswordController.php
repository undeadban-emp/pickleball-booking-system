<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCodeMail;
use App\Models\Email;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ForgotPasswordController extends Controller
{
    public function showRequestForm()
    {
        return view('auth.forgot-password');
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $email = $data['email'];
        $throttleKey = 'password-reset-send:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Please wait {$seconds} seconds before requesting another code.",
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        PasswordResetCode::where('email', $email)
            ->where('is_used', false)
            ->update(['is_used' => true]);

        $code = (string) random_int(100000, 999999);

        PasswordResetCode::create([
            'email' => $email,
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->deliverCode($email, $code);

        $request->session()->put('password_reset_email', $email);
        $request->session()->put('password_reset_verified', false);

        return redirect()->route('password.verify')
            ->with('status', 'A verification code has been sent to your email.');
    }

    public function showVerifyForm()
    {
        if (! session('password_reset_email')) {
            return redirect()->route('password.request');
        }

        return view('auth.verify-code');
    }

    public function verifyCode(Request $request): RedirectResponse
    {
        $email = session('password_reset_email');

        if (! $email) {
            return redirect()->route('password.request');
        }

        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $throttleKey = 'password-reset-verify:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'code' => "Too many attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        RateLimiter::hit($throttleKey, 600);

        $reset = PasswordResetCode::where('email', $email)
            ->where('code', $data['code'])
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $reset) {
            throw ValidationException::withMessages([
                'code' => 'This code is invalid or has expired.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->put('password_reset_verified', true);

        return redirect()->route('password.reset');
    }

    public function showResetForm()
    {
        if (! session('password_reset_email') || ! session('password_reset_verified')) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password');
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $email = session('password_reset_email');

        if (! $email || ! session('password_reset_verified')) {
            return redirect()->route('password.request');
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $user = User::where('email', $email)->firstOrFail();
        $user->update(['password' => Hash::make($data['password'])]);

        PasswordResetCode::where('email', $email)
            ->where('is_used', false)
            ->update(['is_used' => true]);

        $request->session()->forget(['password_reset_email', 'password_reset_verified']);

        return redirect()->route('login')->with('status', 'Your password has been reset. You can now log in.');
    }

    protected function deliverCode(string $email, string $code): void
    {
        $log = Email::create([
            'email' => $email,
            'message' => "Your password reset code is {$code}. It expires in 10 minutes.",
            'status' => 'pending',
        ]);

        try {
            Mail::to($email)->send(new PasswordResetCodeMail($code));
            $log->update(['status' => 'sent']);
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed']);
        }
    }
}
