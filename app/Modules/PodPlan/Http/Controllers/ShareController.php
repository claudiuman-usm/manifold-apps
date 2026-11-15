<?php

namespace App\Modules\PodPlan\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PodPlan\Models\Project;
use App\Modules\PodPlan\Support\ChecklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The shared packing session, reached by secret link with no login. It can
 * read one project's checklist and tick its rows - nothing else. A revoked or
 * mistyped token is a plain 404.
 */
class ShareController extends Controller
{
    public function __construct(protected ChecklistService $checklist)
    {
    }

    public function show(string $token): View
    {
        $project = $this->project($token);

        return view('podplan::share', [
            'project' => $project,
            'token' => $token,
            'payload' => $this->checklist->payload($project, false),
        ]);
    }

    public function state(string $token): JsonResponse
    {
        return response()->json($this->checklist->payload($this->project($token), false));
    }

    public function tick(Request $request, string $token): JsonResponse
    {
        $project = $this->project($token);
        $data = $request->validate(['key' => ['required', 'string', 'max:160'], 'packed' => ['required', 'boolean']]);
        abort_unless($this->checklist->tick($project, $data['key'], (bool) $data['packed']), 422);

        return response()->json($this->checklist->payload($project, false));
    }

    protected function project(string $token): Project
    {
        abort_unless(strlen($token) === 40, 404);

        return Project::where('share_token', $token)->firstOrFail();
    }
}
