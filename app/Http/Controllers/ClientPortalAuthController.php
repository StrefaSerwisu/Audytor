<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ClientPortalAuthController extends Controller
{
    public function create(): View
    {
        return view('client-portal.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::attempt($credentials, (bool) $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Nieprawidlowe dane logowania.'])
                ->onlyInput('email');
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->active || ! $user->hasRole(UserRole::Client) || ! $user->client_id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'To konto nie ma dostepu do portalu klienta.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('client.portal.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('client.login');
    }
}
