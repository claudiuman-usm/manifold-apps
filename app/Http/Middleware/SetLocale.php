<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the locale stored in the session (set by the locale switcher), always
 * resolving to one of the currently available locales. RO is parked, so this
 * keeps the whole platform in EN regardless of a stale session value or an
 * APP_LOCALE=ro left in the server .env.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = config('app.available_locales', ['en']);
        $locale = $request->session()->get('locale');

        if (! in_array($locale, $available, true)) {
            $locale = in_array(config('app.locale'), $available, true)
                ? config('app.locale')
                : ($available[0] ?? 'en');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
