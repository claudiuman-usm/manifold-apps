<?php

namespace App\Modules\MyMusic\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MyMusic\Support\YouTubeApiException;
use App\Modules\MyMusic\Support\YouTubeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OAuthController extends Controller
{
    public function redirect(Request $request, YouTubeClient $yt)
    {
        if (! $yt->isConfigured()) {
            return redirect()->route('music.index')
                ->with('status', __('music::messages.errors.not_configured'));
        }

        $state = Str::random(40);
        $request->session()->put('music_oauth_state', $state);

        return redirect()->away($yt->authUrl($state));
    }

    public function callback(Request $request, YouTubeClient $yt)
    {
        $expected = $request->session()->pull('music_oauth_state');

        if (! $expected || $request->query('state') !== $expected) {
            abort(403, 'OAuth state mismatch.');
        }

        if ($request->query('error') || ! $request->query('code')) {
            return redirect()->route('music.index')
                ->with('status', __('music::messages.errors.oauth_denied'));
        }

        try {
            $yt->handleCallback($request->query('code'));
        } catch (YouTubeApiException $e) {
            return redirect()->route('music.index')->with('status', $e->getMessage());
        }

        return redirect()->route('music.index')
            ->with('status', __('music::messages.flash.connected'));
    }

    public function disconnect(YouTubeClient $yt)
    {
        $yt->disconnect();

        return redirect()->route('music.index')
            ->with('status', __('music::messages.flash.disconnected'));
    }
}
