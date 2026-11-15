<?php

namespace App\Modules\PodPlan\Support;

use App\Modules\PodPlan\Models\Project;

/**
 * Which project the module navigation's "Plan" and "Checklist" links open:
 * the one being looked at, else the one opened last, else the next shoot
 * coming up, else the most recently changed.
 */
class CurrentProject
{
    protected const KEY = 'podplan.project';

    public static function remember(Project $project): void
    {
        session([self::KEY => $project->id]);
    }

    public static function resolve(?Project $given = null): ?Project
    {
        if ($given && $given->exists) {
            return $given;
        }

        if (($id = session(self::KEY)) && ($project = Project::find($id))) {
            return $project;
        }

        return Project::whereDate('shoot_date', '>=', today())->orderBy('shoot_date')->first()
            ?? Project::latest('updated_at')->latest('id')->first();
    }
}
