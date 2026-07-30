<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ResumesPendingBooking;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class RegisteredUserController extends Controller
{
    use ResumesPendingBooking;

    public function create()
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ], [
            'phone.regex' => 'Enter a valid PH mobile number, e.g. 09171234567 or +639171234567.',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'role' => 'customer',
        ]);

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return $this->resumePendingBooking()
            ?? redirect('/')->with('status', 'Welcome to Kitchen Line! Your account is ready.');
    }
}
