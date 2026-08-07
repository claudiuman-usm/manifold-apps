<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (in_array($locale, config('app.available_locales', ['ro', 'en']), true)) {
            $request->session()->put('locale', $locale);
        }

        return back();
    }
}
