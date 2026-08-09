<?php

namespace App\Modules\Flower\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Flower\Models\Client;
use App\Modules\Flower\Models\Step;
use App\Modules\Flower\Models\Template;
use App\Modules\Flower\Models\Type;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TemplateController extends Controller
{
    /** Flower home: templates grouped Client -> Type. */
    public function index(): View
    {
        $clients = Client::query()
            ->with(['types' => function ($q) {
                $q->orderBy('name')->with(['templates' => function ($q) {
                    $q->orderBy('name')->with('activeRun')->withCount(['steps', 'completedRuns']);
                }]);
            }])
            ->orderBy('name')
            ->get();

        return view('flower::templates.index', compact('clients'));
    }

    public function create(): View
    {
        return view('flower::templates.create', [
            'clientNames' => $this->clientNames(),
            'typeNames' => $this->typeNames(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTemplate($request);

        $template = DB::transaction(function () use ($data) {
            $type = $this->resolveType($data['client'], $data['type']);

            return Template::create([
                'type_id' => $type->id,
                'name' => $data['name'],
            ]);
        });

        return redirect()
            ->route('flower.templates.edit', $template)
            ->with('status', __('flower::messages.template.created'));
    }

    public function edit(Template $template): View
    {
        $template->load(['steps', 'type.client']);

        return view('flower::templates.edit', [
            'template' => $template,
            'clientNames' => $this->clientNames(),
            'typeNames' => $this->typeNames(),
        ]);
    }

    public function update(Request $request, Template $template): RedirectResponse
    {
        $data = $this->validateTemplate($request);

        $steps = $request->input('steps', []);

        DB::transaction(function () use ($template, $data, $steps) {
            $type = $this->resolveType($data['client'], $data['type']);

            $template->update([
                'type_id' => $type->id,
                'name' => $data['name'],
            ]);

            $this->syncSteps($template, is_array($steps) ? $steps : []);
        });

        return redirect()
            ->route('flower.templates.edit', $template)
            ->with('status', __('flower::messages.template.updated'));
    }

    public function destroy(Template $template): RedirectResponse
    {
        $template->delete();

        return redirect()
            ->route('flower.index')
            ->with('status', __('flower::messages.template.deleted'));
    }

    /** @return array{name:string,client:string,type:string} */
    protected function validateTemplate(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'client' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
        ]);

        return [
            'name' => trim($validated['name']),
            'client' => trim($validated['client']),
            'type' => trim($validated['type']),
        ];
    }

    /** Find or create the client + type combination by name. */
    protected function resolveType(string $clientName, string $typeName): Type
    {
        $client = Client::firstOrCreate(['name' => $clientName]);

        return Type::firstOrCreate([
            'client_id' => $client->id,
            'name' => $typeName,
        ]);
    }

    /**
     * Reconcile the template's steps with the submitted ordered list.
     * DOM order = array order = position. Missing existing steps are soft-deleted.
     *
     * @param  array<int,array<string,mixed>>  $rows
     */
    protected function syncSteps(Template $template, array $rows): void
    {
        $keptIds = [];
        $position = 0;

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $id = $row['id'] ?? null;

            if (! empty($id) && $step = $template->steps()->whereKey($id)->first()) {
                $step->update(['name' => $name, 'position' => $position]);
            } else {
                $step = $template->steps()->create(['name' => $name, 'position' => $position]);
            }

            $keptIds[] = $step->id;
            $position++;
        }

        $template->steps()
            ->whereNotIn('id', $keptIds ?: [0])
            ->get()
            ->each
            ->delete();
    }

    /** @return array<int,string> */
    protected function clientNames(): array
    {
        return Client::query()->orderBy('name')->pluck('name')->unique()->values()->all();
    }

    /** @return array<int,string> */
    protected function typeNames(): array
    {
        return Type::query()->orderBy('name')->pluck('name')->unique()->values()->all();
    }
}
