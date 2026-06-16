<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') — Отклик</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <meta name="theme-color" content="#1F4E79">
    <style>
        :root {
            --ink: #1F4E79;
            --ink2: #2E74B5;
            --text: #334155;
            --muted: #64748b;
            --card: rgba(255, 255, 255, 0.6);
            --card-border: rgba(255, 255, 255, 0.6);
            --card-shadow: 0 24px 60px rgba(31, 78, 121, 0.18);
            --bg1: #eaf1fe; --bg2: #f6faff; --bg3: #e7f6ff;
            --orb1: #7cc0ff; --orb2: #b9a8ff; --orb3: #7df3e1;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --ink: #9ec5ec; --ink2: #7db4e6;
                --text: #cbd5e1; --muted: #93a3b8;
                --card: rgba(20, 30, 48, 0.6);
                --card-border: rgba(255, 255, 255, 0.1);
                --card-shadow: 0 24px 60px rgba(0, 0, 0, 0.5);
                --bg1: #0b1220; --bg2: #0e1828; --bg3: #0a1a26;
                --orb1: #2a4a73; --orb2: #3a3460; --orb3: #1f5a52;
            }
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body {
            font-family: -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
            color: var(--text);
            display: flex; align-items: center; justify-content: center;
            padding: 24px; overflow: hidden; position: relative;
        }
        .bg {
            position: fixed; inset: 0; z-index: -2;
            background: linear-gradient(125deg, var(--bg1) 0%, var(--bg2) 45%, var(--bg3) 100%);
            background-size: 200% 200%; animation: pan 22s ease infinite;
        }
        .orbs { position: fixed; inset: 0; z-index: -1; overflow: hidden; pointer-events: none; }
        .orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.5; }
        .orb1 { width: 440px; height: 440px; background: var(--orb1); top: -120px; left: -90px; animation: float 20s ease-in-out infinite; }
        .orb2 { width: 360px; height: 360px; background: var(--orb2); top: 25%; right: -100px; animation: float 26s ease-in-out infinite reverse; }
        .orb3 { width: 320px; height: 320px; background: var(--orb3); bottom: -100px; left: 15%; animation: float 24s ease-in-out infinite; }
        @media (prefers-color-scheme: dark) { .orb { opacity: 0.4; } }

        .card {
            position: relative; width: 100%; max-width: 480px; text-align: center;
            background: var(--card); border: 1px solid var(--card-border);
            border-radius: 28px; padding: 44px 36px; box-shadow: var(--card-shadow);
            backdrop-filter: blur(18px) saturate(170%); -webkit-backdrop-filter: blur(18px) saturate(170%);
            animation: rise 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) both;
        }
        .logo { display: inline-flex; align-items: center; gap: 9px; font-weight: 700; color: var(--ink); font-size: 18px; }
        .logo svg { width: 26px; height: 26px; }
        .code {
            margin: 18px 0 4px; font-size: 96px; line-height: 1; font-weight: 800; letter-spacing: -2px;
            background: linear-gradient(135deg, var(--ink2), var(--ink)); -webkit-background-clip: text;
            background-clip: text; color: transparent; animation: pop 0.6s cubic-bezier(0.2, 0.9, 0.2, 1) 0.1s both;
        }
        h1 { margin: 6px 0 8px; font-size: 24px; color: var(--ink); }
        p { margin: 0 auto 26px; font-size: 15px; line-height: 1.55; color: var(--muted); max-width: 360px; }
        .btn {
            display: inline-block; text-decoration: none; font-weight: 600; font-size: 15px;
            background: linear-gradient(135deg, var(--ink2), var(--ink)); color: #fff;
            padding: 13px 26px; border-radius: 14px; box-shadow: 0 12px 28px rgba(46, 116, 181, 0.35);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 16px 34px rgba(46, 116, 181, 0.45); }

        @keyframes pan { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
        @keyframes float { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(26px, -34px) scale(1.07); } }
        @keyframes rise { from { opacity: 0; transform: translateY(22px); } to { opacity: 1; transform: none; } }
        @keyframes pop { from { opacity: 0; transform: scale(0.8); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { .bg, .orb, .card, .code { animation: none; } }
    </style>
</head>
<body>
    <div class="bg"></div>
    <div class="orbs"><span class="orb orb1"></span><span class="orb orb2"></span><span class="orb orb3"></span></div>

    <div class="card">
        <div class="logo">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <defs><linearGradient id="lg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#5aa0e0"/><stop offset="1" stop-color="#2E74B5"/></linearGradient></defs>
                <path fill="url(#lg)" d="M4.8 21.64A6.7 6.7 0 0 0 6 21.75a6.72 6.72 0 0 0 3.58-1.03c.77.18 1.58.28 2.42.28 5.32 0 9.75-3.97 9.75-9s-4.43-9-9.75-9-9.75 3.97-9.75 9c0 2.41 1.03 4.59 2.67 6.19.23.23.28.43.25.54a3.73 3.73 0 0 1-.81 1.69.75.75 0 0 0 .44 1.22Z"/>
                <path fill="#fff" d="M12 7l1.2 2.8L16 11l-2.8 1.2L12 15l-1.2-2.8L8 11l2.8-1.2z"/>
            </svg>
            Отклик
        </div>
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <a class="btn" href="{{ url('/') }}">@yield('action', 'На главную')</a>
    </div>
</body>
</html>
