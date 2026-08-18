<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'locale' => ['required', 'in:fr,en'],
        ]);
        $user = User::create($data);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->to($this->destination($request));
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => __('auth.failed')])->withInput($request->only('email', 'next'));
        }
        $request->session()->regenerate();

        return redirect()->intended($this->destination($request));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function locale(Request $request)
    {
        $data = $request->validate(['locale' => ['required', 'in:fr,en']]);
        if ($request->user()) {
            $request->user()->update($data);
        }

        return back()->with('success', __('ui.profile_saved'))->withCookie(cookie('pokeline_locale', $data['locale'], 60 * 24 * 365));
    }

    private function destination(Request $request): string
    {
        return $request->string('next')->value() === 'online'
            ? route('battle.lobby')
            : route('home');
    }
}
