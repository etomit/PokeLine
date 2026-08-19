<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#232038">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key') }}">
    <meta name="reverb-host" content="{{ config('broadcasting.connections.reverb.client.host') }}">
    <meta name="reverb-port" content="{{ config('broadcasting.connections.reverb.client.port') }}">
    <meta name="reverb-scheme" content="{{ config('broadcasting.connections.reverb.client.scheme') }}">
    <title>@yield('title', __('ui.title'))</title>
    <script>if (window.self !== window.top) document.documentElement.classList.add('game-embedded');</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="gameboy-shell">
    <header class="topbar">
        <a class="brand" href="{{ route('home') }}" aria-label="{{ __('ui.home') }}">
            <span class="brand-dot"></span>{{ __('ui.title') }}
        </a>
        <nav>
            <a href="{{ route('pokedex') }}">{{ __('ui.pokedex') }}</a>
        </nav>
        <div class="presence-counters" data-presence-counters data-presence-url="{{ route('presence') }}" aria-live="polite">
            <span class="presence-stat active-players" title="{{ __('ui.active_players') }}">
                <i aria-hidden="true"></i><b data-active-players>—</b><small>{{ __('ui.active_players') }}</small>
            </span>
            <span class="presence-stat connected-accounts" title="{{ __('ui.connected_accounts') }}">
                <i aria-hidden="true"></i><b data-connected-accounts>—</b><small>{{ __('ui.connected_accounts') }}</small>
            </span>
        </div>
        <div class="account-actions">
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
            <div class="audio-setting-row">
                <label class="sound-setting"><input type="checkbox" id="global-music" checked> {{ __('ui.music') }}</label>
                <label class="volume-setting"><span>{{ __('ui.music_volume') }}</span><input type="range" id="global-music-volume" min="0" max="100" step="5" value="65"><output for="global-music-volume">65%</output></label>
            </div>
            <div class="audio-setting-row">
                <label class="sound-setting"><input type="checkbox" id="global-sound" checked> {{ __('ui.sound') }}</label>
                <label class="volume-setting"><span>{{ __('ui.effects_volume') }}</span><input type="range" id="global-sound-volume" min="0" max="100" step="5" value="75"><output for="global-sound-volume">75%</output></label>
            </div>
            <button class="pixel-button" type="submit">{{ __('ui.save') }}</button>
        </form>
        <small>{{ __('ui.settings_hint') }}</small>
    </dialog>
    <dialog id="pokedex-dialog" class="pokedex-dialog">
        <form method="dialog" class="dialog-close"><button aria-label="{{ __('ui.close') }}">×</button></form>
        <h2>{{ __('ui.choose_from_pokedex') }}</h2>
        <div class="pokedex-device compact" data-pokedex-browser data-catalog-url="{{ route('pokedex.catalog') }}">
            <div class="pokedex-toolbar"><input type="search" data-pokedex-search placeholder="{{ __('ui.search_pokemon') }}"><button type="button" class="pixel-button" data-pokedex-submit>{{ __('ui.search') }}</button></div>
            <div class="pokedex-grid" data-pokedex-grid><div class="pokedex-loading">{{ __('ui.loading_pokedex') }}</div></div>
            <div class="pokedex-pagination"><button type="button" data-pokedex-prev>◀</button><span data-pokedex-page>1 / 1</span><button type="button" data-pokedex-next>▶</button></div>
        </div>
    </dialog>
    <main>
        @if(session('success')) <div class="flash success">{{ session('success') }}</div> @endif
        @if($errors->any())
            <div class="flash error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
