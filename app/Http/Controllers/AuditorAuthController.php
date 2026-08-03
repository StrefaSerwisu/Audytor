<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\AuditLogService;
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
            AuditLogService::record('auth.login_failed', metadata: [
                'email' => $credentials['email'],
                'portal' => 'auditor',
            ]);

            return back()
                ->withErrors(['email' => 'Nieprawidlowy email lub haslo.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        AuditLogService::record('auth.login_succeeded', $user, metadata: ['portal' => 'auditor']);

        return redirect()->intended(route('auditor.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        AuditLogService::record('auth.logout', $user, metadata: ['portal' => 'auditor']);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
