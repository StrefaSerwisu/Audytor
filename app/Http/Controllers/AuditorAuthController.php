<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuditorAuthController extends Controller
{
    public function create(): View
    {
        return view('auditor.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::attempt([...$credentials, 'active' => true], $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Nieprawidlowy email lub haslo.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('auditor.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
