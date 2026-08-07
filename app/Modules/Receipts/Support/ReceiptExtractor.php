<?php

namespace App\Modules\Receipts\Support;

use Anthropic\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Extracts structured fields from a receipt image using Claude vision.
 *
 * Uses a forced tool-call so the model must return the fields in a fixed shape.
 * Fails soft: with no API key or on any error it returns null, and the caller
 * leaves the receipt in "review" for manual entry — uploads never break.
 */
class ReceiptExtractor
{
    /**
     * @param  list<string>  $categories  Allowed category names (constrains the model).
     * @return array{merchant:?string,amount:?float,currency:?string,purchased_at:?string,category:?string}|null
     */
    public function extract(string $base64Image, string $mimeType, array $categories): ?array
    {
        $apiKey = config('receipts.api_key');
        if (empty($apiKey)) {
            return null;
        }

        try {
            $client = new Client(apiKey: $apiKey);

            $message = $client->messages->create(
                maxTokens: 1024,
                model: config('receipts.model', 'claude-opus-4-8'),
                system: 'You read a photo of a purchase receipt and record its fields. '
                    .'Use the currency exactly as printed. If a field is unreadable, omit it. '
                    .'Pick the single closest category from the provided list.',
                messages: [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => [
                            'type' => 'base64', 'media_type' => $mimeType, 'data' => $base64Image,
                        ]],
                        ['type' => 'text', 'text' => 'Record the fields from this receipt.'],
                    ],
                ]],
                tools: [[
                    'name' => 'record_receipt',
                    'description' => 'Record the extracted fields of a purchase receipt.',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'merchant' => ['type' => 'string', 'description' => 'Store / vendor name'],
                            'amount' => ['type' => 'number', 'description' => 'Grand total paid'],
                            'currency' => ['type' => 'string', 'description' => 'ISO 4217 code, e.g. RON, EUR, USD'],
                            'date' => ['type' => 'string', 'description' => 'Purchase date as YYYY-MM-DD'],
                            'category' => ['type' => 'string', 'enum' => array_values($categories)],
                        ],
                    ],
                ]],
                toolChoice: ['type' => 'tool', 'name' => 'record_receipt'],
            );

            foreach ($message->content as $block) {
                if (($block->type ?? null) === 'tool_use') {
                    return $this->normalize((array) $block->input);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Receipt extraction failed: '.$e->getMessage());
        }

        return null;
    }

    /** @return array{merchant:?string,amount:?float,currency:?string,purchased_at:?string,category:?string} */
    protected function normalize(array $input): array
    {
        $date = null;
        if (! empty($input['date'])) {
            try {
                $date = Carbon::parse((string) $input['date'])->toDateString();
            } catch (\Throwable) {
                $date = null;
            }
        }

        return [
            'merchant' => isset($input['merchant']) ? trim((string) $input['merchant']) : null,
            'amount' => isset($input['amount']) && is_numeric($input['amount']) ? (float) $input['amount'] : null,
            'currency' => isset($input['currency']) ? strtoupper(trim((string) $input['currency'])) : null,
            'purchased_at' => $date,
            'category' => isset($input['category']) ? trim((string) $input['category']) : null,
        ];
    }
}
