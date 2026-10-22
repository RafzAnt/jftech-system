@php
    $user = auth()->user();
    // An account that does not belong in the admin area at all (the employee app, or a deactivated one) can only log out.
    $outsider = $user && ! (in_array($user->role, ['super_admin', 'admin'], true) && $user->status === 'active');
@endphp
<!DOCTYPE html>
<html lang="en-PH" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>No access - JFTECH</title>
    <script>
        try {
            document.documentElement.setAttribute('data-theme', localStorage.getItem('jftech-theme') === 'light' ? 'light' : 'dark');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}">
    <style>
        body { min-height: 100vh; display: grid; place-items: center; margin: 0; padding: 1rem; background: var(--primary); color: var(--text-primary); }
        .no-access { width: 100%; max-width: 420px; padding: 2rem; text-align: center; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--radius-lg, 16px); }
        .no-access h1 { margin: 0 0 .5rem; font-size: 1.25rem; }
        .no-access p { margin: 0 0 1.5rem; color: var(--text-secondary); }
        .no-access form { margin: 0; }
    </style>
</head>
<body>
    <main class="no-access">
        @if ($outsider)
            <h1>This account can't use the admin system</h1>
            <p>Log in with an admin account to continue.</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-primary">Log out</button>
            </form>
        @else
            <h1>You don't have access to this page</h1>
            <p>Your account can't open or change this.</p>
            <a class="btn btn-primary" href="{{ url('/dashboard') }}">Back to dashboard</a>
        @endif
    </main>
</body>
</html>
