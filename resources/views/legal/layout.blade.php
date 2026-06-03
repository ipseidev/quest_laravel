<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <title>@yield('title') · Quest</title>
    <style>
        :root {
            --bg: #fbfaf7;
            --surface: #ffffff;
            --text: #1c1b19;
            --text-secondary: #5c594f;
            --text-tertiary: #908c80;
            --border: #e7e3da;
            --accent: #3a6b52;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #131210;
                --surface: #1c1b18;
                --text: #f2efe8;
                --text-secondary: #b9b4a7;
                --text-tertiary: #807c72;
                --border: #2c2a25;
                --accent: #87b89c;
            }
        }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 17px;
            line-height: 1.65;
            -webkit-font-smoothing: antialiased;
        }
        main {
            max-width: 44rem;
            margin: 0 auto;
            padding: 2.5rem 1.5rem 4rem;
        }
        header {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: space-between;
            gap: 1rem;
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border);
        }
        .brand {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text);
            text-decoration: none;
        }
        nav { display: flex; gap: 1rem; align-items: baseline; font-size: 0.9rem; }
        nav a { color: var(--text-secondary); text-decoration: none; }
        nav a:hover { color: var(--accent); }
        nav .lang { color: var(--text-tertiary); }
        nav .lang a { color: var(--text-tertiary); }
        h1 { font-size: 2rem; line-height: 1.2; letter-spacing: -0.02em; margin: 0 0 0.25rem; }
        h2 { font-size: 1.25rem; margin: 2.25rem 0 0.5rem; letter-spacing: -0.01em; }
        h3 { font-size: 1.05rem; margin: 1.5rem 0 0.4rem; }
        p, li { color: var(--text); }
        a { color: var(--accent); }
        ul { padding-left: 1.25rem; }
        li { margin: 0.3rem 0; }
        .updated { color: var(--text-tertiary); font-size: 0.9rem; margin: 0 0 2rem; }
        .notice {
            background: color-mix(in srgb, var(--accent) 12%, transparent);
            border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent);
            border-radius: 12px;
            padding: 0.85rem 1.1rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }
        footer {
            margin-top: 3rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
            color: var(--text-tertiary);
            font-size: 0.85rem;
        }
        footer a { color: var(--text-secondary); }
    </style>
</head>
<body>
    <main>
        <header>
            <a class="brand" href="{{ url('/') }}">Quest</a>
            <nav>
                <a href="{{ route('legal.privacy', ['lang' => $lang]) }}">{{ $lang === 'fr' ? 'Confidentialité' : 'Privacy' }}</a>
                <a href="{{ route('legal.terms', ['lang' => $lang]) }}">{{ $lang === 'fr' ? 'Conditions' : 'Terms' }}</a>
                <a href="{{ route('legal.support', ['lang' => $lang]) }}">{{ $lang === 'fr' ? 'Aide' : 'Support' }}</a>
                <span class="lang">
                    <a href="?lang=en">EN</a> · <a href="?lang=fr">FR</a>
                </span>
            </nav>
        </header>

        @yield('content')

        <footer>
            <p>
                {{ $lang === 'fr'
                    ? 'Quest — un journal où ta vie devient une histoire. Contact : '
                    : 'Quest — a journal where your life becomes a story. Contact: ' }}
                <a href="mailto:contact@affiniteam.io">contact@affiniteam.io</a>
            </p>
        </footer>
    </main>
</body>
</html>
