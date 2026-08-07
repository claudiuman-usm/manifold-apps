<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the locale stored in the session (set by the locale switcher),
 * falling back to the configured app locale (RO).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if (in_array($locale, config('app.available_locales', ['ro', 'en']), true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
