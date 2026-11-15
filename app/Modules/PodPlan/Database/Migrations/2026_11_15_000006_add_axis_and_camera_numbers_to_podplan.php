<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The axis of action (the 180-degree line) is part of a project's plan, and
 * every camera on a plan carries a number. Cameras already on a plan are
 * numbered in the order they were placed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('podplan_projects', function (Blueprint $table) {
            $table->json('axis')->nullable()->after('live_mixing');   // {on, x1, y1, x2, y2}
        });
        Schema::table('podplan_placements', function (Blueprint $table) {
            $table->unsignedSmallInteger('number')->nullable()->after('fov');
        });

        $cameras = DB::table('podplan_placements as p')
            ->join('podplan_gear_items as i', 'i.id', '=', 'p.gear_item_id')
            ->join('podplan_gear_types as t', 't.id', '=', 'i.gear_type_id')
            ->where('t.role', 'camera')
            ->orderBy('p.project_id')->orderBy('p.id')
            ->get(['p.id', 'p.project_id']);

        $next = [];
        foreach ($cameras as $camera) {
            $next[$camera->project_id] = ($next[$camera->project_id] ?? 0) + 1;
            DB::table('podplan_placements')->where('id', $camera->id)->update(['number' => $next[$camera->project_id]]);
        }
    }

    public function down(): void
    {
        Schema::table('podplan_placements', function (Blueprint $table) {
            $table->dropColumn('number');
        });
        Schema::table('podplan_projects', function (Blueprint $table) {
            $table->dropColumn('axis');
        });
    }
};
