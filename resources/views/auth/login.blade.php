<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="{{ session('theme', 'light') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ __('hub.auth.sign_in') }} · {{ __('hub.app_name') }}</title>
    @include('partials.favicon', ['ctx' => 'ctx-flower'])
    <link rel="stylesheet" href="{{ route('assets.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="ctx-flower">
    <div class="bg-decor" aria-hidden="true">
        <span class="orb orb-1"></span>
        <span class="orb orb-2"></span>
        <span class="orb orb-3"></span>
    </div>
    <div class="auth-wrap">
        <div class="auth-card card">
            <div class="auth-brand">
                <span class="mark"></span>
                <h1 style="margin:0;">{{ __('hub.app_name') }}</h1>
            </div>
            <p class="sub">{{ __('hub.auth.sign_in_prompt') }}</p>

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="form-row">
                    <label for="email">{{ __('hub.auth.email') }}</label>
                    <input id="email" name="email" type="email" class="input"
                           value="{{ old('email') }}" required autofocus>
                    @error('email')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-row">
                    <label for="password">{{ __('hub.auth.password') }}</label>
                    <input id="password" name="password" type="password" class="input" required>
                    @error('password')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-row checkbox-row">
                    <input id="remember" name="remember" type="checkbox" value="1">
                    <label for="remember" style="margin:0;font-weight:500;">{{ __('hub.auth.remember') }}</label>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">{{ __('hub.auth.sign_in') }}</button>
            </form>

            <div class="auth-locale flex gap-sm" style="justify-content:center;">
                @include('partials.locale-switch')
                @include('partials.theme-toggle')
            </div>
        </div>
    </div>
</body>
</html>
