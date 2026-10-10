@php($themeCssVersion = @filemtime(public_path('css/jftech-theme.css')) ?: 1)
@php($loginCssVersion = @filemtime(public_path('css/login.css')) ?: 1)
@php($mainJsVersion = @filemtime(public_path('js/jftech-main.js')) ?: 1)
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#06142b">
    <title>JFTECH Management System</title>
    <script>
        try {
            var savedTheme = localStorage.getItem('jftech-theme');
            document.documentElement.setAttribute('data-theme', savedTheme === 'dark' ? 'dark' : 'light');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?{{ $loginCssVersion }}">
</head>
<body class="login-page corporate-login-page">
    <div class="login-background" aria-hidden="true"></div>

    <button class="theme-toggle" type="button" data-theme-toggle aria-pressed="false" title="Toggle theme">
        <span class="theme-toggle__icon" data-theme-icon></span>
        <span class="theme-toggle__label" data-theme-label>Dark Mode</span>
    </button>

    <main class="corporate-login-card">
        <section class="login-section" aria-label="Login form">
            <div class="login-welcome">
                <span class="login-welcome__eyebrow">Secure workspace</span>
                <h2>Welcome back</h2>
                <p>Sign in to manage your daily business operations.</p>
            </div>
            @if ($errors->any())
                <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="corporate-login-form">
                @csrf
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="field-control">
                        <svg class="field-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.25"/><path d="M5.5 19.5c.8-3.1 3.1-4.7 6.5-4.7s5.7 1.6 6.5 4.7"/></svg>
                        <input id="username" name="username" type="text" value="{{ old('username') }}" required autofocus autocomplete="username">
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="field-control">
                        <svg class="field-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10"/></svg>
                        <input id="password" name="password" type="password" required autocomplete="current-password">
                        <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="login-button">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10"/></svg>
                    Login
                </button>
            </form>
        </section>

        <section class="brand-section" aria-label="JFTECH Management System">
                <div class="brand-logo-wrap">
                    <img src="{{ url('/assets/jftech-logo.png') }}" alt="JFTECH logo" width="135" height="135">
                </div>
            <h1>JFTECH</h1>
            <p>Business Management System</p>
            <span class="brand-section__note">Connected teams. Clear operations.</span>
        </section>
    </main>

    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
    <script>
        document.querySelector('.password-toggle')?.addEventListener('click', function () {
            const password = document.getElementById('password');
            const isHidden = password.type === 'password';
            password.type = isHidden ? 'text' : 'password';
            this.setAttribute('aria-pressed', String(isHidden));
            this.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    </script>
</body>
</html>
