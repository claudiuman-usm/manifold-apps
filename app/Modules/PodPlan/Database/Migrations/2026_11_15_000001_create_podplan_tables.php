<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gear types carry the built-in preset rules. kind = plan (individual
        // items placed on the diagram) or accessory (counted pools pulled in
        // by rules, never placed).
        Schema::create('podplan_gear_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('category');
            $table->string('kind');                       // plan | accessory
            $table->string('symbol')->nullable();         // cam | mic | wireless | light | box | recorder
            $table->string('role')->nullable();           // camera | video_mixer | audio_mixer | mic | light
            $table->string('connection')->default('none'); // hdmi | xlr | wireless | none
            $table->json('rules')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('podplan_gear_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gear_type_id')->constrained('podplan_gear_types');
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('provider')->nullable();       // null = mine, otherwise rented from
            $table->unsignedInteger('quantity')->default(1);
            $table->json('rule_overrides')->nullable();   // null = use the type preset
            $table->json('last_answers')->nullable();     // remembered "ask me" answers, by question
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Project-level checklist rules (not tied to one piece of gear).
        Schema::create('podplan_general_rules', function (Blueprint $table) {
            $table->id();
            $table->string('condition');                  // always | live | not_live
            $table->string('label')->nullable();
            $table->foreignId('gear_type_id')->nullable()->constrained('podplan_gear_types');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('podplan_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('shoot_type')->default('podcast');
            $table->date('shoot_date')->nullable();
            $table->string('location')->nullable();
            $table->unsignedInteger('people_count')->default(2);
            $table->boolean('live_mixing')->default(false);
            $table->text('description')->nullable();
            $table->string('share_token', 64)->nullable()->unique();
            $table->json('plan_undo')->nullable();
            $table->json('ai_result')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('podplan_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('podplan_projects')->cascadeOnDelete();
            $table->string('kind');                       // gear | person | furniture
            $table->foreignId('gear_item_id')->nullable()->constrained('podplan_gear_items');
            $table->string('label')->nullable();
            $table->float('x')->default(0);
            $table->float('y')->default(0);
            $table->float('rotation')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // Answers to "ask me every time" questions, per placed item.
        Schema::create('podplan_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('podplan_projects')->cascadeOnDelete();
            $table->foreignId('placement_id')->constrained('podplan_placements')->cascadeOnDelete();
            $table->string('question', 120);
            $table->foreignId('gear_item_id')->nullable()->constrained('podplan_gear_items');
            $table->string('value')->nullable();
            $table->timestamps();
            $table->unique(['placement_id', 'question']);
        });

        // Packed state of generated rows, keyed so a regenerated row keeps its tick.
        Schema::create('podplan_ticks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('podplan_projects')->cascadeOnDelete();
            $table->string('row_key', 160);
            $table->timestamps();
            $table->unique(['project_id', 'row_key']);
        });

        Schema::create('podplan_manual_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('podplan_projects')->cascadeOnDelete();
            $table->string('label');
            $table->boolean('packed')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        $this->seedTypes();
    }

    public function down(): void
    {
        foreach (['podplan_manual_items', 'podplan_ticks', 'podplan_answers', 'podplan_placements',
            'podplan_projects', 'podplan_general_rules', 'podplan_gear_items', 'podplan_gear_types'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Built-in gear types and their preset rules. A rule entry is either
     * ['text' => '...'] or ['type' => '<type key>'] (one unit from that
     * accessory pool); the keys are swapped for ids once every type exists.
     */
    protected function seedTypes(): void
    {
        $types = [
            // On the plan.
            ['camera', 'Camera', 'cameras', 'plan', 'cam', 'camera', 'hdmi', [
                'always' => [['type' => 'tripod'], ['text' => 'AC adapter'], ['type' => 'ssd']],
                'live' => [['text' => 'HDMI cable to mixer']],
                'ask' => [['question' => 'Lens', 'type' => 'lens']],
            ]],
            ['video_mixer', 'Video mixer', 'mixing', 'plan', 'box', 'video_mixer', 'none', [
                'always' => [['type' => 'ssd'], ['text' => 'AC adapter'], ['text' => 'Short HDMI cable'], ['type' => 'monitor']],
            ]],
            ['audio_mixer', 'Audio mixer / recorder', 'audio', 'plan', 'recorder', 'audio_mixer', 'none', []],
            ['wired_mic', 'Wired microphone', 'audio', 'plan', 'mic', 'mic', 'xlr', [
                'always' => [['text' => 'Mic arm or stand'], ['text' => 'XLR cable']],
            ]],
            ['wireless_mic', 'Wireless microphone', 'audio', 'plan', 'wireless', 'mic', 'wireless', []],
            ['light', 'Light', 'lighting', 'plan', 'light', 'light', 'none', [
                'always' => [['type' => 'light_stand'], ['text' => 'Power cable']],
            ]],
            ['other_plan', 'Other gear (on the plan)', 'other', 'plan', 'box', null, 'none', []],
            // Accessories.
            ['tripod', 'Tripod', 'support', 'accessory', null, null, 'none', []],
            ['light_stand', 'Light stand', 'support', 'accessory', null, null, 'none', []],
            ['ssd', 'SSD / drive', 'storage', 'accessory', null, null, 'none', []],
            ['lens', 'Lens', 'lenses', 'accessory', null, null, 'none', []],
            ['lens_adapter', 'Lens adapter', 'lenses', 'accessory', null, null, 'none', []],
            ['monitor', 'Monitor', 'monitoring', 'accessory', null, null, 'none', []],
            ['mic_preamp', 'Mic preamp', 'audio', 'accessory', null, null, 'none', []],
            ['timecode', 'Timecode', 'sync', 'accessory', null, null, 'none', []],
            ['other_accessory', 'Other accessory', 'other', 'accessory', null, null, 'none', []],
        ];

        $now = now();
        foreach ($types as $i => [$key, $name, $category, $kind, $symbol, $role, $connection]) {
            DB::table('podplan_gear_types')->insert([
                'key' => $key, 'name' => $name, 'category' => $category, 'kind' => $kind,
                'symbol' => $symbol, 'role' => $role, 'connection' => $connection,
                'sort' => ($i + 1) * 10, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $ids = DB::table('podplan_gear_types')->pluck('id', 'key');
        $resolve = function (array $entry) use ($ids): array {
            if (isset($entry['type'])) {
                $entry['type_id'] = (int) $ids[$entry['type']];
                unset($entry['type']);
            }

            return $entry;
        };

        foreach ($types as [$key, , , , , , , $rules]) {
            $resolved = [];
            foreach (['always', 'live', 'not_live', 'ask'] as $bucket) {
                $resolved[$bucket] = array_map($resolve, $rules[$bucket] ?? []);
            }
            DB::table('podplan_gear_types')->where('key', $key)->update(['rules' => json_encode($resolved)]);
        }

        // Project-level rules.
        $general = [
            ['always', 'Extension cord and power strip', null],
            ['always', 'Gaffer tape', null],
            ['not_live', null, 'timecode'],   // timecode is mandatory when nothing is mixed live
        ];
        foreach ($general as $i => [$condition, $label, $typeKey]) {
            DB::table('podplan_general_rules')->insert([
                'condition' => $condition, 'label' => $label,
                'gear_type_id' => $typeKey ? $ids[$typeKey] : null,
                'sort' => ($i + 1) * 10, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
};
