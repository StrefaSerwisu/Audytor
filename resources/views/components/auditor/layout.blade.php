<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#102337">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/audytor-it-icon.svg" type="image/svg+xml">
    <script src="/pwa.js" defer></script>
    <script src="/offline-audit.js" defer></script>
    <title>{{ $title ?? 'Audytor IT' }}</title>
    <style>
        :root {
            --bg: #f5f7fb;
            --panel: #ffffff;
            --text: #172033;
            --muted: #657085;
            --line: #dce3ee;
            --primary: #0f766e;
            --primary-dark: #115e59;
            --warn: #b45309;
            --danger: #b91c1c;
            --ok: #15803d;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.45;
        }

        a {
            color: inherit;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 12px clamp(16px, 4vw, 32px);
            background: #102337;
            color: #fff;
            box-shadow: 0 10px 30px rgb(15 23 42 / 18%);
        }

        .brand {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .brand strong {
            font-size: 18px;
        }

        .brand span {
            color: #cbd5e1;
            font-size: 13px;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .network {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 36px;
            padding: 0 12px;
            border: 1px solid rgb(255 255 255 / 22%);
            border-radius: 6px;
            color: #e2e8f0;
            font-size: 13px;
        }

        .network-dot {
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: var(--ok);
        }

        .network.offline .network-dot {
            background: var(--danger);
        }

        .shell {
            width: min(1180px, 100%);
            margin: 0 auto;
            padding: 24px clamp(14px, 4vw, 32px) 48px;
        }

        .page-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 20px;
        }

        h1,
        h2,
        h3 {
            margin: 0;
            line-height: 1.15;
        }

        h1 {
            font-size: clamp(24px, 4vw, 36px);
        }

        h2 {
            font-size: 22px;
        }

        h3 {
            font-size: 18px;
        }

        .muted {
            color: var(--muted);
        }

        .stack {
            display: grid;
            gap: 14px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 14px;
        }

        .card,
        .question {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 12px 26px rgb(15 23 42 / 6%);
        }

        .card {
            display: grid;
            gap: 12px;
            padding: 16px;
            text-decoration: none;
        }

        .question {
            padding: 16px;
        }

        .module {
            display: grid;
            gap: 12px;
            padding-top: 16px;
            border-top: 1px solid var(--line);
        }

        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            background: #eef2f7;
            color: #334155;
            font-size: 13px;
            white-space: nowrap;
        }

        .pill.warn {
            background: #fef3c7;
            color: var(--warn);
        }

        .pill.ok {
            background: #dcfce7;
            color: var(--ok);
        }

        .progress {
            overflow: hidden;
            width: 100%;
            height: 10px;
            border-radius: 999px;
            background: #e5e7eb;
        }

        .progress span {
            display: block;
            height: 100%;
            width: var(--progress);
            border-radius: inherit;
            background: var(--primary);
        }

        .button,
        button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 14px;
            border: 0;
            border-radius: 6px;
            background: var(--primary);
            color: #fff;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .button.secondary,
        .top-actions button {
            border: 1px solid rgb(255 255 255 / 22%);
            background: transparent;
        }

        button:hover,
        .button:hover {
            background: var(--primary-dark);
        }

        button:disabled,
        .button.disabled {
            cursor: not-allowed;
            opacity: 0.55;
        }

        label {
            display: grid;
            gap: 6px;
            font-weight: 700;
        }

        input,
        select,
        textarea {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #fff;
            color: var(--text);
            font: inherit;
        }

        input,
        select {
            min-height: 42px;
            padding: 0 10px;
        }

        textarea {
            min-height: 94px;
            padding: 10px;
            resize: vertical;
        }

        .inline-check {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
        }

        .inline-check input {
            width: 18px;
            height: 18px;
        }

        .attachments {
            display: grid;
            gap: 10px;
        }

        .attachment-row {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 10px;
            align-items: center;
            padding: 10px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #f8fafc;
        }

        .attachment-row form {
            display: contents;
        }

        .notice {
            padding: 12px 14px;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            background: #f0fdf4;
            color: #166534;
        }

        .error {
            padding: 12px 14px;
            border: 1px solid #fecaca;
            border-radius: 8px;
            background: #fef2f2;
            color: #991b1b;
        }

        .login-wrap {
            display: grid;
            min-height: 100vh;
            place-items: center;
            padding: 24px;
        }

        .login-card {
            width: min(420px, 100%);
            padding: 24px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 22px 60px rgb(15 23 42 / 14%);
        }

        @media (max-width: 640px) {
            .topbar,
            .page-head {
                align-items: stretch;
                flex-direction: column;
            }

            .top-actions {
                justify-content: flex-start;
            }

            .shell {
                padding-top: 18px;
            }

            .attachment-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    @isset($authLayout)
        {{ $slot }}
    @else
        <header class="topbar">
            <a class="brand" href="{{ auth()->user()?->hasRole(\App\Enums\UserRole::Sales) ? route('sales.qualifications.index') : route('auditor.index') }}" style="text-decoration: none;">
                <strong>Audytor IT</strong>
                <span>Global IT</span>
            </a>
            <div class="top-actions">
                @auth
                    @php
                        $unreadNotifications = \App\Models\AuditNotification::where('user_id', auth()->id())
                            ->whereNull('read_at')
                            ->count();
                    @endphp
                    <a class="button secondary" href="{{ route('notifications.index') }}">
                        Powiadomienia{{ $unreadNotifications > 0 ? " ({$unreadNotifications})" : '' }}
                    </a>
                    @if (auth()->user()->active && auth()->user()->role->canAccessAdminPanel())
                        <a class="button" href="{{ url('/admin') }}">Panel admina</a>
                    @endif
                    @if (auth()->user()->active && auth()->user()->canViewAllAudits())
                        <a class="button secondary" href="{{ route('dashboard.index') }}">Dashboard</a>
                    @endif
                    @if (auth()->user()->active && auth()->user()->canViewAllAudits())
                        <a class="button secondary" href="{{ route('reports.exports.index') }}">Eksporty</a>
                    @endif
                    @if (auth()->user()->active && auth()->user()->hasAnyRole(\App\Enums\UserRole::SuperAdmin, \App\Enums\UserRole::GlobalAdmin, \App\Enums\UserRole::TechnicalLead, \App\Enums\UserRole::Sales))
                        <a class="button secondary" href="{{ route('sales.qualifications.index') }}">Kwalifikacje Sales</a>
                        <a class="button secondary" href="{{ route('sales.quotations.index') }}">Wyceny</a>
                        <a class="button secondary" href="{{ route('follow-ups.index') }}">Follow-up</a>
                    @endif
                    @if (auth()->user()->active && auth()->user()->hasAnyRole(\App\Enums\UserRole::SuperAdmin, \App\Enums\UserRole::GlobalAdmin, \App\Enums\UserRole::TechnicalLead, \App\Enums\UserRole::Auditor, \App\Enums\UserRole::Sales))
                        <a class="button secondary" href="{{ route('delivery.audit-orders.index') }}">Zlecenia</a>
                    @endif
                    @if (auth()->user()->active && auth()->user()->hasAnyRole(\App\Enums\UserRole::SuperAdmin, \App\Enums\UserRole::GlobalAdmin, \App\Enums\UserRole::TechnicalLead, \App\Enums\UserRole::Auditor))
                        <a class="button secondary" href="{{ route('engineer.audits.index') }}">Audyty 2.0</a>
                    @endif
                    @if (auth()->user()->active && auth()->user()->hasAnyRole(\App\Enums\UserRole::SuperAdmin, \App\Enums\UserRole::GlobalAdmin, \App\Enums\UserRole::TechnicalLead))
                        <a class="button secondary" href="{{ route('technical-review.audits.index') }}">Weryfikacja 2.0</a>
                    @endif
                @endauth
                <div id="network" class="network">
                    <span class="network-dot"></span>
                    <span id="network-label">Online</span>
                </div>
                <form method="post" action="{{ route('auditor.logout') }}">
                    @csrf
                    <button type="submit">Wyloguj</button>
                </form>
            </div>
        </header>
        <main class="shell">
            {{ $slot }}
        </main>
        <script>
            const network = document.getElementById('network');
            const label = document.getElementById('network-label');
            const updateNetwork = () => {
                const online = navigator.onLine;
                network.classList.toggle('offline', !online);
                label.textContent = online ? 'Online' : 'Offline';
            };
            window.addEventListener('online', updateNetwork);
            window.addEventListener('offline', updateNetwork);
            updateNetwork();
        </script>
    @endisset
</body>
</html>
