<x-auditor.layout :title="'Logowanie - Audytor IT'" :auth-layout="true">
    <div class="login-wrap">
        <form class="login-card stack" method="post" action="{{ route('auditor.login.store') }}">
            @csrf

            <div class="stack">
                <div class="brand">
                    <strong>Audytor IT</strong>
                    <span>Logowanie audytora</span>
                </div>
                <p class="muted" style="margin: 0;">Uzyj konta Global IT przypisanego do audytu.</p>
            </div>

            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif

            <label>
                Email
                <input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            </label>

            <label>
                Haslo
                <input name="password" type="password" autocomplete="current-password" required>
            </label>

            <label class="inline-check">
                <input name="remember" type="checkbox" value="1">
                Zapamietaj mnie
            </label>

            <button type="submit">Zaloguj</button>
        </form>
    </div>
</x-auditor.layout>
