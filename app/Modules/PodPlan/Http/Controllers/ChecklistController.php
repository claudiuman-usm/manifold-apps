<?php

namespace App\Modules\PodPlan\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PodPlan\Models\ManualItem;
use App\Modules\PodPlan\Models\Project;
use App\Modules\PodPlan\Support\ChecklistService;
use App\Modules\PodPlan\Support\CurrentProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** The Checklist screen: the only place items get ticked. */
class ChecklistController extends Controller
{
    public function __construct(protected ChecklistService $checklist)
    {
    }

    public function show(Project $project): View
    {
        CurrentProject::remember($project);

        return view('podplan::checklist', [
            'project' => $project,
            'payload' => $this->checklist->payload($project, true),
        ]);
    }

    public function state(Project $project): JsonResponse
    {
        return $this->payload($project);
    }

    public function tick(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate(['key' => ['required', 'string', 'max:160'], 'packed' => ['required', 'boolean']]);
        abort_unless($this->checklist->tick($project, $data['key'], (bool) $data['packed']), 422);

        return $this->payload($project);
    }

    public function addManual(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:160']]);

        $project->manualItems()->create([
            'label' => trim($data['label']),
            'sort' => (int) $project->manualItems()->max('sort') + 1,
        ]);

        return $this->payload($project);
    }

    public function removeManual(Project $project, ManualItem $item): JsonResponse
    {
        abort_unless($item->project_id === $project->id, 404);
        $item->delete();

        return $this->payload($project);
    }

    public function reset(Project $project): JsonResponse
    {
        $this->checklist->reset($project);

        return $this->payload($project);
    }

    /** Create the secret link (or replace it, which cuts off the old one). */
    public function share(Project $project): JsonResponse
    {
        $project->update(['share_token' => Str::random(40)]);

        return $this->payload($project);
    }

    public function unshare(Project $project): JsonResponse
    {
        $project->update(['share_token' => null]);

        return $this->payload($project);
    }

    protected function payload(Project $project): JsonResponse
    {
        return response()->json($this->checklist->payload($project->refresh(), true));
    }
}
