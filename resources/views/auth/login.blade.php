<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In | Gemilang License Server</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: #1a3a6b;
            --primary-mid: #29abe2;
            --accent: #0078d4;
        }

        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: stretch;
            background: #f0f2f5;
        }

        /* ── Left panel — Windows-style illustration ── */
        .login-left {
            flex: 1;
            background: linear-gradient(145deg, #0f2a52 0%, #1a3a6b 40%, #1e4a8a 70%, #29abe2 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem;
            position: relative;
            overflow: hidden;
        }

        /* Decorative circles — Windows 11 wallpaper feel */
        .login-left::before {
            content: '';
            position: absolute;
            width: 600px; height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(41,171,226,.18) 0%, transparent 70%);
            top: -150px; right: -150px;
            pointer-events: none;
        }

        .login-left::after {
            content: '';
            position: absolute;
            width: 400px; height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,.06) 0%, transparent 70%);
            bottom: -100px; left: -100px;
            pointer-events: none;
        }

        .left-content {
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 420px;
        }

        /* Shield icon */
        .shield-icon {
            width: 96px; height: 96px;
            background: rgba(255,255,255,.12);
            border: 2px solid rgba(255,255,255,.2);
            border-radius: 24px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 2rem;
            backdrop-filter: blur(8px);
        }

        .shield-icon svg { width: 48px; height: 48px; color: #fff; }

        .left-title {
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: -.02em;
            margin-bottom: .5rem;
        }

        .left-subtitle {
            font-size: .9rem;
            color: rgba(255,255,255,.65);
            line-height: 1.6;
            margin-bottom: 2.5rem;
        }

        /* Feature pills */
        .feature-pills {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            justify-content: center;
        }

        .feature-pill {
            display: flex;
            align-items: center;
            gap: .35rem;
            padding: .4rem .85rem;
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 20px;
            color: rgba(255,255,255,.85);
            font-size: .72rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
        }

        .feature-pill svg { width: 12px; height: 12px; flex-shrink: 0; }

        /* ── Right panel — Login form ── */
        .login-right {
            width: 420px;
            flex-shrink: 0;
            background: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 2rem;
            position: relative;
        }

        .login-form-wrap {
            width: 100%;
            max-width: 340px;
        }

        /* Logo area */
        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo-icon {
            width: 56px; height: 56px;
            background: var(--primary);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto .75rem;
            box-shadow: 0 4px 14px rgba(26,58,107,.3);
        }

        .login-logo-icon svg { width: 28px; height: 28px; color: #fff; }

        .login-logo h1 {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -.02em;
            margin-bottom: .2rem;
        }

        .login-logo p {
            font-size: .72rem;
            color: #94a3b8;
            font-weight: 500;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        /* Divider */
        .login-divider {
            height: 1px;
            background: #f1f5f9;
            margin: 0 0 1.5rem;
        }

        /* Alerts */
        .login-alert {
            display: flex;
            align-items: flex-start;
            gap: .5rem;
            padding: .65rem .85rem;
            border-radius: 10px;
            font-size: .78rem;
            margin-bottom: 1.25rem;
            line-height: 1.4;
        }

        .login-alert svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px; }
        .login-alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        /* Fields */
        .field-group { margin-bottom: 1rem; }

        .field-label {
            display: block;
            font-size: .7rem;
            font-weight: 700;
            color: #475569;
            margin-bottom: .35rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .field-wrap { position: relative; }

        .field-icon {
            position: absolute;
            left: .8rem; top: 50%;
            transform: translateY(-50%);
            color: #cbd5e1;
            pointer-events: none;
            display: flex;
        }

        .field-icon svg { width: 16px; height: 16px; }

        .field-input {
            width: 100%;
            padding: .65rem .8rem .65rem 2.4rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: .82rem;
            color: #1e293b;
            background: #f8fafc;
            outline: none;
            transition: all .2s;
        }

        .field-input::placeholder { color: #cbd5e1; }

        .field-input:focus {
            border-color: var(--accent);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(0,120,212,.1);
        }

        .field-input.is-invalid { border-color: #ef4444; }
        .field-error { font-size: .7rem; color: #ef4444; margin-top: .3rem; }

        /* Password toggle */
        .pw-toggle {
            position: absolute;
            right: .6rem; top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #94a3b8;
            padding: .25rem;
            border-radius: 5px;
            display: flex;
            transition: color .15s;
        }

        .pw-toggle:hover { color: var(--accent); }
        .pw-toggle svg { width: 16px; height: 16px; }

        /* Remember */
        .remember-row {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-bottom: 1.25rem;
        }

        .remember-row input[type=checkbox] {
            width: 15px; height: 15px;
            accent-color: var(--accent);
            cursor: pointer;
        }

        .remember-row label {
            font-size: .75rem;
            color: #64748b;
            cursor: pointer;
        }

        /* Submit */
        .btn-login {
            width: 100%;
            padding: .7rem;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: .84rem;
            font-weight: 700;
            cursor: pointer;
            transition: all .2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            letter-spacing: .02em;
        }

        .btn-login:hover { background: #006cbf; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(0,120,212,.35); }
        .btn-login:active { transform: translateY(0); }
        .btn-login:disabled { opacity: .65; cursor: not-allowed; transform: none; }

        .login-spinner {
            width: 14px; height: 14px;
            border: 2px solid rgba(255,255,255,.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* Footer */
        .login-footer {
            text-align: center;
            font-size: .65rem;
            color: #94a3b8;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #f1f5f9;
        }

        /* Windows-style bottom bar */
        .win-bar {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-mid) 50%, var(--accent) 100%);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .login-left { display: none; }
            .login-right { width: 100%; }
        }
    </style>
</head>
<body>
    <!-- Left panel -->
    <div class="login-left">
        <div class="left-content">
            <div class="shield-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
            </div>
            <h2 class="left-title">Gemilang</h2>
            <p class="left-subtitle">
                Central license server for the ERM platform.<br>
                Manage, issue, and monitor software licenses with PASETO v4 security.
            </p>
            <div class="feature-pills">
                <div class="feature-pill">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    Ed25519 Signing
                </div>
                <div class="feature-pill">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    PASETO v4 Tokens
                </div>
                <div class="feature-pill">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    Heartbeat Monitor
                </div>
                <div class="feature-pill">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    Fingerprint Lock
                </div>
            </div>
        </div>
    </div>

    <!-- Right panel -->
    <div class="login-right">
        <div class="login-form-wrap">
            <div class="login-logo">
                <div class="login-logo-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                </div>
                <h1>Gemilang</h1>
                <p>License Server — Admin Panel</p>
            </div>

            <div class="login-divider"></div>

            @if($errors->any())
            <div class="login-alert login-alert-danger">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            </div>
            @endif

            <form method="POST" action="{{ route('login.submit') }}" id="loginForm">
                @csrf

                <div class="field-group">
                    <label class="field-label" for="email">Email</label>
                    <div class="field-wrap">
                        <span class="field-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </span>
                        <input type="email" id="email" name="email"
                            class="field-input @error('email') is-invalid @enderror"
                            placeholder="admin@example.com"
                            value="{{ old('email') }}"
                            required autofocus autocomplete="email">
                    </div>
                    @error('email')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="field-group">
                    <label class="field-label" for="password">Password</label>
                    <div class="field-wrap">
                        <span class="field-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </span>
                        <input type="password" id="password" name="password"
                            class="field-input @error('password') is-invalid @enderror"
                            placeholder="Enter your password"
                            required autocomplete="current-password"
                            style="padding-right:2.5rem;">
                        <button type="button" class="pw-toggle" onclick="togglePw()" tabindex="-1">
                            <svg id="iconEye" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg id="iconEyeOff" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                    @error('password')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="remember-row">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Keep me signed in</label>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <span id="loginText">Sign In</span>
                    <span id="loginLoading" style="display:none;align-items:center;gap:.4rem;">
                        <span class="login-spinner"></span> Signing in...
                    </span>
                </button>
            </form>

            <p class="login-footer">
                &copy; {{ date('Y') }} Gemilang License Server. All rights reserved.
            </p>
        </div>

        <div class="win-bar"></div>
    </div>

    <script>
        function togglePw() {
            var inp = document.getElementById('password');
            var on  = document.getElementById('iconEye');
            var off = document.getElementById('iconEyeOff');
            if (inp.type === 'password') {
                inp.type = 'text';
                on.style.display = 'block';
                off.style.display = 'none';
            } else {
                inp.type = 'password';
                on.style.display = 'none';
                off.style.display = 'block';
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function() {
            var btn = document.getElementById('loginBtn');
            btn.disabled = true;
            document.getElementById('loginText').style.display = 'none';
            document.getElementById('loginLoading').style.display = 'flex';
        });
    </script>
</body>
</html>
