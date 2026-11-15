<?php

namespace App\Modules\PodPlan\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PodPlan\Models\GearItem;
use App\Modules\PodPlan\Models\Placement;
use App\Modules\PodPlan\Models\Project;
use App\Modules\PodPlan\Support\CurrentProject;
use App\Modules\PodPlan\Support\PlanDescriber;
use App\Modules\PodPlan\Support\PlanService;
use App\Modules\PodPlan\Support\Rules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Plan screen. Every mutating endpoint answers with the whole plan state
 * (placements, cables, sidebar gear, checklist preview), so the page redraws
 * from one payload and can never drift from the server.
 */
class PlanController extends Controller
{
    public function __construct(protected PlanService $plan)
    {
    }

    public function show(Project $project, PlanDescriber $describer): View
    {
        CurrentProject::remember($project);

        return view('podplan::plan', [
            'project' => $project,
            'state' => $this->plan->state($project),
            'aiEnabled' => $describer->enabled(),
        ]);
    }

    public function state(Project $project): JsonResponse
    {
        return response()->json($this->plan->state($project));
    }

    public function add(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate(['gear_item_id' => ['required', 'integer']]);
        $item = GearItem::with('type')->findOrFail($data['gear_item_id']);

        abort_unless($item->type->isPlan(), 422);
        $this->plan->addGear($project, $item);

        return $this->state($project);
    }

    public function move(Request $request, Project $project, Placement $placement): JsonResponse
    {
        abort_unless($placement->project_id === $project->id, 404);

        $data = $request->validate([
            'x' => ['nullable', 'numeric'],
            'y' => ['nullable', 'numeric'],
            'rotation' => ['nullable', 'numeric'],
            'fov' => ['nullable', 'numeric', 'min:5', 'max:160'],
            'fov_auto' => ['nullable', 'boolean'],
            'number' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        // Cameras and lights point somewhere; everything else stays square to the canvas.
        $aims = in_array($placement->item?->type->role, ['camera', 'light'], true);

        $this->plan->move(
            $placement,
            isset($data['x']) ? (float) $data['x'] : null,
            isset($data['y']) ? (float) $data['y'] : null,
            isset($data['rotation']) && $aims ? (float) $data['rotation'] : null,
        );

        // "number" present but empty means: take the number off.
        if ($request->exists('number') && $placement->item?->type->role === 'camera') {
            $this->plan->setNumber($placement, isset($data['number']) ? (int) $data['number'] : null);
        }

        if ($aims && $request->boolean('fov_auto')) {
            $this->plan->setFov($placement, null);
        } elseif ($aims && isset($data['fov'])) {
            $this->plan->setFov($placement, (float) $data['fov']);
        }

        return $this->state($project);
    }

    public function remove(Project $project, Placement $placement): JsonResponse
    {
        abort_unless($placement->project_id === $project->id, 404);
        abort_unless($placement->kind === 'gear', 422);

        $this->plan->remove($placement);

        return $this->state($project);
    }

    public function live(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate(['live' => ['required', 'boolean']]);
        $project->update(['live_mixing' => (bool) $data['live']]);

        return $this->state($project);
    }

    /** Show, hide or move the axis of action. */
    public function axis(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'on' => ['required', 'boolean'],
            'x1' => ['nullable', 'numeric'], 'y1' => ['nullable', 'numeric'],
            'x2' => ['nullable', 'numeric'], 'y2' => ['nullable', 'numeric'],
            'reset' => ['nullable', 'boolean'],
        ]);

        $this->plan->setAxis($project, (bool) $data['on'], array_filter(
            array_intersect_key($data, array_flip(['x1', 'y1', 'x2', 'y2'])),
            fn ($v) => $v !== null,
        ), $request->boolean('reset'));

        return $this->state($project);
    }

    public function answer(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'placement_id' => ['required', 'integer'],
            'question' => ['required', 'string', 'max:120'],
            'gear_item_id' => ['nullable', 'integer'],
            'value' => ['nullable', 'string', 'max:160'],
        ]);

        $placement = $project->placements()->where('kind', 'gear')->with('item.type')->findOrFail($data['placement_id']);

        // The answer must be one the item's own rules offer.
        $ask = collect($placement->item->effectiveRules()['ask'])->firstWhere('question', $data['question']);
        abort_unless($ask, 422);

        $itemId = $data['gear_item_id'] ?? null;
        if ($itemId) {
            abort_unless(isset($ask['type_id'])
                && GearItem::whereKey($itemId)->where('gear_type_id', $ask['type_id'])->exists(), 422);
        } elseif (($data['value'] ?? '') !== '' && isset($ask['options'])) {
            abort_unless(in_array($data['value'], array_map([Rules::class, 'optionLabel'], $ask['options']), true), 422);
        }

        $this->plan->answer($placement, $data['question'], $itemId, $data['value'] ?? null);

        return $this->state($project);
    }

    public function describe(Request $request, Project $project, PlanDescriber $describer): JsonResponse
    {
        $data = $request->validate(['prompt' => ['required', 'string', 'max:4000']]);
        $error = $this->plan->generate($project, $data['prompt'], $describer);

        return response()->json($this->plan->state($project) + [
            'error' => $error ? __('podplan::messages.describe.errors.'.$error) : null,
        ]);
    }

    public function keep(Project $project): JsonResponse
    {
        $project->update(['plan_undo' => null]);

        return $this->state($project);
    }

    public function undo(Project $project): JsonResponse
    {
        if ($undo = $project->plan_undo) {
            $this->plan->applyLayout($project, $undo['placements'] ?? [], $undo['answers'] ?? []);
            $project->update(['plan_undo' => null, 'ai_result' => null]);
        }

        return $this->state($project);
    }
}
