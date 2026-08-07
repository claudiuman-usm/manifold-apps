<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="{{ session('theme', 'light') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', __('hub.app_name')) · {{ __('hub.app_name') }}</title>
    @include('partials.favicon', ['ctx' => trim($__env->yieldContent('bodyClass', 'ctx-hub'))])
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @stack('head')
</head>
<body class="@yield('bodyClass', 'ctx-hub')">
    <div class="bg-decor" aria-hidden="true">
        <span class="orb orb-1"></span>
        <span class="orb orb-2"></span>
        <span class="orb orb-3"></span>
    </div>

    <header class="topbar">
        <div class="topbar-inner">
            <a href="{{ route('dashboard') }}" class="brand">
                {{ __('hub.app_name') }} <small>{{ __('hub.tagline') }}</small>
            </a>

            <nav class="topbar-nav">
                <a href="{{ route('dashboard') }}"
                   class="navlink {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    {{ __('hub.nav.dashboard') }}
                </a>

                @include('partials.locale-switch')
                @include('partials.theme-toggle')

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="navlink" style="border:0;background:none;cursor:pointer;font:inherit;">
                        {{ __('hub.nav.sign_out') }}
                    </button>
                </form>
            </nav>
        </div>
    </header>

    <main class="content">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @yield('content')
    </main>

    <footer style="position:relative;z-index:1;max-width:var(--maxw);margin:0 auto;padding:8px 24px 32px;text-align:center;color:var(--text-3);font-size:.78rem;">
        {{ __('hub.app_name') }} · v{{ config('app.version', '1.0') }}
    </footer>

    @stack('scripts')
</body>
</html>
