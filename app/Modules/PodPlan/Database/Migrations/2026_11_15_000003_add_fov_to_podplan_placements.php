<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A hand-set field of view per placement (null = automatic: from the lens for
 * a camera, a standard beam for a light). Lights now turn like cameras, so
 * any light already on a plan is aimed at its table instead of straight up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('podplan_placements', function (Blueprint $table) {
            $table->float('fov')->nullable()->after('rotation');
        });

        $lights = DB::table('podplan_placements as p')
            ->join('podplan_gear_items as i', 'i.id', '=', 'p.gear_item_id')
            ->join('podplan_gear_types as t', 't.id', '=', 'i.gear_type_id')
            ->where('t.role', 'light')->where('p.rotation', 0)
            ->get(['p.id', 'p.project_id', 'p.x', 'p.y']);

        foreach ($lights as $light) {
            $table = DB::table('podplan_placements')
                ->where('project_id', $light->project_id)->where('kind', 'furniture')->first();
            $tx = $table ? $table->x + 100 : 330;
            $ty = $table ? $table->y + 40 : 235;

            // 0 degrees points up the canvas; angles grow clockwise.
            $rotation = round(rad2deg(atan2($tx - ($light->x + 17), -($ty - ($light->y + 17)))));
            DB::table('podplan_placements')->where('id', $light->id)->update(['rotation' => $rotation]);
        }
    }

    public function down(): void
    {
        Schema::table('podplan_placements', function (Blueprint $table) {
            $table->dropColumn('fov');
        });
    }
};
