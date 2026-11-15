<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A camera's link to the video mixer is a cable by default but can be
 * wireless. This adds the two accessory kinds a wireless link needs, one of
 * each to the inventory, and gives the camera preset a "Connection" choice:
 * Cable brings the HDMI cable, Wireless brings a transmitter and a receiver.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $kinds = [
            ['video_tx', 'Wireless video transmitter', 132],
            ['video_rx', 'Wireless video receiver', 134],
        ];

        foreach ($kinds as [$key, $name, $sort]) {
            if (! DB::table('podplan_gear_types')->where('key', $key)->exists()) {
                DB::table('podplan_gear_types')->insert([
                    'key' => $key, 'name' => $name, 'category' => 'video', 'kind' => 'accessory',
                    'connection' => 'none', 'rules' => json_encode(['always' => [], 'live' => [], 'not_live' => [], 'ask' => []]),
                    'sort' => $sort, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        $types = DB::table('podplan_gear_types')->pluck('id', 'key');

        // One of each to start with; rename them and set the real count in Inventory.
        foreach ($kinds as [$key, $name]) {
            if (! DB::table('podplan_gear_items')->where('gear_type_id', $types[$key])->exists()) {
                DB::table('podplan_gear_items')->insert([
                    'gear_type_id' => $types[$key], 'name' => $name, 'quantity' => 1,
                    'sort' => (int) DB::table('podplan_gear_items')->max('sort') + 10,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // The HDMI cable moves out of the camera's "if live mixing" bucket and
        // into the Cable answer of the new choice, which only shows with live mixing.
        DB::table('podplan_gear_types')->where('key', 'camera')->update(['rules' => json_encode([
            'always' => [['type_id' => (int) $types['tripod']], ['text' => 'AC adapter'], ['type_id' => (int) $types['ssd']]],
            'live' => [],
            'not_live' => [],
            'ask' => [
                ['question' => 'Lens', 'type_id' => (int) $types['lens']],
                [
                    'question' => 'Connection',
                    'options' => [
                        ['label' => 'Cable', 'adds' => [['text' => 'HDMI cable to mixer']]],
                        ['label' => 'Wireless', 'wireless' => true, 'adds' => [
                            ['type_id' => (int) $types['video_tx']],
                            ['type_id' => (int) $types['video_rx']],
                        ]],
                    ],
                    'default' => 'Cable',
                    'when' => 'live',
                ],
            ],
        ]), 'updated_at' => $now]);
    }

    public function down(): void
    {
        // Inventory and presets are data once changed; nothing to undo.
    }
};
