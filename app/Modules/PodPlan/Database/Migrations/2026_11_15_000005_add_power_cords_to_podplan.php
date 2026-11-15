<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every camera and every light needs a power cord on the checklist. Cameras
 * get one after the AC adapter; for lights the existing "Power cable" line is
 * renamed so there is one name for the same thing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Camera preset: a power cord right after the AC adapter.
        $camera = DB::table('podplan_gear_types')->where('key', 'camera')->first();
        if ($camera) {
            $rules = json_decode($camera->rules ?? '[]', true) ?: [];
            $always = $rules['always'] ?? [];
            if (! in_array('Power cord', array_column($always, 'text'), true)) {
                $at = count($always);
                foreach ($always as $i => $entry) {
                    if (($entry['text'] ?? null) === 'AC adapter') {
                        $at = $i + 1;
                        break;
                    }
                }
                array_splice($always, $at, 0, [['text' => 'Power cord']]);
                $rules['always'] = $always;
                DB::table('podplan_gear_types')->where('id', $camera->id)
                    ->update(['rules' => json_encode($rules), 'updated_at' => $now]);
            }
        }

        // Lights: "Power cable" becomes "Power cord", in the preset and in any item's own rules.
        $rename = function (?string $json): ?string {
            $rules = json_decode($json ?? '', true);
            if (! is_array($rules)) {
                return null;
            }
            $changed = false;
            foreach (['always', 'live', 'not_live'] as $bucket) {
                foreach ($rules[$bucket] ?? [] as $i => $entry) {
                    if (($entry['text'] ?? null) === 'Power cable') {
                        $rules[$bucket][$i]['text'] = 'Power cord';
                        $changed = true;
                    }
                }
            }

            return $changed ? json_encode($rules) : null;
        };

        $light = DB::table('podplan_gear_types')->where('key', 'light')->first();
        if ($light) {
            if ($renamed = $rename($light->rules)) {
                DB::table('podplan_gear_types')->where('id', $light->id)->update(['rules' => $renamed, 'updated_at' => $now]);
            }
            foreach (DB::table('podplan_gear_items')->where('gear_type_id', $light->id)->whereNotNull('rule_overrides')->get() as $item) {
                if ($renamed = $rename($item->rule_overrides)) {
                    DB::table('podplan_gear_items')->where('id', $item->id)->update(['rule_overrides' => $renamed, 'updated_at' => $now]);
                }
            }
        }
    }

    public function down(): void
    {
        // Presets are data once changed; nothing to undo.
    }
};
