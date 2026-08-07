<?php

namespace App\Modules\Flower\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Flower\Models\Run;
use App\Modules\Flower\Models\Step;
use App\Modules\Flower\Models\StepLog;
use App\Modules\Flower\Models\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RunController extends Controller
{
    /** Start a new run of a template and open the timer on step 1. */
    public function start(Template $template): RedirectResponse
    {
        $template->load('steps');

        if ($template->steps->isEmpty()) {
            return redirect()
                ->route('flower.templates.edit', $template)
                ->with('status', __('flower::messages.run.no_steps'));
        }

        $run = DB::transaction(function () use ($template) {
            $now = Carbon::now();

            $run = Run::create([
                'template_id' => $template->id,
                'status' => Run::STATUS_IN_PROGRESS,
                'started_at' => $now,
            ]);

            StepLog::create([
                'run_id' => $run->id,
                'step_id' => $template->steps->first()->id,
                'started_at' => $now,
            ]);

            return $run;
        });

        return redirect()->route('flower.runs.show', $run);
    }

    public function show(Run $run): View
    {
        $run->load(['template.steps', 'stepLogs']);

        return $run->isCompleted()
            ? $this->summary($run)
            : $this->active($run);
    }

    /** Check off the current step: close its timer, open the next (or finish). */
    public function advance(Run $run): RedirectResponse
    {
        if ($run->isCompleted()) {
            return redirect()->route('flower.runs.show', $run);
        }

        DB::transaction(function () use ($run) {
            $openLog = $run->stepLogs()->whereNull('completed_at')->latest('started_at')->first();

            if (! $openLog) {
                return;
            }

            $now = Carbon::now();
            $openLog->update([
                'completed_at' => $now,
                'duration_seconds' => max(0, (int) round($openLog->started_at->diffInSeconds($now, true))),
            ]);

            $steps = $run->template->steps()->orderBy('position')->get();
            $currentIndex = $steps->search(fn (Step $s) => $s->id === $openLog->step_id);
            $nextStep = $currentIndex !== false ? $steps->get($currentIndex + 1) : null;

            if ($nextStep) {
                StepLog::create([
                    'run_id' => $run->id,
                    'step_id' => $nextStep->id,
                    'started_at' => $now,
                ]);
            } else {
                $run->update([
                    'status' => Run::STATUS_COMPLETED,
                    'completed_at' => $now,
                ]);
            }
        });

        return redirect()->route('flower.runs.show', $run);
    }

    /** Cancel an in-progress run (not saved to history). */
    public function destroy(Run $run): RedirectResponse
    {
        $template = $run->template;
        $run->delete();

        return redirect()
            ->route('flower.templates.history', $template)
            ->with('status', __('flower::messages.run.cancelled'));
    }

    public function history(Template $template): View
    {
        $template->load('steps');

        $runs = $template->runs()
            ->where('status', Run::STATUS_COMPLETED)
            ->with('stepLogs')
            ->orderByDesc('completed_at')
            ->get();

        // Per-step averages across all completed runs.
        $averages = [];
        foreach ($template->steps as $step) {
            $averages[$step->id] = $step->averageDuration();
        }

        return view('flower::runs.history', [
            'template' => $template,
            'runs' => $runs,
            'averages' => $averages,
        ]);
    }

    protected function active(Run $run): View
    {
        $steps = $run->template->steps; // ordered by position
        $logsByStep = $run->stepLogs->keyBy('step_id');

        $openLog = $run->stepLogs->firstWhere('completed_at', null);
        $currentStepId = $openLog?->step_id;

        // Build ordered view rows.
        $rows = $steps->map(function (Step $step) use ($logsByStep, $currentStepId) {
            $log = $logsByStep->get($step->id);
            $isCurrent = $step->id === $currentStepId;
            $isDone = $log && $log->completed_at !== null;

            return [
                'step' => $step,
                'is_current' => $isCurrent,
                'is_done' => $isDone,
                'duration' => $isDone ? $log->duration_seconds : null,
                'average' => $step->averageDuration(),
                'started_at' => $isCurrent && $log ? $log->started_at : null,
            ];
        });

        $doneCount = $rows->where('is_done', true)->count();
        $total = $steps->count();
        $isLastStep = $currentStepId !== null && $steps->last()?->id === $currentStepId;

        $currentRow = $rows->firstWhere('is_current', true);
        $currentAverage = $currentRow['average'] ?? null;
        $currentStartedAt = $currentRow['started_at'] ?? null;

        // Seconds already elapsed on the current step at page load (server-computed,
        // so client clock skew never affects the nudge threshold).
        $currentElapsedAtLoad = $currentStartedAt
            ? max(0, (int) round($currentStartedAt->diffInSeconds(Carbon::now(), true)))
            : 0;

        return view('flower::runs.active', [
            'run' => $run,
            'template' => $run->template,
            'rows' => $rows,
            'doneCount' => $doneCount,
            'total' => $total,
            'isLastStep' => $isLastStep,
            'currentAverage' => $currentAverage,
            'currentElapsedAtLoad' => $currentElapsedAtLoad,
        ]);
    }

    protected function summary(Run $run): View
    {
        $steps = $run->template->steps;
        $logsByStep = $run->stepLogs->keyBy('step_id');

        $rows = $steps->map(function (Step $step) use ($logsByStep) {
            $log = $logsByStep->get($step->id);
            $duration = $log?->duration_seconds;
            $average = $step->averageDurationExcludingRun($log?->run_id);

            return [
                'step' => $step,
                'duration' => $duration,
                'average' => $average,
                'delta' => ($duration !== null && $average !== null) ? $duration - $average : null,
            ];
        });

        $totalDuration = $run->stepLogs->sum('duration_seconds');

        return view('flower::runs.summary', [
            'run' => $run,
            'template' => $run->template,
            'rows' => $rows,
            'totalDuration' => $totalDuration,
        ]);
    }
}
