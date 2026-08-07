<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Flower\Models\Run;
use App\Modules\Flower\Models\Step;
use App\Modules\Flower\Models\Template;
use App\Modules\Flower\Models\Type;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FlowerFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('flower.index'))->assertRedirect(route('login'));
    }

    public function test_dashboard_lists_the_flower_module_card(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Flow-er');
    }

    public function test_template_can_be_created_with_findorcreate_client_and_type(): void
    {
        $this->actingAs($this->user)
            ->post(route('flower.templates.store'), [
                'client' => 'AcmeCorp',
                'type' => 'Podcast',
                'name' => 'Main edit',
            ])
            ->assertRedirect();

        $template = Template::firstOrFail();
        $this->assertSame('Main edit', $template->name);
        $this->assertSame('Podcast', $template->type->name);
        $this->assertSame('AcmeCorp', $template->type->client->name);
    }

    public function test_steps_sync_creates_updates_and_soft_deletes(): void
    {
        $template = $this->makeTemplate(['A', 'B']);
        $stepA = $template->steps()->orderBy('position')->first();

        $this->actingAs($this->user)->put(route('flower.templates.update', $template), [
            'client' => 'AcmeCorp',
            'type' => 'Podcast',
            'name' => 'Main edit',
            'steps' => [
                ['id' => $stepA->id, 'name' => 'A renamed'],
                ['id' => '', 'name' => 'C new'],
            ],
        ])->assertRedirect();

        $steps = $template->steps()->orderBy('position')->get();
        $this->assertCount(2, $steps);
        $this->assertSame(['A renamed', 'C new'], $steps->pluck('name')->all());
        $this->assertSame([0, 1], $steps->pluck('position')->all());
        // "B" was omitted -> soft deleted.
        $this->assertSoftDeleted('flower_steps', ['name' => 'B']);
    }

    public function test_full_run_lifecycle_records_durations_and_completes(): void
    {
        $template = $this->makeTemplate(['One', 'Two', 'Three']);

        // Start run.
        Carbon::setTestNow('2026-08-06 10:00:00');
        $this->actingAs($this->user)->post(route('flower.runs.start', $template))->assertRedirect();

        $run = Run::firstOrFail();
        $this->assertSame(Run::STATUS_IN_PROGRESS, $run->status);
        $this->assertCount(1, $run->stepLogs); // step 1 open

        // Advance step 1 (10s), step 2 (20s), step 3 (30s -> completes).
        Carbon::setTestNow('2026-08-06 10:00:10');
        $this->actingAs($this->user)->post(route('flower.runs.advance', $run))->assertRedirect();
        Carbon::setTestNow('2026-08-06 10:00:30');
        $this->actingAs($this->user)->post(route('flower.runs.advance', $run))->assertRedirect();
        Carbon::setTestNow('2026-08-06 10:01:00');
        $this->actingAs($this->user)->post(route('flower.runs.advance', $run))->assertRedirect();

        $run->refresh()->load('stepLogs');
        $this->assertTrue($run->isCompleted());
        $this->assertNotNull($run->completed_at);
        $this->assertSame([10, 20, 30], $run->stepLogs()->orderBy('id')->pluck('duration_seconds')->all());
    }

    public function test_averages_use_prior_completed_runs_only(): void
    {
        $template = $this->makeTemplate(['One']);
        $step = $template->steps()->first();

        // Run 1: 10s.
        $this->completeSingleStepRun($template, 10);
        // Run 2: 20s.
        $this->completeSingleStepRun($template, 20);

        // Average across both completed runs = 15.
        $this->assertEqualsWithDelta(15.0, $step->averageDuration(), 0.01);

        // For run 2's summary, average should exclude run 2 -> only run 1 (10).
        $run2 = Run::orderByDesc('id')->first();
        $this->assertEqualsWithDelta(10.0, $step->averageDurationExcludingRun($run2->id), 0.01);
    }

    public function test_first_run_shows_active_screen_without_nudge_data(): void
    {
        $template = $this->makeTemplate(['One']);
        $this->actingAs($this->user)->post(route('flower.runs.start', $template));
        $run = Run::firstOrFail();

        $this->actingAs($this->user)
            ->get(route('flower.runs.show', $run))
            ->assertOk()
            ->assertSee('One');
    }

    public function test_completed_run_show_renders_summary(): void
    {
        $template = $this->makeTemplate(['One']);
        $this->completeSingleStepRun($template, 12);
        $run = Run::firstOrFail();

        $this->actingAs($this->user)
            ->get(route('flower.runs.show', $run))
            ->assertOk()
            ->assertSee(__('flower::messages.summary.heading'));
    }

    public function test_all_authed_flower_pages_render(): void
    {
        $template = $this->makeTemplate(['One', 'Two']);
        $this->completeSingleStepRun($template, 15); // gives history + averages

        $this->actingAs($this->user);

        $this->get(route('flower.index'))->assertOk()->assertSee('AcmeCorp');
        $this->get(route('flower.templates.create'))->assertOk();
        $this->get(route('flower.templates.edit', $template))->assertOk()->assertSee('One');
        $this->get(route('flower.templates.history', $template))->assertOk();
    }

    // ---- helpers ----

    protected function makeTemplate(array $stepNames): Template
    {
        $client = \App\Modules\Flower\Models\Client::create(['name' => 'AcmeCorp']);
        $type = Type::create(['client_id' => $client->id, 'name' => 'Podcast']);
        $template = Template::create(['type_id' => $type->id, 'name' => 'Main edit']);

        foreach ($stepNames as $i => $name) {
            Step::create(['template_id' => $template->id, 'name' => $name, 'position' => $i]);
        }

        return $template->fresh('steps');
    }

    protected function completeSingleStepRun(Template $template, int $seconds): void
    {
        Carbon::setTestNow('2026-08-06 12:00:00');
        $this->actingAs($this->user)->post(route('flower.runs.start', $template));
        $run = Run::orderByDesc('id')->first();
        Carbon::setTestNow(Carbon::parse('2026-08-06 12:00:00')->addSeconds($seconds));
        $this->actingAs($this->user)->post(route('flower.runs.advance', $run));
        Carbon::setTestNow();
    }
}
