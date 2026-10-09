<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Tenancy\Actions\RegisterBusiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterBusinessRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showRegister()
    {
        return view('auth.account-registration');
    }

    public function register(RegisterBusinessRequest $request, RegisterBusiness $register): RedirectResponse
    {
        $user = $register($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('onboarding.index');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);

        // Disabled accounts fail with the same generic message as bad credentials.
        $attempt = Auth::attempt([...$credentials, 'disabled_at' => null], $request->boolean('remember'));

        if (! $attempt) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        $request->session()->regenerate();

        $home = $request->user()->isSuperAdmin() ? route('admin.index') : route('dashboard');

        return redirect()->intended($home);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
