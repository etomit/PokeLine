<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = ['fr', 'en'];
        $locale = $request->user()?->locale
            ?? $request->cookie('pokeline_locale')
            ?? $request->getPreferredLanguage($supported)
            ?? config('app.fallback_locale', 'fr');

        App::setLocale(in_array($locale, $supported, true) ? $locale : 'fr');

        return $next($request);
    }
}
