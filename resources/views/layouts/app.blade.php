<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('ui.title'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="gameboy-shell">
    <header class="topbar">
        <a class="brand" href="{{ route('home') }}" aria-label="{{ __('ui.home') }}">
            <span class="brand-dot"></span>{{ __('ui.title') }}
        </a>
        <nav>
            <a href="{{ route('battle.setup', 'solo') }}">{{ __('ui.solo') }}</a>
            <a href="{{ route('battle.setup', 'local') }}">{{ __('ui.local') }}</a>
            @auth <a href="{{ route('battle.lobby') }}">{{ __('ui.online') }}</a> @endauth
        </nav>
        <div class="account-actions">
            <button type="button" class="settings-button" id="settings-open">⚙ {{ __('ui.settings') }} <kbd>P</kbd></button>
            @guest
                <a href="{{ route('login') }}">{{ __('ui.login') }}</a>
                <a class="pixel-button small" href="{{ route('register') }}">{{ __('ui.register') }}</a>
            @else
                <span>{{ auth()->user()->name }}</span>
                <form action="{{ route('logout') }}" method="post">@csrf<button class="link-button">{{ __('ui.logout') }}</button></form>
            @endguest
        </div>
    </header>
    <dialog id="settings-dialog" class="settings-dialog">
        <form method="dialog" class="dialog-close"><button aria-label="{{ __('ui.close') }}">×</button></form>
        <p class="eyebrow">SYSTEM // OPTIONS</p><h2>{{ __('ui.settings') }}</h2>
        <form action="{{ route('locale') }}" method="post" class="settings-form">
            @csrf
            <label>{{ __('ui.language') }}<select name="locale"><option value="fr" @selected(app()->getLocale() === 'fr')>Français</option><option value="en" @selected(app()->getLocale() === 'en')>English</option></select></label>
            <label class="sound-setting"><input type="checkbox" id="global-sound" checked> {{ __('ui.sound') }}</label>
            <button class="pixel-button" type="submit">{{ __('ui.save') }}</button>
        </form>
        <small>{{ __('ui.settings_hint') }}</small>
    </dialog>
    <main>
        @if(session('success')) <div class="flash success">{{ session('success') }}</div> @endif
        @if($errors->any())
            <div class="flash error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
    </main>
    <footer>POKÉLINE // POKEAPI // MVC</footer>
</div>
@stack('scripts')
</body>
</html>
