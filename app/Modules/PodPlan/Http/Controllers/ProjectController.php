<?php

namespace App\Modules\PodPlan\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PodPlan\Models\Project;
use App\Modules\PodPlan\Support\ChecklistBuilder;
use App\Modules\PodPlan\Support\PlanDescriber;
use App\Modules\PodPlan\Support\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(ChecklistBuilder $builder): View
    {
        $projects = Project::query()
            ->orderByRaw('shoot_date is null')
            ->orderByDesc('shoot_date')
            ->orderByDesc('id')
            ->get();

        $cards = $projects->map(function (Project $project) use ($builder) {
            $checklist = $builder->build($project);

            return [
                'project' => $project,
                'cameras' => $project->placements()->where('kind', 'gear')
                    ->whereHas('item.type', fn ($q) => $q->where('role', 'camera'))->count(),
                'total' => $checklist['total'],
                'packed' => $checklist['packed'],
            ];
        });

        return view('podplan::index', ['cards' => $cards]);
    }

    public function create(PlanDescriber $describer): View
    {
        return view('podplan::form', [
            'project' => new Project(['shoot_type' => 'podcast', 'people_count' => 2, 'live_mixing' => false]),
            'aiEnabled' => $describer->enabled(),
        ]);
    }

    public function store(Request $request, PlanService $plan, PlanDescriber $describer): RedirectResponse
    {
        $data = $this->validated($request);
        $project = Project::create($data);
        $plan->seedSet($project);

        // "Generate plan" hands the description to Claude straight away;
        // "Start with an empty plan" leaves just the table and the people.
        if ($request->input('start') === 'generate' && trim((string) $project->description) !== '') {
            $error = $plan->generate($project, $project->description, $describer);

            return redirect()->route('podplan.plan', ['project' => $project, 'mode' => 'describe'])
                ->with($error ? 'pp_error' : 'status', $error
                    ? __('podplan::messages.describe.errors.'.$error)
                    : __('podplan::messages.flash.generated'));
        }

        return redirect()->route('podplan.plan', $project);
    }

    public function edit(Project $project): View
    {
        return view('podplan::form', ['project' => $project, 'aiEnabled' => false]);
    }

    public function update(Request $request, Project $project, PlanService $plan): RedirectResponse
    {
        $people = $project->people_count;
        $project->update($this->validated($request));

        if ($project->people_count !== $people) {
            $plan->syncPeople($project);
        }

        return redirect()->route('podplan.plan', $project)
            ->with('status', __('podplan::messages.flash.saved'));
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return redirect()->route('podplan.index')
            ->with('status', __('podplan::messages.flash.deleted'));
    }

    /** @return array<string,mixed> */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'shoot_type' => ['required', Rule::in(Project::SHOOT_TYPES)],
            'shoot_date' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:160'],
            'people_count' => ['required', 'integer', 'min:0', 'max:8'],
            'live_mixing' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:4000'],
        ]);
        $data['live_mixing'] = (bool) $data['live_mixing'];

        return $data;
    }
}
