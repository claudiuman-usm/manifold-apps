<?php

namespace App\Modules\MyMusic\Support;

use Anthropic\Client;
use Illuminate\Support\Facades\Log;

/**
 * Turns listening statistics into a short, friendly "taste profile" narrative
 * with rediscovery suggestions, via Claude. Reuses the Anthropic PHP SDK and
 * ANTHROPIC_API_KEY already used by the Receipts module. Fails soft (returns
 * null) with no key or on any error, so the stats page never breaks.
 */
class TasteAnalyzer
{
    /** @param array<string,mixed> $stats */
    public function analyze(array $stats): ?string
    {
        $apiKey = config('music.ai.api_key');
        if (empty($apiKey)) {
            return null;
        }

        try {
            $client = new Client(apiKey: $apiKey);

            $message = $client->messages->create(
                maxTokens: 900,
                model: config('music.ai.model', 'claude-opus-4-8'),
                system: 'You are a warm, sharp music writer helping someone rediscover their own '
                    .'YouTube music library. Given their listening stats, write a short taste profile '
                    .'(2–3 tight paragraphs), then a bulleted "Worth revisiting" list drawn from their '
                    .'lost favourites and lesser-played tracks, and one line suggesting a direction to '
                    .'explore next based on their genres. Be specific and reference their actual artists/'
                    .'tracks. No headings other than "Worth revisiting". Plain text, no preamble.',
                messages: [[
                    'role' => 'user',
                    'content' => 'Here are my listening stats as JSON:'."\n\n"
                        .json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                ]],
            );

            $text = '';
            foreach ($message->content as $block) {
                if (($block->type ?? null) === 'text') {
                    $text .= $block->text;
                }
            }

            return trim($text) ?: null;
        } catch (\Throwable $e) {
            Log::warning('MyMusic taste analysis failed: '.$e->getMessage());

            return null;
        }
    }
}
