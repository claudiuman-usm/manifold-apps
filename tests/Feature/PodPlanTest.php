<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\PodPlan\Models\GearItem;
use App\Modules\PodPlan\Models\GearType;
use App\Modules\PodPlan\Models\Project;
use App\Modules\PodPlan\Support\PlanDescriber;
use App\Modules\PodPlan\Support\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PodPlan, exercised against the inventory the module ships with (the seed
 * migration), so these tests double as a check of the owner's own rules.
 */
class PodPlanTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    // -------------------------------------------------------------- helpers

    protected function project(array $attributes = []): Project
    {
        $project = Project::create($attributes + [
            'name' => 'Episode 12', 'shoot_type' => 'podcast', 'people_count' => 2, 'live_mixing' => false,
        ]);
        app(PlanService::class)->seedSet($project);

        return $project;
    }

    protected function item(string $name, ?string $provider = null): GearItem
    {
        return GearItem::where('name', $name)
            ->when($provider, fn ($q) => $q->where('provider', $provider), fn ($q) => $q->whereNull('provider'))
            ->firstOrFail();
    }

    protected function add(Project $project, GearItem $item): array
    {
        return $this->postJson(route('podplan.plan.add', $project), ['gear_item_id' => $item->id])
            ->assertOk()->json();
    }

    protected function state(Project $project): array
    {
        return $this->getJson(route('podplan.plan.state', $project))->assertOk()->json();
    }

    /** Every generated row, flattened, with the section it belongs to. */
    protected function rows(array $state): array
    {
        $rows = [];
        foreach ($state['checklist']['sections'] as $section) {
            foreach ($section['rows'] as $row) {
                $rows[] = $row + ['section' => $section['title']];
            }
        }
        foreach ($state['checklist']['general'] as $row) {
            $rows[] = $row + ['section' => 'general'];
        }

        return $rows;
    }

    /** What goes with the gear: every row's label, leaving out the row for the gear itself. */
    protected function labels(array $state, ?string $section = null): array
    {
        return array_values(array_map(
            fn ($r) => $r['label'],
            array_filter($this->rows($state), fn ($r) => ! $r['self'] && ($section === null || $r['section'] === $section)),
        ));
    }

    protected function placementId(array $state, GearItem $item): int
    {
        return collect($state['placements'])->firstWhere('item_id', $item->id)['id'];
    }

    protected function answer(Project $project, int $placementId, ?GearItem $lens): array
    {
        return $this->postJson(route('podplan.plan.answer', $project), [
            'placement_id' => $placementId, 'question' => 'Lens', 'gear_item_id' => $lens?->id,
        ])->assertOk()->json();
    }

    // ---------------------------------------------------------------- pages

    public function test_guests_are_sent_to_login(): void
    {
        auth()->logout();

        $this->get(route('podplan.index'))->assertRedirect(route('login'));
        $this->get(route('podplan.inventory.index'))->assertRedirect(route('login'));
    }

    public function test_every_screen_renders(): void
    {
        $project = $this->project(['shoot_date' => '2026-12-01', 'location' => 'Studio A']);
        $this->add($project, $this->item('Blackmagic Studio Camera 4K Pro'));

        $this->get(route('podplan.index'))->assertOk()->assertSee('Episode 12');
        $this->get(route('podplan.projects.create'))->assertOk();
        $this->get(route('podplan.projects.edit', $project))->assertOk()->assertSee('Studio A');
        $this->get(route('podplan.plan', $project))->assertOk()->assertSee('pp-svg', false);
        $this->get(route('podplan.checklist', $project))->assertOk();
        $this->get(route('podplan.inventory.index'))->assertOk()->assertSee('Shure SM7B #1')->assertSee('Glam Art Media')
            ->assertSee('pp-inv-search', false)
            ->assertSee('v'.app(\App\Modules\ModuleRegistry::class)->get('podplan')->manifest['version']);
        $this->get(route('podplan.inventory.index', ['item' => $this->item('Nanlite PavoTube', 'Marius Achiței')->id]))->assertOk();
        $this->get(route('podplan.inventory.index', ['new' => 'lens']))->assertOk();
    }

    public function test_the_owners_inventory_is_seeded(): void
    {
        $plan = GearItem::whereHas('type', fn ($q) => $q->where('kind', 'plan'))->count();
        $accessories = GearItem::whereHas('type', fn ($q) => $q->where('kind', 'accessory'))->count();

        $this->assertSame(21, $plan);
        $this->assertSame(19, $accessories, 'including one wireless video transmitter and one receiver');
        $this->assertSame(4, $this->item('Tripod')->quantity);
        $this->assertSame(2, GearItem::where('name', 'DJI Mic 3')->count());
        $this->assertSame(2, $this->item('DJI Mic 2')->quantity, 'a wireless kit has two lavs');
        $this->assertSame(18, GearType::count());
    }

    // ------------------------------------------------------------- projects

    public function test_a_new_project_starts_with_the_table_and_its_people(): void
    {
        $this->post(route('podplan.projects.store'), [
            'name' => 'Interview', 'shoot_type' => 'interview', 'people_count' => 3,
            'live_mixing' => 0, 'start' => 'empty',
        ])->assertRedirect();

        $project = Project::firstOrFail();
        $this->assertSame(1, $project->placements()->where('kind', 'furniture')->count());
        $this->assertSame(3, $project->placements()->where('kind', 'person')->count());

        $this->put(route('podplan.projects.update', $project), [
            'name' => 'Interview', 'shoot_type' => 'interview', 'people_count' => 1, 'live_mixing' => 1,
        ])->assertRedirect();

        $this->assertSame(1, $project->placements()->where('kind', 'person')->count());
        $this->assertTrue($project->fresh()->live_mixing);
    }

    // ---------------------------------------------------------------- rules

    public function test_a_camera_brings_its_kit_and_live_mixing_adds_the_hdmi_cable(): void
    {
        $project = $this->project();
        $camera = $this->item('Blackmagic Studio Camera 4K Pro');
        $state = $this->add($project, $camera);

        $this->assertSame(['Tripod', 'AC adapter', 'Power cord', 'Samsung T7'], $this->labels($state, $camera->name));
        $this->assertSame(1, $state['checklist']['open'], 'the lens is still an open choice');
        $this->assertSame([], $state['checklist']['warnings']);

        $state = $this->patchJson(route('podplan.plan.live', $project), ['live' => true])->assertOk()->json();

        $this->assertContains('HDMI cable to mixer', $this->labels($state, $camera->name));
        $hdmi = collect($this->rows($state))->firstWhere('label', 'HDMI cable to mixer');
        $this->assertTrue($hdmi['live']);
        $this->assertSame('need_mixer', $state['checklist']['warnings'][0]['type']);

        $mixer = $this->item('Blackmagic ATEM Mini Extreme ISO');
        $this->assertSame($mixer->id, $state['checklist']['warnings'][0]['item_id']);

        $state = $this->add($project, $mixer);
        $this->assertSame([], $state['checklist']['warnings']);
        $this->assertSame(
            ['Samsung T7', 'AC adapter', 'Short HDMI cable', 'Portable monitor'],
            $this->labels($state, $mixer->name),
        );
    }

    public function test_a_cameras_link_to_the_mixer_is_a_cable_unless_wireless_is_chosen(): void
    {
        $project = $this->project();
        $camera = $this->item('Blackmagic Studio Camera 4K Pro');
        $second = $this->item('Blackmagic Pocket Cinema Camera 4K #1');
        $state = $this->add($project, $camera);
        $id = $this->placementId($state, $camera);
        $connection = fn (array $state, int $placement) => collect($state['checklist']['sections'])
            ->firstWhere('placement_id', $placement)['prompts'][1] ?? null;
        $choose = fn (int $placement, string $value) => $this->postJson(route('podplan.plan.answer', $project), [
            'placement_id' => $placement, 'question' => 'Connection', 'value' => $value,
        ])->assertOk()->json();

        // No live mixing, no link to a mixer, so nothing to ask.
        $this->assertNull($connection($state, $id));

        $this->add($project, $this->item('Blackmagic ATEM Mini Extreme ISO'));
        $state = $this->patchJson(route('podplan.plan.live', $project), ['live' => true])->json();

        // It comes after the lens, already answered with the default.
        $prompt = $connection($state, $id);
        $this->assertSame('Connection', $prompt['question']);
        $this->assertSame(['Cable', 'Wireless'], array_column($prompt['options'], 'value'));
        $this->assertSame('Cable', $prompt['chosen']['value']);
        $this->assertTrue($prompt['required']);
        $this->assertSame(1, $state['checklist']['open'], 'only the lens is open');
        $this->assertContains('HDMI cable to mixer', $this->labels($state, $camera->name));
        $this->assertSame(['hdmi'], array_column($state['cables'], 'kind'));

        // Wireless swaps the cable for a transmitter and a receiver, and draws no line.
        $state = $choose($id, 'Wireless');
        $labels = $this->labels($state, $camera->name);
        $this->assertNotContains('HDMI cable to mixer', $labels);
        $this->assertContains('Wireless video transmitter', $labels);
        $this->assertContains('Wireless video receiver', $labels);
        $this->assertTrue(collect($this->rows($state))->firstWhere('label', 'Wireless video receiver')['live']);
        $this->assertSame([], $state['cables']);
        $this->assertTrue(collect($state['placements'])->firstWhere('id', $id)['wireless']);
        // Both ends of the link are marked: the camera and the mixer it sends to.
        $marked = collect($state['placements'])->where('wireless_rx', true)->pluck('title')->all();
        $this->assertSame(['Blackmagic ATEM Mini Extreme ISO'], $marked);
        $this->assertSame('Meike 35mm', collect($this->answer($project, $id, $this->item('Meike 35mm'))['placements'])->firstWhere('id', $id)['sub'], 'the label still shows the lens');

        // There is one set, so a second wireless camera is flagged as short.
        $state = $this->add($project, $second);
        $state = $choose($this->placementId($state, $second), 'Wireless');
        $short = collect($state['checklist']['warnings'])->where('type', 'short')->pluck('label')->all();
        $this->assertEqualsCanonicalizing(['Wireless video transmitter', 'Wireless video receiver'], $short);

        // Back to cable (both cameras), and an answer that is not on offer is refused.
        $choose($this->placementId($state, $second), 'Cable');
        $state = $choose($id, 'Cable');
        $this->assertSame([], collect($state['placements'])->where('wireless_rx', true)->all());
        $this->assertContains('HDMI cable to mixer', $this->labels($state, $camera->name));
        $this->postJson(route('podplan.plan.answer', $project), [
            'placement_id' => $id, 'question' => 'Connection', 'value' => 'Carrier pigeon',
        ])->assertStatus(422);

        // Unlike the lens, the connection is not remembered: the next project starts on cable.
        $choose($id, 'Wireless');
        $next = $this->project(['name' => 'Next shoot', 'live_mixing' => true]);
        $state = $this->add($next, $camera);
        $this->assertSame('Cable', $connection($state, $this->placementId($state, $camera))['chosen']['value']);
    }

    public function test_every_camera_and_every_light_brings_a_power_cord(): void
    {
        $project = $this->project();
        $camera = $this->item('Blackmagic Pocket Cinema Camera 4K #1');
        $light = $this->item('Nanlite Forza 500');
        $tube = $this->item('Nanlite PavoTube', 'Marius Achiței');
        foreach ([$camera, $light, $tube] as $item) {
            $state = $this->add($project, $item);
        }

        $this->assertContains('Power cord', $this->labels($state, $camera->name));
        $this->assertSame(['Light stand', 'Power cord'], $this->labels($state, $light->name));
        $this->assertSame(['Power cord'], $this->labels($state, $tube->name), 'the PavoTube keeps its own rule: a cord, no stand');
        $this->assertNotContains('Power cable', $this->labels($state));
    }

    public function test_timecode_is_on_the_list_only_without_live_mixing(): void
    {
        $project = $this->project();
        $state = $this->add($project, $this->item('Blackmagic Studio Camera 4K Pro'));
        $this->assertContains('Tentacle Sync', $this->labels($state, 'general'));
        $this->assertContains('Gaffer tape', $this->labels($state, 'general'));

        $state = $this->patchJson(route('podplan.plan.live', $project), ['live' => true])->json();
        $this->assertNotContains('Tentacle Sync', $this->labels($state, 'general'));
    }

    public function test_pools_use_the_owners_units_first_and_rented_ones_when_short(): void
    {
        $project = $this->project();
        $marius = 'Marius Achiței';
        $lights = [
            $this->item('Nanlite Forza 500'),
            $this->item('Nanlite Forza 720', $marius),
            $this->item('Nanlite PavoSlim'),
            $this->item('Nanlite PavoSlim', $marius),
            $this->item('Nanlite PavoTube', $marius),
        ];
        foreach ($lights as $light) {
            $state = $this->add($project, $light);
        }

        $stands = array_values(array_filter($this->rows($state), fn ($r) => $r['label'] === 'Light stand'));
        $this->assertCount(4, $stands, 'the PavoTube takes no stand');
        $this->assertSame([null, null, $marius, $marius], array_column($stands, 'rented'));
        $this->assertSame([], $state['checklist']['warnings']);

        // With a single stand of each, the third light finds the pool empty.
        GearItem::where('name', 'Light stand')->update(['quantity' => 1]);
        $state = $this->state($project);
        $short = array_filter($this->rows($state), fn ($r) => $r['short']);
        $this->assertCount(2, $short);
        $this->assertSame(['type' => 'short', 'label' => 'Light stand', 'count' => 2], $state['checklist']['warnings'][0]);
    }

    public function test_an_sm7b_brings_a_fethead_as_the_owners_own_rule(): void
    {
        $project = $this->project();
        $state = $this->add($project, $this->item('Shure SM7B #1'));
        $state = $this->add($project, $this->item('RØDE NTG4+ #1'));

        $this->assertSame(['Mic arm or stand', 'XLR cable', 'Triton Audio FetHead'], $this->labels($state, 'Shure SM7B #1'));
        $this->assertSame(['Mic arm or stand', 'XLR cable'], $this->labels($state, 'RØDE NTG4+ #1'));

        $fethead = collect($this->rows($state))->firstWhere('label', 'Triton Audio FetHead');
        $this->assertTrue($fethead['rule']);
    }

    public function test_choosing_a_lens_adds_it_with_its_adapter_and_labels_the_camera(): void
    {
        $project = $this->project();
        $cameras = [
            $this->item('Blackmagic Pocket Cinema Camera 4K #1'),
            $this->item('Blackmagic Pocket Cinema Camera 4K #2'),
            $this->item('Blackmagic Pocket Cinema Camera 4K #3'),
        ];
        foreach ($cameras as $camera) {
            $state = $this->add($project, $camera);
        }
        $this->assertSame(3, $state['checklist']['open']);

        $state = $this->answer($project, $this->placementId($state, $cameras[0]), $this->item('Canon 35mm'));
        $this->assertSame(2, $state['checklist']['open']);
        $this->assertContains('Canon 35mm', $this->labels($state, $cameras[0]->name));
        $this->assertContains('Metabones Speed Booster XL 0.64x', $this->labels($state, $cameras[0]->name));
        $this->assertSame('Canon 35mm', collect($state['placements'])->firstWhere('item_id', $cameras[0]->id)['sub']);

        // A Meike lens needs no adapter.
        $state = $this->answer($project, $this->placementId($state, $cameras[1]), $this->item('Meike 35mm'));
        $this->assertNotContains('Metabones Speed Booster Ultra 0.71x', $this->labels($state, $cameras[1]->name));

        // Three Canon lenses, two adapters: the third one is flagged, and the rented lens is tagged.
        $this->answer($project, $this->placementId($state, $cameras[1]), $this->item('Canon 50mm'));
        $state = $this->answer($project, $this->placementId($state, $cameras[2]), $this->item('Canon 70-200mm', 'Glam Art Media'));

        $third = array_values(array_filter($this->rows($state), fn ($r) => $r['section'] === $cameras[2]->name));
        $this->assertSame('Glam Art Media', collect($third)->firstWhere('label', 'Canon 70-200mm')['rented']);
        $this->assertTrue(collect($third)->firstWhere('label', 'Lens adapter')['short']);
        $this->assertSame(0, $state['checklist']['open']);

        // Tapping the chosen lens again clears it.
        $state = $this->answer($project, $this->placementId($state, $cameras[0]), null);
        $this->assertSame(1, $state['checklist']['open']);
    }

    public function test_the_last_lens_is_remembered_for_the_next_project(): void
    {
        $camera = $this->item('Blackmagic Studio Camera 4K Pro');
        $first = $this->project();
        $state = $this->add($first, $camera);
        $this->answer($first, $this->placementId($state, $camera), $this->item('Meike 85mm'));

        $second = $this->project(['name' => 'Episode 13']);
        $state = $this->add($second, $camera);

        $this->assertSame(0, $state['checklist']['open']);
        $this->assertContains('Meike 85mm', $this->labels($state, $camera->name));
    }

    public function test_renaming_gear_shows_in_existing_projects(): void
    {
        $project = $this->project();
        $camera = $this->item('Blackmagic Studio Camera 4K Pro');
        $this->add($project, $camera);

        $this->put(route('podplan.inventory.update', $camera), [
            'name' => 'Studio Cam A', 'short_name' => 'Cam A', 'gear_type_id' => $camera->gear_type_id,
        ])->assertRedirect();

        $state = $this->state($project);
        $this->assertSame('Studio Cam A', $state['checklist']['sections'][0]['title']);
        $this->assertSame('Cam A', collect($state['placements'])->firstWhere('item_id', $camera->id)['label']);
    }

    // ----------------------------------------------------------------- plan

    public function test_cables_follow_the_gear_types_connection(): void
    {
        $project = $this->project(['live_mixing' => true]);
        $camera = $this->item('Blackmagic Studio Camera 4K Pro');
        $mic = $this->item('Shure SM7B #1');

        $this->add($project, $camera);
        $this->add($project, $mic);
        $state = $this->add($project, $this->item('DJI Mic 2'));
        $this->assertSame([], $state['cables'], 'nothing to run a cable to yet');

        $this->add($project, $this->item('Blackmagic ATEM Mini Extreme ISO'));
        $state = $this->add($project, $this->item('Sound Devices MixPre-3'));

        $kinds = collect($state['cables'])->pluck('kind', 'from');
        $this->assertSame('hdmi', $kinds[$this->placementId($state, $camera)]);
        $this->assertSame('xlr', $kinds[$this->placementId($state, $mic)]);
        $this->assertCount(2, $state['cables'], 'the wireless mic draws nothing');

        $state = $this->patchJson(route('podplan.plan.live', $project), ['live' => false])->json();
        $this->assertSame(['xlr'], array_column($state['cables'], 'kind'));
    }

    public function test_mixers_land_in_the_mixer_corner(): void
    {
        [$zx, $zy, $zw, $zh] = PlanService::MIXER_ZONE;
        $project = $this->project();
        $mixers = [
            $this->item('Blackmagic ATEM Mini Extreme ISO'),
            $this->item('Blackmagic ATEM Mini Pro ISO'),
            $this->item('Sound Devices MixPre-3'),
        ];
        foreach ($mixers as $mixer) {
            $state = $this->add($project, $mixer);
        }
        $state = $this->add($project, $this->item('Blackmagic Studio Camera 4K Pro'));

        $this->assertSame(PlanService::MIXER_ZONE, $state['canvas']['zone']);
        foreach ($mixers as $mixer) {
            $placed = collect($state['placements'])->firstWhere('item_id', $mixer->id);
            $this->assertTrue(
                $placed['x'] >= $zx && $placed['x'] <= $zx + $zw && $placed['y'] >= $zy && $placed['y'] <= $zy + $zh,
                $mixer->name.' should start inside the mixer corner',
            );
        }

        $camera = collect($state['placements'])->firstWhere('symbol', 'cam');
        $this->assertFalse($camera['x'] >= $zx && $camera['y'] >= $zy, 'cameras stay out of it');
        $this->assertCount(3, collect($state['placements'])->where('kind', 'gear')->whereIn('role', ['video_mixer', 'audio_mixer'])->unique(fn ($p) => $p['x'].':'.$p['y']));
    }

    public function test_the_module_navigation_opens_the_current_projects_checklist(): void
    {
        // With no project there is nothing for Plan and Checklist to open.
        $this->get(route('podplan.inventory.index'))->assertOk()
            ->assertSee(route('podplan.inventory.index'), false)
            ->assertDontSee('/checklist"', false);

        $first = $this->project(['name' => 'First shoot']);
        $second = $this->project(['name' => 'Second shoot']);

        // Opening a project makes it the one the navigation points at...
        $this->get(route('podplan.plan', $first))->assertOk()->assertSee(route('podplan.checklist', $first), false);

        // ...from every other screen of the module.
        $this->get(route('podplan.inventory.index'))->assertOk()->assertSee(route('podplan.checklist', $first), false);
        $this->get(route('podplan.index'))->assertOk()->assertSee(route('podplan.plan', $first), false);

        $this->get(route('podplan.checklist', $second))->assertOk();
        $this->get(route('podplan.inventory.index'))->assertOk()
            ->assertSee(route('podplan.checklist', $second), false)
            ->assertSee('Second shoot');
    }

    public function test_a_wireless_kit_puts_both_its_lavs_on_the_plan_but_packs_as_one(): void
    {
        $project = $this->project();
        $kit = $this->item('DJI Mic 2');

        $state = $this->add($project, $kit);
        $state = $this->add($project, $kit);
        $lavs = collect($state['placements'])->where('item_id', $kit->id)->values();

        $this->assertCount(2, $lavs);
        $this->assertSame(['DJI Mic 2 · A', 'DJI Mic 2 · B'], $lavs->pluck('label')->all());
        $this->assertNotSame([$lavs[0]['x'], $lavs[0]['y']], [$lavs[1]['x'], $lavs[1]['y']], 'one lav per person');

        // Both are out: a third tap adds nothing.
        $state = $this->add($project, $kit);
        $this->assertCount(2, collect($state['placements'])->where('item_id', $kit->id));

        $entry = collect($state['gear'])->flatMap(fn ($g) => $g['items'])->firstWhere('id', $kit->id);
        $this->assertSame(2, $entry['units']);
        $this->assertCount(2, $entry['placement_ids']);

        // One kit to pack, however many of its lavs are on the plan.
        $this->assertCount(1, array_filter($state['checklist']['sections'], fn ($s) => $s['item_id'] === $kit->id));

        // An ordinary item still goes on once.
        $camera = $this->item('Blackmagic Studio Camera 4K Pro');
        $this->add($project, $camera);
        $state = $this->add($project, $camera);
        $this->assertCount(1, collect($state['placements'])->where('item_id', $camera->id));
    }

    public function test_the_field_of_view_follows_the_lens(): void
    {
        $project = $this->project();
        $camera = $this->item('Blackmagic Pocket Cinema Camera 4K #1');
        $state = $this->add($project, $camera);
        $id = $this->placementId($state, $camera);
        $fov = fn (array $state) => collect($state['placements'])->firstWhere('id', $id)['fov'];

        $default = $fov($state);

        $wide = $fov($this->answer($project, $id, $this->item('Meike 35mm')));
        $tele = $fov($this->answer($project, $id, $this->item('Meike 85mm')));
        $this->assertGreaterThan($tele, $wide, 'a longer lens sees a narrower angle');
        $this->assertEqualsWithDelta(30.3, $wide, 0.2);
        $this->assertEqualsWithDelta(12.7, $tele, 0.2);

        // The speed booster a Canon lens brings with it widens the view again.
        $boosted = $fov($this->answer($project, $id, $this->item('Canon 35mm')));
        $this->assertGreaterThan($wide, $boosted);

        // A zoom is drawn at its wide end; no lens falls back to the default cone.
        $zoom = $fov($this->answer($project, $id, $this->item('Canon 70-200mm', 'Glam Art Media')));
        $this->assertLessThan($boosted, $zoom);
        $this->assertSame($default, $fov($this->answer($project, $id, null)));

        // Set by hand, the angle stays put whatever the lens; Auto hands it back.
        $this->answer($project, $id, $this->item('Meike 35mm'));
        $state = $this->patchJson(route('podplan.plan.move', [$project, $id]), ['fov' => 75])->assertOk()->json();
        $this->assertSame(75, (int) $fov($state));
        $this->assertTrue(collect($state['placements'])->firstWhere('id', $id)['fov_manual']);
        $this->assertSame(75, (int) $fov($this->answer($project, $id, $this->item('Meike 85mm'))));

        $state = $this->patchJson(route('podplan.plan.move', [$project, $id]), ['fov_auto' => true])->assertOk()->json();
        $this->assertEqualsWithDelta(12.7, $fov($state), 0.2);
        $this->assertFalse(collect($state['placements'])->firstWhere('id', $id)['fov_manual']);
    }

    public function test_lights_are_aimed_at_the_set_and_their_beam_can_be_set(): void
    {
        $project = $this->project();
        $light = $this->item('Nanlite Forza 500');
        $mic = $this->item('Shure SM7B #1');
        $state = $this->add($project, $light);
        $state = $this->add($project, $mic);
        $lamp = collect($state['placements'])->firstWhere('item_id', $light->id);

        // The first light sits top left, so it looks down and to the right at the table.
        $this->assertGreaterThan(90, $lamp['rot']);
        $this->assertLessThan(180, $lamp['rot']);
        $this->assertSame(60, (int) $lamp['fov']);

        $state = $this->patchJson(route('podplan.plan.move', [$project, $lamp['id']]), ['rotation' => 45, 'fov' => 30])->json();
        $lamp = collect($state['placements'])->firstWhere('id', $lamp['id']);
        $this->assertSame([45, 30], [(int) $lamp['rot'], (int) $lamp['fov']]);

        // A microphone has no direction and no beam.
        $micId = $this->placementId($state, $mic);
        $state = $this->patchJson(route('podplan.plan.move', [$project, $micId]), ['rotation' => 45, 'fov' => 30])->json();
        $placed = collect($state['placements'])->firstWhere('id', $micId);
        $this->assertSame(0, (int) $placed['rot']);
        $this->assertNull($placed['fov']);
    }

    public function test_the_gear_itself_is_packed_too_and_rented_gear_says_so(): void
    {
        $project = $this->project();
        $own = $this->item('Nanlite Forza 500');
        $rented = $this->item('Nanlite Forza 720', 'Marius Achiței');
        $this->add($project, $own);
        $state = $this->add($project, $rented);

        $self = collect($this->rows($state))->where('self', true)->keyBy('label');
        $this->assertSame(['Nanlite Forza 500', 'Nanlite Forza 720'], $self->keys()->all());
        $this->assertNull($self['Nanlite Forza 500']['rented']);
        $this->assertSame('Marius Achiței', $self['Nanlite Forza 720']['rented']);
        $this->assertSame('Light', $self['Nanlite Forza 720']['kind']);

        // It is a row like any other: it counts and it ticks.
        $payload = $this->postJson(route('podplan.checklist.tick', $project), ['key' => $self['Nanlite Forza 720']['key'], 'packed' => true])->assertOk()->json();
        $this->assertSame(1, $payload['checklist']['packed']);

        // What the Rented filter shows: the rented light itself and nothing of the owner's.
        $rentedRows = collect($this->rows($state))->whereNotNull('rented')->pluck('label')->all();
        $this->assertSame(['Nanlite Forza 720'], $rentedRows);
    }

    public function test_rows_say_what_kind_they_are_so_the_checklist_can_group_them(): void
    {
        $project = $this->project();
        $camera = $this->item('Blackmagic Studio Camera 4K Pro');
        $state = $this->add($project, $camera);
        $state = $this->answer($project, $this->placementId($state, $camera), $this->item('Meike 35mm'));
        $rows = collect($this->rows($state))->keyBy('label');

        $this->assertSame('Tripod', $rows['Tripod']['kind']);
        $this->assertSame('SSD / drive', $rows['Samsung T7']['kind']);
        $this->assertSame('Lens', $rows['Meike 35mm']['kind']);
        $this->assertSame('Timecode', $rows['Tentacle Sync']['kind']);
        $this->assertNull($rows['AC adapter']['kind'], 'loose items have no kind');
        $this->assertSame('Camera', $rows[$camera->name]['kind'], 'the camera itself groups under Camera');
        $this->assertLessThan($rows['Meike 35mm']['kind_sort'], $rows['Tripod']['kind_sort']);
    }

    public function test_gear_can_be_moved_and_stays_on_the_canvas(): void
    {
        $project = $this->project();
        $camera = $this->item('Blackmagic Studio Camera 4K Pro');
        $state = $this->add($project, $camera);

        $cam = $this->placementId($state, $camera);
        $state = $this->patchJson(route('podplan.plan.move', [$project, $cam]), ['x' => 100, 'y' => 9999, 'rotation' => 30])->json();
        $placed = collect($state['placements'])->firstWhere('id', $cam);
        $this->assertSame(100, (int) $placed['x']);
        $this->assertLessThanOrEqual(PlanService::H, $placed['y'], 'kept inside the canvas');
        $this->assertSame(30, (int) $placed['rot']);
    }

    public function test_cameras_are_numbered_and_numbers_can_be_swapped_or_removed(): void
    {
        $project = $this->project();
        $this->add($project, $this->item('Blackmagic Pocket Cinema Camera 4K #1'));
        $this->add($project, $this->item('Blackmagic Pocket Cinema Camera 4K #2'));
        $state = $this->add($project, $this->item('Nanlite Forza 500'));

        $cameras = collect($state['placements'])->where('role', 'camera')->values();
        $this->assertSame([1, 2], $cameras->pluck('number')->all());
        $this->assertNull(collect($state['placements'])->firstWhere('role', 'light')['number']);
        [$first, $second] = [$cameras[0]['id'], $cameras[1]['id']];
        $number = fn (array $state, int $id) => collect($state['placements'])->firstWhere('id', $id)['number'];

        // Taking a number that is in use swaps the two cameras.
        $state = $this->patchJson(route('podplan.plan.move', [$project, $first]), ['number' => 2])->assertOk()->json();
        $this->assertSame(2, $number($state, $first));
        $this->assertSame(1, $number($state, $second));

        // A free number is simply taken, and a number can be taken off.
        $state = $this->patchJson(route('podplan.plan.move', [$project, $first]), ['number' => 5])->assertOk()->json();
        $this->assertSame(5, $number($state, $first));
        $state = $this->patchJson(route('podplan.plan.move', [$project, $second]), ['number' => null])->assertOk()->json();
        $this->assertNull($number($state, $second));

        // Moving a camera leaves its number alone; the next camera gets the lowest free one.
        $state = $this->patchJson(route('podplan.plan.move', [$project, $first]), ['x' => 40, 'y' => 40])->assertOk()->json();
        $this->assertSame(5, $number($state, $first));
        $state = $this->add($project, $this->item('Blackmagic Pocket Cinema Camera 4K #3'));
        $this->assertSame([5, null, 1], collect($state['placements'])->where('role', 'camera')->pluck('number')->all());

        // Numbers are for the plan only: nothing on the checklist mentions them.
        $this->assertStringNotContainsString('"number"', json_encode($state['checklist']));

        // Only cameras take a number.
        $light = collect($state['placements'])->firstWhere('role', 'light')['id'];
        $state = $this->patchJson(route('podplan.plan.move', [$project, $light]), ['number' => 3])->assertOk()->json();
        $this->assertNull($number($state, $light));
    }

    public function test_the_axis_of_action_can_be_shown_moved_and_flags_a_camera_across_it(): void
    {
        $project = $this->project();
        $this->add($project, $this->item('Blackmagic Pocket Cinema Camera 4K #1'));
        $this->add($project, $this->item('Blackmagic Pocket Cinema Camera 4K #2'));
        $state = $this->add($project, $this->item('Blackmagic Pocket Cinema Camera 4K #3'));
        $this->assertNull($state['axis']);
        $cameras = collect($state['placements'])->where('role', 'camera')->pluck('id')->all();

        // Turned on for the first time, it runs through the two people and beyond them.
        $state = $this->patchJson(route('podplan.plan.axis', $project), ['on' => true])->assertOk()->json();
        $people = collect($state['placements'])->where('kind', 'person')->values();
        $axis = $state['axis'];
        $this->assertTrue($axis['on']);
        foreach ($people as $person) {
            $cross = ($axis['x2'] - $axis['x1']) * ($person['y'] + 16 - $axis['y1']) - ($axis['y2'] - $axis['y1']) * ($person['x'] + 16 - $axis['x1']);
            $this->assertEqualsWithDelta(0, $cross / hypot($axis['x2'] - $axis['x1'], $axis['y2'] - $axis['y1']), 0.5);
        }
        $this->assertGreaterThan(
            hypot($people[1]['x'] - $people[0]['x'], $people[1]['y'] - $people[0]['y']),
            hypot($axis['x2'] - $axis['x1'], $axis['y2'] - $axis['y1']),
        );

        // Placed level across the set: the three cameras all sit below it, so nothing is flagged.
        $line = ['on' => true, 'x1' => 100, 'y1' => 235, 'x2' => 560, 'y2' => 235];
        $state = $this->patchJson(route('podplan.plan.axis', $project), $line)->assertOk()->json();
        $this->assertSame(235, (int) $state['axis']['y1']);
        $this->assertSame([], $state['plan_warnings']);

        // One camera goes round to the far side: it is flagged, the other two are not.
        $state = $this->patchJson(route('podplan.plan.move', [$project, $cameras[2]]), ['x' => 300, 'y' => 60])->assertOk()->json();
        $across = collect($state['placements'])->where('across', true)->pluck('id')->all();
        $this->assertSame([$cameras[2]], $across);
        $this->assertSame([['type' => 'axis', 'number' => 3]], array_map(fn ($w) => ['type' => $w['type'], 'number' => $w['number']], $state['plan_warnings']));

        // One camera each side: the lowest number sets the working side.
        $this->deleteJson(route('podplan.plan.remove', [$project, $cameras[1]]))->assertOk();
        $state = $this->state($project);
        $this->assertSame([$cameras[2]], collect($state['placements'])->where('across', true)->pluck('id')->all());

        // Hidden, the line keeps its place but flags nothing; shown again it is where it was left.
        $state = $this->patchJson(route('podplan.plan.axis', $project), ['on' => false])->assertOk()->json();
        $this->assertFalse($state['axis']['on']);
        $this->assertSame([], $state['plan_warnings']);
        $state = $this->patchJson(route('podplan.plan.axis', $project), ['on' => true])->assertOk()->json();
        $this->assertSame(235, (int) $state['axis']['y2']);
        $this->assertCount(1, $state['plan_warnings']);

        // It can be put back through the people, and never leaves the canvas.
        $state = $this->patchJson(route('podplan.plan.axis', $project), ['on' => true, 'reset' => true])->assertOk()->json();
        $this->assertEquals($axis, $state['axis']);
        $state = $this->patchJson(route('podplan.plan.axis', $project), ['on' => true, 'x1' => -50, 'y1' => 10, 'x2' => 9000, 'y2' => 900])->assertOk()->json();
        $this->assertSame([0, 10, 660, 560], array_map('intval', [$state['axis']['x1'], $state['axis']['y1'], $state['axis']['x2'], $state['axis']['y2']]));
    }

    public function test_the_same_gear_on_two_shoots_that_day_is_flagged(): void
    {
        $camera = $this->item('Blackmagic Studio Camera 4K Pro');
        $first = $this->project(['name' => 'Morning shoot', 'shoot_date' => '2026-12-05']);
        $second = $this->project(['name' => 'Evening shoot', 'shoot_date' => '2026-12-05']);
        $other = $this->project(['name' => 'Next week', 'shoot_date' => '2026-12-12']);
        $this->add($first, $camera);
        $this->add($other, $camera);

        $state = $this->add($second, $camera);

        $double = array_values(array_filter($state['checklist']['warnings'], fn ($w) => $w['type'] === 'double'));
        $this->assertCount(1, $double);
        $this->assertSame('Morning shoot', $double[0]['project']);
    }

    // ------------------------------------------------------------ checklist

    public function test_ticks_survive_a_rebuild_and_reset_when_gear_is_put_back(): void
    {
        $project = $this->project();
        $camera = $this->item('Blackmagic Studio Camera 4K Pro');
        $state = $this->add($project, $camera);
        $key = collect($this->rows($state))->firstWhere('label', 'AC adapter')['key'];

        $payload = $this->postJson(route('podplan.checklist.tick', $project), ['key' => $key, 'packed' => true])->assertOk()->json();
        $this->assertSame(1, $payload['checklist']['packed']);

        // Flipping live mixing rebuilds every row; the tick stays on its row.
        $state = $this->patchJson(route('podplan.plan.live', $project), ['live' => true])->json();
        $this->assertTrue(collect($this->rows($state))->firstWhere('label', 'AC adapter')['packed']);

        $this->deleteJson(route('podplan.plan.remove', [$project, $this->placementId($state, $camera)]))->assertOk();
        $state = $this->add($project, $camera);
        $this->assertFalse(collect($this->rows($state))->firstWhere('label', 'AC adapter')['packed']);
        $this->assertSame(0, $state['checklist']['packed']);

        $this->postJson(route('podplan.checklist.tick', $project), ['key' => 'p999:t:nope', 'packed' => true])->assertStatus(422);
    }

    public function test_manual_items_and_resetting_the_checklist(): void
    {
        $project = $this->project();
        $state = $this->add($project, $this->item('Nanlite Forza 500'));
        $key = $this->rows($state)[0]['key'];

        $payload = $this->postJson(route('podplan.checklist.manual.add', $project), ['label' => 'Snacks'])->assertOk()->json();
        $manual = $payload['checklist']['manual'][0];
        $this->assertSame('Snacks', $manual['label']);

        $this->postJson(route('podplan.checklist.tick', $project), ['key' => 'm:'.$manual['id'], 'packed' => true])->assertOk();
        $payload = $this->postJson(route('podplan.checklist.tick', $project), ['key' => $key, 'packed' => true])->json();
        $this->assertSame(2, $payload['checklist']['packed']);

        $payload = $this->postJson(route('podplan.checklist.reset', $project))->assertOk()->json();
        $this->assertSame(0, $payload['checklist']['packed']);
        $this->assertCount(1, $payload['checklist']['manual'], 'reset unticks; it does not delete');

        $payload = $this->deleteJson(route('podplan.checklist.manual.remove', [$project, $manual['id']]))->assertOk()->json();
        $this->assertSame([], $payload['checklist']['manual']);
    }

    public function test_the_secret_link_lets_a_guest_tick_and_reach_nothing_else(): void
    {
        $project = $this->project();
        $state = $this->add($project, $this->item('Nanlite Forza 500'));
        $key = $this->rows($state)[0]['key'];

        $payload = $this->postJson(route('podplan.checklist.share', $project))->assertOk()->json();
        $token = $project->fresh()->share_token;
        $this->assertSame(40, strlen($token));
        $this->assertSame(route('podplan.share.show', $token), $payload['share_url']);

        auth()->logout();

        $this->get(route('podplan.share.show', $token))->assertOk()->assertSee('Episode 12');
        $shared = $this->postJson(route('podplan.share.tick', $token), ['key' => $key, 'packed' => true])->assertOk()->json();
        $this->assertSame(1, $shared['checklist']['packed']);
        $this->assertArrayNotHasKey('share_url', $shared);

        $this->get(route('podplan.plan', $project))->assertRedirect(route('login'));
        $this->post(route('podplan.checklist.reset', $project))->assertRedirect(route('login'));
        $this->get(route('podplan.share.show', str_repeat('x', 40)))->assertNotFound();

        // Stopping the share kills the link.
        $this->actingAs($this->user)->deleteJson(route('podplan.checklist.unshare', $project))->assertOk();
        auth()->logout();
        $this->get(route('podplan.share.show', $token))->assertNotFound();
        $this->postJson(route('podplan.share.tick', $token), ['key' => $key, 'packed' => false])->assertNotFound();
    }

    // ------------------------------------------------------------ inventory

    public function test_item_rules_can_be_overridden_reset_and_applied_to_the_type(): void
    {
        $light = $this->item('Nanlite Forza 500');
        $this->assertFalse($light->hasCustomRules());

        $rules = ['always' => [['text' => 'Softbox'], ['type_id' => $light->type->rules['always'][0]['type_id']]], 'live' => [], 'not_live' => [], 'ask' => []];
        $this->put(route('podplan.inventory.update', $light), [
            'name' => $light->name, 'gear_type_id' => $light->gear_type_id, 'rules_json' => json_encode($rules),
        ])->assertRedirect();
        $this->assertTrue($light->fresh()->hasCustomRules());

        // Saving the preset unchanged stores no override.
        $other = $this->item('Nanlite Forza 720', 'Marius Achiței');
        $this->put(route('podplan.inventory.update', $other), [
            'name' => $other->name, 'gear_type_id' => $other->gear_type_id, 'provider' => $other->provider,
            'rules_json' => json_encode($other->type->rules),
        ])->assertRedirect();
        $this->assertFalse($other->fresh()->hasCustomRules());

        $this->post(route('podplan.inventory.apply', $light))->assertRedirect();
        $this->assertSame('Softbox', $other->fresh()->effectiveRules()['always'][0]['text']);

        $this->post(route('podplan.inventory.reset', $light))->assertRedirect();
        $this->assertFalse($light->fresh()->hasCustomRules());
    }

    public function test_a_kinds_own_rules_can_be_edited_and_its_items_follow(): void
    {
        $light = GearType::where('key', 'light')->firstOrFail();
        $rules = $light->presetRules();
        $rules['always'][] = ['text' => 'Sandbag'];

        $this->put(route('podplan.inventory.types.update', $light), ['rules_json' => json_encode($rules)])
            ->assertRedirect(route('podplan.inventory.index', ['kind' => 'light']));

        $project = $this->project();
        $this->add($project, $this->item('Nanlite Forza 500'));
        $state = $this->add($project, $this->item('Nanlite PavoTube', 'Marius Achiței'));

        $this->assertContains('Sandbag', $this->labels($state, 'Nanlite Forza 500'), 'an item that follows the kind picks it up');
        $this->assertNotContains('Sandbag', $this->labels($state, 'Nanlite PavoTube'), 'an item with its own rules does not');

        // Making one item's rules the rule for the kind brings every item back in line.
        $tube = $this->item('Nanlite PavoTube', 'Marius Achiței');
        $this->post(route('podplan.inventory.apply', $tube))->assertRedirect();
        $this->assertFalse($tube->fresh()->hasCustomRules());
        $this->assertSame(['Power cord'], $this->labels($this->state($project), 'Nanlite Forza 500'));
    }

    public function test_the_inventory_can_be_narrowed_to_a_kind_and_shows_differences_once(): void
    {
        $this->get(route('podplan.inventory.index', ['show' => 'light']))->assertOk()
            ->assertSee('Nanlite PavoTube')
            ->assertDontSee('Shure SM7B #1')
            ->assertSee(__('podplan::messages.inventory.every_brings'))
            ->assertSee(__('podplan::messages.inventory.minus', ['label' => 'Light stand']));

        $this->get(route('podplan.inventory.index', ['kind' => 'camera']))->assertOk()
            ->assertSee(__('podplan::messages.inventory.kind_eyebrow'));

        $this->get(route('podplan.inventory.index', ['view' => 'rules']))->assertOk()
            ->assertSee('Gaffer tape')
            ->assertDontSee('pp-inv-search', false);
    }

    public function test_gear_and_general_rules_can_be_added_and_removed(): void
    {
        $lens = GearType::where('key', 'lens')->firstOrFail();
        $this->post(route('podplan.inventory.store'), [
            'name' => 'Sigma 18-35mm', 'gear_type_id' => $lens->id, 'provider' => 'Glam Art Media', 'quantity' => 2,
        ])->assertRedirect();

        $item = GearItem::where('name', 'Sigma 18-35mm')->firstOrFail();
        $this->assertSame(2, $item->quantity);
        $this->assertSame('Glam Art Media', $item->provider);

        $this->delete(route('podplan.inventory.destroy', $item))->assertRedirect();
        $this->assertNull(GearItem::find($item->id));

        $this->post(route('podplan.inventory.general.store'), ['condition' => 'always', 'label' => 'Clapperboard'])->assertRedirect();
        $project = $this->project();
        $state = $this->add($project, $this->item('Nanlite Forza 500'));
        $this->assertContains('Clapperboard', $this->labels($state, 'general'));
    }

    // ------------------------------------------------------------- describe

    protected function fakeDescriber(array $result): void
    {
        $this->app->instance(PlanDescriber::class, new class($result) extends PlanDescriber
        {
            public array $seen = [];

            public function __construct(protected array $result)
            {
            }

            public function enabled(): bool
            {
                return true;
            }

            public function describe(Project $project, string $instruction, array $current): array
            {
                $this->seen = ['instruction' => $instruction, 'current' => $current];

                return $this->result;
            }
        });
    }

    public function test_describe_places_only_owned_gear_and_can_be_undone(): void
    {
        $project = $this->project();
        $kept = $this->item('Blackmagic Studio Camera 4K Pro');
        $dropped = $this->item('Nanlite Forza 500');
        $new = $this->item('Shure SM7B #1');
        $state = $this->add($project, $kept);
        $this->add($project, $dropped);
        $this->answer($project, $this->placementId($state, $kept), $this->item('Meike 35mm'));
        $keptPlacement = $this->placementId($state, $kept);

        $this->fakeDescriber(['ok' => true, 'notes' => 'Two-shot.', 'missing' => ['Teleprompter'], 'placements' => [
            ['kind' => 'gear', 'gear_item_id' => $kept->id, 'label' => 'x', 'x' => 300, 'y' => 410, 'rotation' => 10],
            ['kind' => 'gear', 'gear_item_id' => $new->id, 'label' => 'x', 'x' => 250, 'y' => 230, 'rotation' => 0],
            ['kind' => 'gear', 'gear_item_id' => 999999, 'label' => 'Invented', 'x' => 10, 'y' => 10, 'rotation' => 0],
            ['kind' => 'gear', 'gear_item_id' => $this->item('Tripod')->id, 'label' => 'an accessory', 'x' => 10, 'y' => 10, 'rotation' => 0],
            ['kind' => 'furniture', 'gear_item_id' => null, 'label' => 'table', 'x' => 230, 'y' => 195, 'rotation' => 0],
            ['kind' => 'person', 'gear_item_id' => null, 'label' => 'Host', 'x' => 180, 'y' => 219, 'rotation' => 0],
        ]]);

        $state = $this->postJson(route('podplan.plan.describe', $project), ['prompt' => 'Host with one mic'])->assertOk()->json();

        $this->assertNull($state['error']);
        $gear = collect($state['placements'])->where('kind', 'gear');
        $this->assertEqualsCanonicalizing([$kept->id, $new->id], $gear->pluck('item_id')->all());
        $this->assertSame($keptPlacement, $this->placementId($state, $kept), 'gear that stays keeps its placement');
        $this->assertSame('Meike 35mm', $gear->firstWhere('item_id', $kept->id)['sub'], '...and so its lens');
        $this->assertSame(1, collect($state['placements'])->where('kind', 'person')->count());
        $this->assertSame(['Teleprompter'], $state['ai']['missing']);
        $this->assertTrue($state['can_undo']);

        $state = $this->postJson(route('podplan.plan.undo', $project))->assertOk()->json();
        $gear = collect($state['placements'])->where('kind', 'gear');
        $this->assertEqualsCanonicalizing([$kept->id, $dropped->id], $gear->pluck('item_id')->all());
        $this->assertSame(2, collect($state['placements'])->where('kind', 'person')->count());
        $this->assertFalse($state['can_undo']);
    }

    public function test_describe_fails_soft_and_leaves_the_plan_alone(): void
    {
        $project = $this->project();
        $this->add($project, $this->item('Nanlite Forza 500'));

        config(['podplan.api_key' => null]);
        $state = $this->postJson(route('podplan.plan.describe', $project), ['prompt' => 'Anything'])->assertOk()->json();
        $this->assertNotNull($state['error']);
        $this->assertSame(1, collect($state['placements'])->where('kind', 'gear')->count());

        $this->fakeDescriber(['ok' => false, 'error' => 'busy']);
        $state = $this->postJson(route('podplan.plan.describe', $project), ['prompt' => 'Anything'])->assertOk()->json();
        $this->assertSame(__('podplan::messages.describe.errors.busy'), $state['error']);
        $this->assertFalse($state['can_undo']);
    }

    public function test_generate_plan_from_the_new_project_form(): void
    {
        $light = $this->item('Nanlite Forza 500');
        $this->fakeDescriber(['ok' => true, 'notes' => '', 'missing' => [], 'placements' => [
            ['kind' => 'gear', 'gear_item_id' => $light->id, 'label' => 'x', 'x' => 70, 'y' => 80, 'rotation' => 0],
        ]]);

        $this->post(route('podplan.projects.store'), [
            'name' => 'Described', 'shoot_type' => 'podcast', 'people_count' => 2, 'live_mixing' => 0,
            'description' => 'One key light.', 'start' => 'generate',
        ])->assertRedirect();

        $project = Project::firstOrFail();
        $this->assertSame([$light->id], $project->placements()->where('kind', 'gear')->pluck('gear_item_id')->all());
        $this->assertSame('One key light.', $project->ai_result['prompt']);
    }
}
