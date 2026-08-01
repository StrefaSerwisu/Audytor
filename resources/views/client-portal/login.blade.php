<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102337">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/audytor-it-icon.svg" type="image/svg+xml">
    <script src="/pwa.js" defer></script>
    <title>Portal klienta - Audytor IT</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f5f7fb; color: #172033; font-family: Arial, sans-serif; }
        .card { width: min(420px, 100%); padding: 24px; border: 1px solid #dce3ee; border-radius: 8px; background: #fff; box-shadow: 0 22px 60px rgb(15 23 42 / 14%); }
        h1 { margin: 0 0 8px; }
        p { margin: 0 0 18px; color: #657085; }
        form, label { display: grid; gap: 12px; }
        input { min-height: 42px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; }
        button { min-height: 42px; border: 0; border-radius: 6px; background: #0f766e; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        .error { margin-bottom: 14px; padding: 12px; border: 1px solid #fecaca; border-radius: 8px; background: #fef2f2; color: #991b1b; }
    </style>
</head>
<body>
    <main class="card">
        <h1>Portal klienta</h1>
        <p>Logowanie do raportow audytowych Global IT.</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('client.login.store') }}">
            @csrf
            <label>
                Email
                <input name="email" type="email" value="{{ old('email') }}" required autofocus>
            </label>
            <label>
                Haslo
                <input name="password" type="password" required>
            </label>
            <button type="submit">Zaloguj</button>
        </form>
    </main>
</body>
</html>
