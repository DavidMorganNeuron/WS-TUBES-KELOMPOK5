<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Something went wrong') · Sumatera Heritage &amp; Trails</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <script>
        (() => {
            const root = document.documentElement;
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

            try {
                const preference = localStorage.getItem('wayfinder.theme');
                const isDark = preference === 'dark' || (preference !== 'light' && systemPrefersDark);

                root.classList.toggle('dark', isDark);
                root.style.colorScheme = isDark ? 'dark' : 'light';
            } catch (error) {
                root.classList.toggle('dark', systemPrefersDark);
                root.style.colorScheme = systemPrefersDark ? 'dark' : 'light';
                console.warn('WayFinder: saved theme preference could not be restored.', error);
            }
        })();
    </script>
    <style>
        :root {
            color-scheme: light;
            --brand-dark: #0b2921;
            --brand-primary: #0f5132;
            --brand-light: #f4f9f4;
            --brand-accent: #b74a1c;
            --brand-text: #1a1a1a;
            --brand-muted: #6b7280;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--brand-light);
            color: var(--brand-text);
            font-family: "Plus Jakarta Sans", "Segoe UI", system-ui, -apple-system, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .site-header {
            position: sticky;
            top: 0;
            z-index: 20;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
        }

        .site-header__inner {
            width: min(1120px, 100% - 2rem);
            margin: 0 auto;
            min-height: 5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--brand-primary);
            font-family: Georgia, "Times New Roman", serif;
            font-size: 1.125rem;
            font-weight: 700;
        }

        .brand svg {
            width: 2rem;
            height: 2rem;
            flex-shrink: 0;
        }

        .site-nav {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #4b5563;
        }

        .site-nav a:hover {
            color: var(--brand-primary);
        }

        .site-nav .nav-cta {
            border-radius: 9999px;
            background: var(--brand-accent);
            color: #ffffff;
            padding: 0.65rem 1rem;
        }

        main {
            flex: 1;
            width: min(760px, 100% - 2rem);
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4rem 0;
        }

        .error-card {
            width: 100%;
            border: 1px solid #e5e7eb;
            border-radius: 1.5rem;
            background: #ffffff;
            padding: clamp(1.75rem, 5vw, 3rem);
            text-align: center;
            box-shadow: 0 14px 40px rgba(11, 41, 33, 0.08);
        }

        .error-code {
            display: inline-block;
            margin-bottom: 1rem;
            border-radius: 9999px;
            background: var(--brand-light);
            color: var(--brand-primary);
            padding: 0.4rem 0.9rem;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.12em;
        }

        h1 {
            margin: 0 0 0.75rem;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(2rem, 6vw, 3rem);
            line-height: 1.1;
        }

        p {
            margin: 0 auto 1.75rem;
            max-width: 34rem;
            color: var(--brand-muted);
            line-height: 1.7;
        }

        .error-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.75rem;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.8rem;
            padding: 0.8rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 700;
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        .button--primary {
            background: var(--brand-dark);
            color: #ffffff;
        }

        .button--primary:hover {
            background: #123c30;
        }

        .button--secondary {
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #374151;
        }

        .button--secondary:hover {
            background: #f9fafb;
        }

        .site-footer {
            border-top: 1px solid #e5e7eb;
            background: #ffffff;
            padding: 1.25rem 0;
        }

        .site-footer__inner {
            width: min(1120px, 100% - 2rem);
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 0.5rem;
            color: var(--brand-muted);
            font-size: 0.75rem;
        }

        .theme-toggle {
            width: 2.5rem;
            height: 2.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e5e7eb;
            border-radius: 9999px;
            background: #ffffff;
            color: #4b5563;
            cursor: pointer;
        }

        .theme-toggle svg {
            width: 1.25rem;
            height: 1.25rem;
        }

        .theme-toggle:hover {
            background: #f9fafb;
        }

        .theme-toggle [data-theme-icon="sun"],
        .theme-toggle[aria-pressed="false"] [data-theme-icon="sun"],
        .theme-toggle[aria-pressed="true"] [data-theme-icon="moon"] {
            display: none;
        }

        .theme-toggle[aria-pressed="true"] [data-theme-icon="sun"] {
            display: block;
        }

        html.dark {
            color-scheme: dark;
            --brand-primary: #a7d6bc;
            --brand-light: #111827;
            --brand-accent: #d97745;
            --brand-text: #e5e7eb;
            --brand-muted: #9ca3af;
        }

        html.dark body {
            background: #111827;
            color: #e5e7eb;
        }

        html.dark .site-header,
        html.dark .error-card,
        html.dark .site-footer,
        html.dark .button--secondary {
            background: #1f2937;
            border-color: #374151;
        }

        html.dark .site-nav {
            color: #cbd5e1;
        }

        html.dark .button--secondary {
            color: #e5e7eb;
        }

        html.dark .button--secondary:hover {
            background: #273449;
        }

        html.dark .theme-toggle {
            border-color: #fbbf24;
            background: #fbbf24;
            color: #111827;
        }

        html.dark .theme-toggle:hover {
            border-color: #fcd34d;
            background: #fcd34d;
            color: #111827;
        }

        html.dark .site-footer {
            border-color: #374151;
        }

        @media (max-width: 640px) {
            .site-nav a:not(.nav-cta) {
                display: none;
            }

            .brand span {
                max-width: 12rem;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="site-header__inner">
            <a href="{{ url('/') }}" class="brand" aria-label="Sumatera Heritage and Trails home">
                <svg fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2L2 22h20L12 2zm0 4.5l6.5 13.5h-13L12 6.5z"/></svg>
                <span>Sumatera Heritage &amp; Trails</span>
            </a>

            <nav class="site-nav" aria-label="Error page navigation">
                <a href="{{ url('/') }}">Dashboard</a>
                <a href="{{ url('/destinations') }}">Destinations</a>
                <a href="{{ url('/map') }}">Map View</a>
                <button id="btn-theme-toggle" class="theme-toggle" type="button" aria-label="Switch to dark mode" aria-pressed="false" title="Switch to dark mode">
                    <svg data-theme-icon="moon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    <svg data-theme-icon="sun" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4" stroke-width="2"/><path stroke-linecap="round" stroke-width="2" d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>
                </button>
                <a href="{{ url('/destinations') }}" class="nav-cta">Explore</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="error-card" aria-labelledby="error-title">
            <span class="error-code">@yield('code', 'Error')</span>
            <h1 id="error-title">@yield('title', 'Something went wrong')</h1>
            <p>@yield('message', 'Please try again. If the problem continues, return to the dashboard and explore another page.')</p>
            <div class="error-actions">
                @yield('actions')
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="site-footer__inner">
            <span>&copy; {{ date('Y') }} Sumatera Heritage &amp; Trails</span>
            <span>Tourism ontology project · North Sumatra</span>
        </div>
    </footer>
    <script>
        (() => {
            const root = document.documentElement;
            const button = document.getElementById('btn-theme-toggle');

            if (!button) {
                return;
            }

            const syncThemeControl = () => {
                const isDark = root.classList.contains('dark');
                const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';

                button.setAttribute('aria-label', label);
                button.setAttribute('title', label);
                button.setAttribute('aria-pressed', String(isDark));
            };

            button.addEventListener('click', () => {
                const isDark = !root.classList.contains('dark');

                root.classList.toggle('dark', isDark);
                root.style.colorScheme = isDark ? 'dark' : 'light';
                syncThemeControl();

                try {
                    localStorage.setItem('wayfinder.theme', isDark ? 'dark' : 'light');
                } catch (error) {
                    console.warn('WayFinder: theme preference could not be saved.', error);
                }
            });

            syncThemeControl();
        })();
    </script>
</body>
</html>
