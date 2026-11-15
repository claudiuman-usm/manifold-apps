<?php

namespace App\Modules\PodPlan\Support;

use App\Modules\PodPlan\Models\ManualItem;
use App\Modules\PodPlan\Models\Project;
use App\Modules\PodPlan\Models\Tick;

/**
 * Packing state shared by the owner's Checklist screen and the secret-link
 * page colleagues use: the same payload and the same tick logic, so both
 * sides always see one list.
 */
class ChecklistService
{
    public function __construct(protected ChecklistBuilder $builder)
    {
    }

    public function payload(Project $project, bool $owner): array
    {
        $payload = [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'type' => __('podplan::messages.shoot_types.'.$project->shoot_type),
                'date' => $project->shoot_date?->translatedFormat('j M Y'),
                'location' => $project->location,
                'live' => (bool) $project->live_mixing,
            ],
            'checklist' => $this->builder->build($project),
        ];

        if ($owner) {
            $payload['share_url'] = $project->share_token
                ? route('podplan.share.show', $project->share_token)
                : null;
        }

        return $payload;
    }

    /** Tick or untick one row. Returns false when the row is not on the list. */
    public function tick(Project $project, string $key, bool $packed): bool
    {
        if (str_starts_with($key, 'm:')) {
            $item = ManualItem::where('project_id', $project->id)->find((int) substr($key, 2));
            if (! $item) {
                return false;
            }
            $item->update(['packed' => $packed]);

            return true;
        }

        if (! in_array($key, $this->keys($this->builder->build($project)), true)) {
            return false;
        }

        if ($packed) {
            Tick::firstOrCreate(['project_id' => $project->id, 'row_key' => $key]);
        } else {
            Tick::where('project_id', $project->id)->where('row_key', $key)->delete();
        }

        return true;
    }

    /** Unpack everything, so the same project can be used for the next shoot. */
    public function reset(Project $project): void
    {
        Tick::where('project_id', $project->id)->delete();
        ManualItem::where('project_id', $project->id)->update(['packed' => false]);
    }

    /** @return list<string> */
    protected function keys(array $checklist): array
    {
        $keys = array_column($checklist['general'], 'key');
        foreach ($checklist['sections'] as $section) {
            $keys = array_merge($keys, array_column($section['rows'], 'key'));
        }

        return $keys;
    }
}
