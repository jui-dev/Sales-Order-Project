{{--
    Standalone page - deliberately does NOT extend layouts.app, which would wrap
    the sign-in form in the full application sidebar and navigation.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In &middot; Sales Order System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            padding: 1.5rem;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            border: none;
            border-radius: .75rem;
            box-shadow: 0 1.5rem 3rem rgba(0, 0, 0, .35);
        }

        /* Icon and wordmark sit on one line, centred as a unit */
        .login-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .625rem;
            color: #fff;
            margin-bottom: 1.75rem;
        }

        .login-brand i {
            font-size: 1.75rem;
            line-height: 1;
        }
    </style>
</head>
<body>
    <div>
        <div class="login-brand">
            <i class="bi bi-graph-up"></i>
            <h1 class="h4 fw-semibold mb-0">Sales Order System</h1>
        </div>

        <div class="card login-card">
            <div class="card-body p-4 p-sm-5">
                <h2 class="h5 fw-semibold mb-1">Sign in</h2>
                {{-- The seeded admin credentials are shown as placeholders while the
                     project is in development. Remove them before this is deployed
                     anywhere real. --}}
                <p class="text-muted small mb-4">
                    Enter your credentials to continue. The seeded admin login is shown
                    in the fields below.
                </p>

                @if (session('status'))
                    <div class="alert alert-info py-2 small">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input id="email"
                                   type="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   name="email"
                                   value="{{ old('email') }}"
                                   placeholder="admin@example.com"
                                   required
                                   autocomplete="email"
                                   autofocus>
                            @error('email')
                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input id="password"
                                   type="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   name="password"
                                   placeholder="password"
                                   required
                                   autocomplete="current-password">
                            @error('password')
                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember"
                               {{ old('remember') ? 'checked' : '' }}>
                        <label class="form-check-label small" for="remember">Remember me</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Sign in
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
