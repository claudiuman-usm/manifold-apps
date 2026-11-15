<?php

namespace App\Modules\PodPlan\Support;

/**
 * The four rule buckets a piece of gear carries:
 *   always   - added whenever the gear is on the plan
 *   live     - added only when live mixing is on
 *   not_live - added only when live mixing is off
 *   ask      - a question; the answer becomes a checklist row
 *
 * A bucket entry is ['text' => '...'] or ['type_id' => N] (one unit from that
 * accessory pool). An ask entry is ['question' => '...', 'type_id' => N]
 * (choose from that pool) or ['question' => '...', 'options' => [...]].
 *
 * An option is a plain string, or ['label' => '...', 'adds' => [entries]]
 * when choosing it should put things on the checklist ("Wireless" adds a
 * transmitter and a receiver); 'wireless' => true also stops the cable line
 * being drawn. An ask may carry 'default' (an option label, so it is never
 * left open) and 'when' => live | not_live (only asked in that mode).
 */
class Rules
{
    public const BUCKETS = ['always', 'live', 'not_live'];

    /** @return array{always:list<array>,live:list<array>,not_live:list<array>,ask:list<array>} */
    public static function normalize(mixed $rules): array
    {
        $rules = is_array($rules) ? $rules : [];
        $out = ['always' => [], 'live' => [], 'not_live' => [], 'ask' => []];

        foreach (self::BUCKETS as $bucket) {
            foreach ((array) ($rules[$bucket] ?? []) as $entry) {
                if ($clean = self::entry($entry)) {
                    $out[$bucket][] = $clean;
                }
            }
        }

        foreach ((array) ($rules['ask'] ?? []) as $ask) {
            if (! is_array($ask)) {
                continue;
            }
            $question = trim((string) ($ask['question'] ?? ''));
            if ($question === '') {
                continue;
            }
            $entry = ['question' => mb_substr($question, 0, 120)];

            if (! empty($ask['type_id'])) {
                $entry['type_id'] = (int) $ask['type_id'];
            } else {
                $options = [];
                foreach ((array) ($ask['options'] ?? []) as $option) {
                    if ($clean = self::option($option)) {
                        $options[] = $clean;
                    }
                }
                if (! $options) {
                    continue;
                }
                $entry['options'] = $options;

                $default = trim((string) ($ask['default'] ?? ''));
                if ($default !== '' && in_array($default, array_map([self::class, 'optionLabel'], $options), true)) {
                    $entry['default'] = $default;
                }
            }

            if (in_array($ask['when'] ?? null, ['live', 'not_live'], true)) {
                $entry['when'] = $ask['when'];
            }

            $out['ask'][] = $entry;
        }

        return $out;
    }

    /** @return string|array<string,mixed>|null */
    protected static function option(mixed $option): string|array|null
    {
        if (! is_array($option)) {
            $label = trim((string) $option);

            return $label === '' ? null : mb_substr($label, 0, 80);
        }

        $label = trim((string) ($option['label'] ?? ''));
        if ($label === '') {
            return null;
        }

        $out = ['label' => mb_substr($label, 0, 80)];
        if (! empty($option['wireless'])) {
            $out['wireless'] = true;
        }
        $adds = array_values(array_filter(array_map([self::class, 'entry'], (array) ($option['adds'] ?? []))));
        if ($adds) {
            $out['adds'] = $adds;
        }

        // An option that does nothing extra is stored as the plain label.
        return count($out) === 1 ? $out['label'] : $out;
    }

    public static function optionLabel(string|array $option): string
    {
        return is_array($option) ? (string) $option['label'] : $option;
    }

    /** @return array{text:string}|array{type_id:int}|null */
    protected static function entry(mixed $entry): ?array
    {
        if (! is_array($entry)) {
            return null;
        }
        if (! empty($entry['type_id'])) {
            return ['type_id' => (int) $entry['type_id']];
        }
        $text = trim((string) ($entry['text'] ?? ''));

        return $text === '' ? null : ['text' => mb_substr($text, 0, 160)];
    }

    public static function same(mixed $a, mixed $b): bool
    {
        return json_encode(self::normalize($a)) === json_encode(self::normalize($b));
    }

    /** Stable identity of an entry, used to tell an override from the preset. */
    public static function signature(array $entry): string
    {
        return isset($entry['type_id'])
            ? 'type:'.$entry['type_id'].(isset($entry['question']) ? ':'.$entry['question'] : '')
            : 'text:'.mb_strtolower($entry['text'] ?? ($entry['question'] ?? ''));
    }
}
