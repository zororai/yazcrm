<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match our records.']);
        }

        if (!Auth::user()->is_active) {
            Auth::logout();
            return back()->withErrors(['email' => 'Account is disabled.']);
        }

        Auth::user()->update(['last_login_at' => now()]);
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    public function showChangePassword(): Response
    {
        return Inertia::render('Auth/ChangePassword');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $request->user()->update([
            'password'              => Hash::make($data['password']),
            'must_change_password'  => false,
        ]);

        return redirect()->route('dashboard')->with('success', 'Password updated.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
