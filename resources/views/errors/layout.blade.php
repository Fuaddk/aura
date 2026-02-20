<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — Aura</title>
    <link rel="icon" type="image/png" href="{{ asset('cirkel.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Figtree', sans-serif;
            background: #f9f8ff;
            color: #1e1b4b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .error-card {
            background: #fff;
            border: 1px solid #ede9fe;
            border-radius: 1.25rem;
            padding: 3rem 2.5rem;
            max-width: 460px;
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 32px rgba(126,117,206,0.08);
        }

        .error-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            text-decoration: none;
        }

        .error-logo img {
            width: 32px;
            height: 32px;
        }

        .error-logo span {
            font-size: 1.25rem;
            font-weight: 600;
            color: #7E75CE;
            letter-spacing: -0.01em;
        }

        .error-code {
            font-size: 5rem;
            font-weight: 600;
            line-height: 1;
            color: #7E75CE;
            opacity: 0.18;
            margin-bottom: 1rem;
            letter-spacing: -0.04em;
        }

        .error-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .error-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e1b4b;
            margin-bottom: 0.5rem;
        }

        .error-desc {
            font-size: 0.9375rem;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .error-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-primary {
            display: inline-block;
            background: #7E75CE;
            color: #fff;
            font-size: 0.9375rem;
            font-weight: 500;
            padding: 0.625rem 1.5rem;
            border-radius: 0.625rem;
            text-decoration: none;
            transition: background 0.15s;
        }

        .btn-primary:hover { background: #6d64be; }

        .btn-ghost {
            display: inline-block;
            background: transparent;
            color: #7E75CE;
            font-size: 0.9375rem;
            font-weight: 500;
            padding: 0.625rem 1.5rem;
            border-radius: 0.625rem;
            border: 1px solid #ede9fe;
            text-decoration: none;
            transition: background 0.15s;
        }

        .btn-ghost:hover { background: #f5f3ff; }
    </style>
</head>
<body>
    <div class="error-card">
        <a href="{{ url('/') }}" class="error-logo">
            <img src="{{ asset('cirkel.png') }}" alt="Aura">
            <span>Aura</span>
        </a>

        <div class="error-code">@yield('code')</div>
        <div class="error-icon">@yield('icon')</div>
        <div class="error-title">@yield('title')</div>
        <div class="error-desc">@yield('description')</div>

        <div class="error-actions">
            @yield('actions')
        </div>
    </div>
</body>
</html>
