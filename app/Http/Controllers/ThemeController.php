<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function switch(Request $request, string $theme): RedirectResponse
    {
        if (in_array($theme, ['dark', 'light'], true)) {
            $request->session()->put('theme', $theme);
        }

        return back();
    }
}
