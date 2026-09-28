<?php

namespace App\Modules\MyMusic\Support;

use App\Modules\MyMusic\Models\GoogleToken;
use App\Modules\MyMusic\Models\QuotaEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin Google OAuth + YouTube Data API v3 client over Laravel's Http facade
 * (no SDK dependency — plays nice with the no-SSH cPanel deploy).
 *
 * Every API call logs its quota cost to music_quota_log so the UI can show
 * spend against the 10,000-unit daily budget (resets midnight Pacific).
 */
class YouTubeClient
{
    protected const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    protected const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    protected const API_BASE = 'https://www.googleapis.com/youtube/v3/';
    protected const SCOPES = 'https://www.googleapis.com/auth/youtube.readonly https://www.googleapis.com/auth/youtube';

    public function isConfigured(): bool
    {
        return (bool) (config('music.google.client_id') && config('music.google.client_secret'));
    }

    public function isConnected(): bool
    {
        return GoogleToken::current() !== null;
    }

    public function authUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('music.google.client_id'),
            'redirect_uri' => route('music.oauth.callback'),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'access_type' => 'offline',
            'prompt' => 'consent', // guarantees a refresh_token on every connect
            'state' => $state,
        ]);
    }

    /** Exchange the OAuth code, store tokens + the channel name. */
    public function handleCallback(string $code): void
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('music.google.client_id'),
            'client_secret' => config('music.google.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => route('music.oauth.callback'),
        ]);

        if ($response->failed()) {
            Log::warning('MyMusic OAuth exchange failed: '.$response->body());
            throw new YouTubeApiException(__('music::messages.errors.oauth_exchange'));
        }

        $data = $response->json();

        // Single connected account: replace any previous row.
        GoogleToken::query()->forceDelete();

        $token = GoogleToken::create([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? '',
            'expires_at' => Carbon::now()->addSeconds((int) ($data['expires_in'] ?? 3600) - 60),
            'scope' => $data['scope'] ?? self::SCOPES,
        ]);

        // Grab the channel title for the settings panel (1 quota unit).
        try {
            $channel = $this->get('channels', ['part' => 'snippet', 'mine' => 'true'], 1, 'channels.list');
            $token->update(['account_name' => $channel['items'][0]['snippet']['title'] ?? null]);
        } catch (\Throwable $e) {
            Log::warning('MyMusic channel lookup failed: '.$e->getMessage());
        }
    }

    public function disconnect(): void
    {
        GoogleToken::query()->forceDelete();
    }

    /** GET a YouTube API endpoint, logging its quota cost. */
    public function get(string $endpoint, array $query, int $units, string $action): array
    {
        return $this->call('get', $endpoint, ['query' => $query], $units, $action);
    }

    /** POST a YouTube API endpoint (JSON body), logging its quota cost. */
    public function post(string $endpoint, array $query, array $body, int $units, string $action): array
    {
        return $this->call('post', $endpoint, ['query' => $query, 'body' => $body], $units, $action);
    }

    protected function call(string $method, string $endpoint, array $payload, int $units, string $action, bool $retried = false): array
    {
        $request = Http::withToken($this->accessToken())->acceptJson();
        $url = self::API_BASE.$endpoint.'?'.http_build_query($payload['query'] ?? []);

        $response = $method === 'post'
            ? $request->post($url, $payload['body'] ?? [])
            : $request->get($url);

        // A failed call still consumed quota — log before inspecting.
        QuotaEntry::record($action, $units, ['status' => $response->status()]);

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $reason = $response->json('error.errors.0.reason') ?? '';

        if ($response->status() === 401 && ! $retried) {
            $this->refreshAccessToken();

            return $this->call($method, $endpoint, $payload, $units, $action, true);
        }

        if ($reason === 'quotaExceeded' || $reason === 'dailyLimitExceeded') {
            throw new YouTubeApiException(__('music::messages.errors.quota'), quotaExceeded: true);
        }

        Log::warning("MyMusic YouTube API {$action} failed ({$response->status()}): ".$response->body());
        throw new YouTubeApiException(__('music::messages.errors.api', ['reason' => $reason ?: $response->status()]));
    }

    protected function accessToken(): string
    {
        $token = GoogleToken::current();

        if (! $token) {
            throw new YouTubeApiException(__('music::messages.errors.not_connected'));
        }

        if ($token->expires_at === null || $token->expires_at->isPast()) {
            $this->refreshAccessToken();
            $token = GoogleToken::current();
        }

        return $token->access_token;
    }

    protected function refreshAccessToken(): void
    {
        $token = GoogleToken::current();

        if (! $token || $token->refresh_token === '') {
            throw new YouTubeApiException(__('music::messages.errors.not_connected'));
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('music.google.client_id'),
            'client_secret' => config('music.google.client_secret'),
            'refresh_token' => $token->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            Log::warning('MyMusic token refresh failed: '.$response->body());
            // invalid_grant = token revoked/expired → force a reconnect.
            if ($response->json('error') === 'invalid_grant') {
                $this->disconnect();
            }
            throw new YouTubeApiException(__('music::messages.errors.refresh'));
        }

        $data = $response->json();

        $token->update([
            'access_token' => $data['access_token'],
            'expires_at' => Carbon::now()->addSeconds((int) ($data['expires_in'] ?? 3600) - 60),
        ]);
    }
}
