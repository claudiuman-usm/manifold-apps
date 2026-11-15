<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The owner's real gear, typed as a list on 2026-11-15 and normalised here.
 * Runs once and only into an empty inventory, so it never overwrites edits.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('podplan_gear_items')->exists()) {
            return;
        }

        $types = DB::table('podplan_gear_types')->pluck('id', 'key');
        $marius = 'Marius Achiței';
        $george = 'George Mihăescu';
        $glam = 'Glam Art Media';

        // Per-item rule overrides (replace the type preset for that item).
        $sm7b = ['always' => [['text' => 'Mic arm or stand'], ['text' => 'XLR cable'], ['type_id' => (int) $types['mic_preamp']]]];
        $noStand = ['always' => [['text' => 'Power cable']]];          // PavoTube: stand added by hand when needed
        $canon = ['always' => [['type_id' => (int) $types['lens_adapter']]]];

        // [type key, name, short name, provider, quantity, overrides]
        $items = [
            ['camera', 'Blackmagic Studio Camera 4K Pro', 'Studio 4K Pro', null, 1, null],
            ['camera', 'Blackmagic Pocket Cinema Camera 4K #1', 'BMPCC 4K #1', null, 1, null],
            ['camera', 'Blackmagic Pocket Cinema Camera 4K #2', 'BMPCC 4K #2', null, 1, null],
            ['camera', 'Blackmagic Pocket Cinema Camera 4K #3', 'BMPCC 4K #3', null, 1, null],

            ['video_mixer', 'Blackmagic ATEM Mini Extreme ISO', 'ATEM Extreme ISO', null, 1, null],
            ['video_mixer', 'Blackmagic ATEM Mini Pro ISO', 'ATEM Pro ISO', null, 1, null],

            ['audio_mixer', 'Sound Devices MixPre-3', 'MixPre-3', null, 1, null],

            ['wired_mic', 'Shure SM7B #1', 'SM7B #1', null, 1, $sm7b],
            ['wired_mic', 'Shure SM7B #2', 'SM7B #2', null, 1, $sm7b],
            ['wired_mic', 'Shure SM7B #3', 'SM7B #3', null, 1, $sm7b],
            ['wired_mic', 'Shure SM7B #4', 'SM7B #4', null, 1, $sm7b],
            ['wired_mic', 'RØDE NTG4+ #1', 'NTG4+ #1', null, 1, null],
            ['wired_mic', 'RØDE NTG4+ #2', 'NTG4+ #2', null, 1, null],

            // Wireless kits: quantity is the number of lavs that go on the plan.
            ['wireless_mic', 'DJI Mic 2', 'DJI Mic 2', null, 2, null],
            ['wireless_mic', 'DJI Mic 3', 'DJI Mic 3', $marius, 2, null],
            ['wireless_mic', 'DJI Mic 3', 'DJI Mic 3', $george, 2, null],

            ['light', 'Nanlite Forza 500', 'Forza 500', null, 1, null],
            ['light', 'Nanlite Forza 720', 'Forza 720', $marius, 1, null],
            ['light', 'Nanlite PavoSlim', 'PavoSlim', null, 1, null],
            ['light', 'Nanlite PavoSlim', 'PavoSlim', $marius, 1, null],
            ['light', 'Nanlite PavoTube', 'PavoTube', $marius, 1, $noStand],

            ['tripod', 'Tripod', null, null, 4, null],
            ['tripod', 'Sachtler tripod', null, $marius, 1, null],
            ['light_stand', 'Light stand', null, null, 2, null],
            ['light_stand', 'Light stand', null, $marius, 2, null],

            ['ssd', 'Samsung T7', null, null, 3, null],
            ['ssd', 'SanDisk Professional G-DRIVE', null, null, 3, null],

            ['lens', 'Meike 35mm', null, null, 1, null],
            ['lens', 'Meike 85mm', null, null, 2, null],
            ['lens', 'Canon 35mm', null, null, 1, $canon],
            ['lens', 'Canon 50mm', null, null, 1, $canon],
            ['lens', 'Canon 70-200mm', null, $glam, 1, $canon],

            ['lens_adapter', 'Metabones Speed Booster XL 0.64x', null, null, 1, null],
            ['lens_adapter', 'Metabones Speed Booster Ultra 0.71x', null, null, 1, null],

            ['monitor', 'Portable monitor', null, null, 1, null],
            ['monitor', 'Atomos Ninja', null, null, 1, null],

            ['mic_preamp', 'Triton Audio FetHead', null, null, 4, null],
            ['timecode', 'Tentacle Sync', null, null, 1, null],
        ];

        $now = now();
        foreach ($items as $i => [$type, $name, $short, $provider, $qty, $overrides]) {
            DB::table('podplan_gear_items')->insert([
                'gear_type_id' => $types[$type],
                'name' => $name,
                'short_name' => $short,
                'provider' => $provider,
                'quantity' => $qty,
                'rule_overrides' => $overrides ? json_encode($overrides) : null,
                'sort' => ($i + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Inventory is user data once seeded; nothing to undo.
    }
};
