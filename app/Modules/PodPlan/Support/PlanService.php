<?php

namespace App\Modules\PodPlan\Support;

use App\Modules\PodPlan\Models\Answer;
use App\Modules\PodPlan\Models\GearItem;
use App\Modules\PodPlan\Models\GearType;
use App\Modules\PodPlan\Models\Placement;
use App\Modules\PodPlan\Models\Project;
use App\Modules\PodPlan\Models\Tick;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Everything that changes what is on a project's top-down plan, plus the
 * single JSON "state" the Plan screen renders from.
 *
 * Coordinates are the top-left corner of a symbol on a 660 x 560 canvas. The
 * bottom-right corner is FOH, the mixer corner: mixers land there by default, and
 * the canvas script runs every cable out to the edge of the canvas and along
 * it into that corner, the way cable is actually laid around a set.
 */
class PlanService
{
    public const W = 660;

    public const H = 560;

    /** FOH, the corner kept for the mixers: [x, y, width, height]. */
    public const MIXER_ZONE = [430, 456, 222, 96];

    /** Symbol box sizes, shared with the canvas script through state(). */
    public const SIZES = [
        'cam' => [40, 28], 'mic' => [16, 16], 'wireless' => [16, 16], 'light' => [34, 34],
        'box' => [48, 22], 'recorder' => [40, 22], 'person' => [32, 32], 'table' => [200, 80],
    ];

    protected const TABLE = [230, 195];

    /** Default spots per symbol: [x, y, rotation]. */
    protected const SLOTS = [
        'cam' => [[310, 372, 0], [120, 356, 42], [500, 356, -42], [215, 384, 20], [405, 384, -20]],
        'light' => [[70, 80, 0], [556, 80, 0], [70, 300, 0], [556, 300, 0], [313, 40, 0]],
        // FOH: one under another, each a step to the right, so every cable can
        // come straight up from the bottom edge without crossing the mixer below.
        'video_mixer' => [[476, 496, 0], [512, 518, 0]],
        'audio_mixer' => [[440, 474, 0]],
        'box' => [[60, 470, 0], [150, 470, 0], [240, 470, 0]],
        'recorder' => [[60, 470, 0], [150, 470, 0]],
        'mic' => [[244, 227, 0], [400, 227, 0], [290, 200, 0], [354, 200, 0]],
        'wireless' => [[204, 205, 0], [472, 205, 0], [300, 150, 0], [360, 150, 0]],
    ];

    public function __construct(protected ChecklistBuilder $builder)
    {
    }

    // ------------------------------------------------------------------ set

    /** A fresh project starts with the table and its people already placed. */
    public function seedSet(Project $project): void
    {
        $project->placements()->create([
            'kind' => 'furniture', 'label' => 'table',
            'x' => self::TABLE[0], 'y' => self::TABLE[1], 'sort' => 0,
        ]);
        $this->syncPeople($project);
    }

    /** Re-place the people around the table to match people_count. */
    public function syncPeople(Project $project): void
    {
        $project->placements()->where('kind', 'person')->delete();

        $count = max(0, min(8, (int) $project->people_count));
        foreach ($this->peoplePositions($count) as $i => [$x, $y]) {
            $project->placements()->create([
                'kind' => 'person',
                'label' => $this->personLabel($i, $count),
                'x' => $x, 'y' => $y, 'sort' => $i + 1,
            ]);
        }
    }

    /** @return list<array{0:float,1:float}> */
    public function peoplePositions(int $count): array
    {
        [$tx, $ty] = self::TABLE;
        [$tw, $th] = self::SIZES['table'];
        $midY = $ty + $th / 2 - 16;

        $ends = [[$tx - 50, $midY], [$tx + $tw + 18, $midY]];
        $positions = array_slice($ends, 0, min(2, $count));

        $rest = $count - count($positions);
        $top = min(3, $rest);
        $bottom = min(3, $rest - $top);

        foreach ([[$top, $ty - 40], [$bottom, $ty + $th + 8]] as [$n, $y]) {
            for ($i = 0; $i < $n; $i++) {
                $positions[] = [$tx + ($tw / ($n + 1)) * ($i + 1) - 16, $y];
            }
        }

        return $positions;
    }

    protected function personLabel(int $index, int $count): string
    {
        if ($index === 0) {
            return __('podplan::messages.plan.host');
        }

        return __('podplan::messages.plan.guest').($count > 2 ? ' '.$index : '');
    }

    // ----------------------------------------------------------------- gear

    /**
     * Put an inventory item on the plan. An item has as many pieces as its
     * quantity (a wireless kit with two lavs has two); once they are all
     * placed, adding again just hands back the last one.
     */
    public function addGear(Project $project, GearItem $item, ?float $x = null, ?float $y = null, ?float $rotation = null): ?Placement
    {
        if (! $item->type->isPlan()) {
            return null;
        }

        $existing = $project->placements()->where('kind', 'gear')->where('gear_item_id', $item->id)->orderBy('id')->get();
        if ($existing->count() >= max(1, (int) $item->quantity)) {
            return $existing->last();
        }

        [$sx, $sy, $srot] = $this->defaultSlot($project, $item);

        // A light starts out aimed at the set, not straight up the canvas.
        if ($rotation === null && $item->type->role === 'light') {
            $srot = $this->aimAtSet($project, ($x ?? $sx) + 17, ($y ?? $sy) + 17);
        }

        $placement = $project->placements()->create([
            'kind' => 'gear',
            'gear_item_id' => $item->id,
            'x' => $this->clampX($x ?? $sx),
            'y' => $this->clampY($y ?? $sy),
            'rotation' => $rotation ?? $srot,
            // Every camera on a plan is numbered: the lowest number not in use.
            'number' => $item->type->role === 'camera' ? $this->nextNumber($project) : null,
            'sort' => (int) $project->placements()->max('sort') + 1,
        ]);

        // Questions (the lens) belong to the item, so only its first piece carries them.
        if ($existing->isEmpty()) {
            $this->rememberAnswers($project, $placement, $item);
        }

        return $placement;
    }

    /** "Ask me every time" answers start from what this item was given last time. */
    protected function rememberAnswers(Project $project, Placement $placement, GearItem $item): void
    {
        $remembered = (array) $item->last_answers;

        foreach ($item->effectiveRules()['ask'] as $ask) {
            // A question with a default always starts from that default, not from last time.
            if (isset($ask['default'])) {
                continue;
            }
            $last = $remembered[$ask['question']] ?? null;
            if (! is_array($last)) {
                continue;
            }
            $chosenId = $last['gear_item_id'] ?? null;
            if ($chosenId && ! GearItem::whereKey($chosenId)->exists()) {
                continue;
            }
            if (! $chosenId && trim((string) ($last['value'] ?? '')) === '') {
                continue;
            }

            Answer::create([
                'project_id' => $project->id,
                'placement_id' => $placement->id,
                'question' => $ask['question'],
                'gear_item_id' => $chosenId,
                'value' => $last['value'] ?? null,
            ]);
        }
    }

    /** Store (or clear) the answer to one of a placed item's questions. */
    public function answer(Placement $placement, string $question, ?int $gearItemId, ?string $value): void
    {
        $value = $value !== null ? trim($value) : null;

        if (! $gearItemId && ($value === null || $value === '')) {
            Answer::where('placement_id', $placement->id)->where('question', $question)->delete();

            return;
        }

        Answer::updateOrCreate(
            ['placement_id' => $placement->id, 'question' => $question],
            ['project_id' => $placement->project_id, 'gear_item_id' => $gearItemId, 'value' => $gearItemId ? null : $value],
        );

        $item = $placement->item;
        $ask = $item ? collect($item->effectiveRules()['ask'])->firstWhere('question', $question) : null;

        if ($item && ! isset($ask['default'])) {
            $remembered = (array) $item->last_answers;
            $remembered[$question] = ['gear_item_id' => $gearItemId, 'value' => $gearItemId ? null : $value];
            $item->last_answers = $remembered;
            $item->save();
        }
    }

    /** @return array{0:float,1:float,2:float} */
    protected function defaultSlot(Project $project, GearItem $item): array
    {
        $symbol = $item->type->symbol ?: 'box';
        $taken = $project->placements()->get(['x', 'y']);
        $free = fn (float $x, float $y) => ! $taken->contains(
            fn ($p) => abs($p->x - $x) < 14 && abs($p->y - $y) < 14
        );

        // Mics go to the people first: wired ones on the table in front of
        // them, wireless ones on the person.
        if (in_array($symbol, ['mic', 'wireless'], true)) {
            [$tw, $th] = self::SIZES['table'];
            $cx = self::TABLE[0] + $tw / 2;
            $cy = self::TABLE[1] + $th / 2;
            foreach ($project->placements()->where('kind', 'person')->get() as $person) {
                $px = $person->x + 16;
                $py = $person->y + 16;
                [$x, $y] = $symbol === 'mic'
                    ? [$px + ($cx - $px) * 0.42 - 8, $py + ($cy - $py) * 0.42 - 8]
                    : [$person->x + 22, $person->y - 8];
                if ($free($x, $y)) {
                    return [round($x), round($y), 0];
                }
            }
        }

        // Mixers have their own corner; everything else goes by its symbol.
        $slots = self::SLOTS[$item->type->role] ?? self::SLOTS[$symbol] ?? self::SLOTS['box'];

        foreach ($slots as [$x, $y, $rot]) {
            if ($free($x, $y)) {
                return [$x, $y, $rot];
            }
        }

        // Everything is taken: line the overflow up along the bottom edge.
        for ($i = 0; $i < 20; $i++) {
            if ($free(16 + $i * 32, self::H - 44)) {
                return [16 + $i * 32, self::H - 44, 0];
            }
        }

        return [16, self::H - 44, 0];
    }

    public function move(Placement $placement, ?float $x, ?float $y, ?float $rotation): void
    {
        if ($x !== null) {
            $placement->x = $this->clampX($x);
        }
        if ($y !== null) {
            $placement->y = $this->clampY($y);
        }
        if ($rotation !== null) {
            $placement->rotation = fmod($rotation, 360);
        }
        $placement->save();
    }

    /** The lowest camera number this project is not using yet. */
    protected function nextNumber(Project $project): int
    {
        $used = $project->placements()->whereNotNull('number')->pluck('number')->all();
        for ($n = 1; in_array($n, $used, true); $n++);

        return $n;
    }

    /**
     * Give a camera a number (or none). Numbers are unique on a plan, so
     * taking one another camera has swaps the two.
     */
    public function setNumber(Placement $placement, ?int $number): void
    {
        if ($number !== null) {
            $holder = Placement::where('project_id', $placement->project_id)
                ->where('number', $number)->where('id', '!=', $placement->id)->first();
            $holder?->update(['number' => $placement->number]);
        }

        $placement->update(['number' => $number]);
    }

    // ----------------------------------------------------------------- axis

    /**
     * Show, hide or move the axis of action (the 180-degree line). Turning it
     * on for the first time lays it through the two people on set.
     *
     * @param  array{x1?:float,y1?:float,x2?:float,y2?:float}  $points
     */
    public function setAxis(Project $project, bool $on, array $points = [], bool $reset = false): void
    {
        $axis = (array) $project->axis;

        if (isset($points['x1'], $points['y1'], $points['x2'], $points['y2'])) {
            $axis = [
                'x1' => max(0, min(self::W, round((float) $points['x1'], 1))),
                'y1' => max(0, min(self::H, round((float) $points['y1'], 1))),
                'x2' => max(0, min(self::W, round((float) $points['x2'], 1))),
                'y2' => max(0, min(self::H, round((float) $points['y2'], 1))),
            ];
        } elseif ($reset || ! isset($axis['x1'])) {
            $axis = $this->defaultAxis($project);
        }

        $project->update(['axis' => ['on' => $on] + array_intersect_key($axis, array_flip(['x1', 'y1', 'x2', 'y2']))]);
    }

    /** Through the first two people, reaching a little past each; level through the table otherwise. */
    protected function defaultAxis(Project $project): array
    {
        $people = $project->placements()->where('kind', 'person')->orderBy('sort')->orderBy('id')->take(2)->get();
        [$pw] = self::SIZES['person'];

        if ($people->count() === 2) {
            [$ax, $ay] = [$people[0]->x + $pw / 2, $people[0]->y + $pw / 2];
            [$bx, $by] = [$people[1]->x + $pw / 2, $people[1]->y + $pw / 2];
            $length = hypot($bx - $ax, $by - $ay) ?: 1;
            [$ux, $uy] = [($bx - $ax) / $length, ($by - $ay) / $length];
            $reach = 80;

            return [
                'x1' => max(0, min(self::W, round($ax - $ux * $reach, 1))), 'y1' => max(0, min(self::H, round($ay - $uy * $reach, 1))),
                'x2' => max(0, min(self::W, round($bx + $ux * $reach, 1))), 'y2' => max(0, min(self::H, round($by + $uy * $reach, 1))),
            ];
        }

        [$tw, $th] = self::SIZES['table'];
        $y = self::TABLE[1] + $th / 2;

        return ['x1' => self::TABLE[0] - 120.0, 'y1' => $y, 'x2' => self::TABLE[0] + $tw + 120.0, 'y2' => $y];
    }

    /**
     * Cameras on the wrong side of the axis. The working side is where most
     * cameras are; on a tie it is the side of the lowest-numbered camera.
     *
     * @param  list<array<string,mixed>>  $placements
     * @return array<int,bool> placement id => true
     */
    protected function acrossTheAxis(?array $axis, array $placements): array
    {
        if (empty($axis['on']) || ! isset($axis['x1'])) {
            return [];
        }

        [$cw, $ch] = self::SIZES['cam'];
        $sides = [];
        foreach ($placements as $p) {
            if ($p['role'] !== 'camera') {
                continue;
            }
            $cross = ($axis['x2'] - $axis['x1']) * ($p['y'] + $ch / 2 - $axis['y1'])
                - ($axis['y2'] - $axis['y1']) * ($p['x'] + $cw / 2 - $axis['x1']);
            // A camera sitting on the line itself is on neither side.
            if (abs($cross) > 1) {
                $sides[$p['id']] = ['side' => $cross > 0 ? 1 : -1, 'number' => $p['number'] ?? PHP_INT_MAX];
            }
        }

        $count = array_count_values(array_column($sides, 'side'));
        $one = $count[1] ?? 0;
        $other = $count[-1] ?? 0;
        if (! $one || ! $other) {
            return [];
        }

        if ($one !== $other) {
            $working = $one > $other ? 1 : -1;
        } else {
            uasort($sides, fn ($a, $b) => $a['number'] <=> $b['number']);
            $working = reset($sides)['side'];
        }

        return array_map(fn () => true, array_filter($sides, fn ($s) => $s['side'] !== $working));
    }

    /** Set the field of view by hand, or pass null to go back to automatic. */
    public function setFov(Placement $placement, ?float $fov): void
    {
        $placement->fov = $fov === null ? null : max(5.0, min(160.0, round($fov, 1)));
        $placement->save();
    }

    /** Rotation (0 = up the canvas, clockwise) that points from a spot at the table. */
    protected function aimAtSet(Project $project, float $cx, float $cy): float
    {
        $table = $project->placements()->where('kind', 'furniture')->first();
        [$tw, $th] = self::SIZES['table'];
        $tx = ($table->x ?? self::TABLE[0]) + $tw / 2;
        $ty = ($table->y ?? self::TABLE[1]) + $th / 2;

        return round(rad2deg(atan2($tx - $cx, -($ty - $cy))));
    }

    /** Removing gear also clears its ticks, so adding it back starts unpacked. */
    public function remove(Placement $placement): void
    {
        Tick::where('project_id', $placement->project_id)
            ->where('row_key', 'like', 'p'.$placement->id.':%')
            ->delete();
        Answer::where('placement_id', $placement->id)->delete();
        $placement->delete();
    }

    protected function clampX(float $x): float
    {
        return max(0, min(self::W - 16, round($x, 1)));
    }

    protected function clampY(float $y): float
    {
        return max(0, min(self::H - 16, round($y, 1)));
    }

    // --------------------------------------------------------------- layout

    /** What is on the plan right now, in a form applyLayout() can restore. */
    public function snapshot(Project $project): array
    {
        $placements = $project->placements()->get();
        $itemOf = $placements->pluck('gear_item_id', 'id');

        return [
            'placements' => $placements->map(fn (Placement $p) => [
                'kind' => $p->kind, 'gear_item_id' => $p->gear_item_id, 'label' => $p->label,
                'x' => $p->x, 'y' => $p->y, 'rotation' => $p->rotation, 'fov' => $p->fov, 'number' => $p->number,
            ])->all(),
            'answers' => $project->answers()->get()->map(fn (Answer $a) => [
                'item' => $itemOf[$a->placement_id] ?? null, 'question' => $a->question,
                'gear_item_id' => $a->gear_item_id, 'value' => $a->value,
            ])->filter(fn ($a) => $a['item'] !== null)->values()->all(),
        ];
    }

    /**
     * Replace the plan with $layout. Gear that stays keeps its placement row
     * (so its answers and ticks survive); gear that left is removed; people
     * and furniture are replaced wholesale.
     *
     * @param  list<array<string,mixed>>  $layout
     * @param  list<array<string,mixed>>  $answers  answers to restore for gear that comes back
     */
    public function applyLayout(Project $project, array $layout, array $answers = []): void
    {
        $existing = $project->placements()->where('kind', 'gear')->orderBy('id')->get()->groupBy('gear_item_id');

        // What the layout wants per item, never more pieces than the item has.
        $wanted = [];
        foreach ($layout as $entry) {
            if (($entry['kind'] ?? '') !== 'gear' || empty($entry['gear_item_id'])) {
                continue;
            }
            $item = GearItem::with('type')->find((int) $entry['gear_item_id']);
            if (! $item || ! $item->type->isPlan()) {
                continue;
            }
            if (count($wanted[$item->id]['entries'] ?? []) >= max(1, (int) $item->quantity)) {
                continue;
            }
            $wanted[$item->id]['item'] = $item;
            $wanted[$item->id]['entries'][] = $entry;
        }

        foreach ($wanted as $itemId => ['item' => $item, 'entries' => $entries]) {
            $placed = $existing->get($itemId, collect())->values();

            foreach ($entries as $i => $entry) {
                $x = (float) ($entry['x'] ?? 0);
                $y = (float) ($entry['y'] ?? 0);
                $rot = (float) ($entry['rotation'] ?? 0);

                if ($placement = $placed->get($i)) {
                    $this->move($placement, $x, $y, $rot);

                    continue;
                }

                $placement = $this->addGear($project, $item, $x, $y, $rot);
                if ($placement && isset($entry['fov'])) {
                    $this->setFov($placement, (float) $entry['fov']);
                }
                // An undo puts back the numbers the cameras had, "none" included.
                if ($placement && array_key_exists('number', $entry)) {
                    $this->setNumber($placement, $entry['number'] === null ? null : (int) $entry['number']);
                }
                if ($placement && $i === 0) {
                    foreach ($answers as $answer) {
                        if (($answer['item'] ?? null) === $item->id) {
                            Answer::updateOrCreate(
                                ['placement_id' => $placement->id, 'question' => $answer['question']],
                                ['project_id' => $project->id, 'gear_item_id' => $answer['gear_item_id'] ?? null, 'value' => $answer['value'] ?? null],
                            );
                        }
                    }
                }
            }
        }

        foreach ($existing as $itemId => $placed) {
            foreach ($placed->values()->slice(count($wanted[$itemId]['entries'] ?? [])) as $surplus) {
                $this->remove($surplus);
            }
        }

        $project->placements()->whereIn('kind', ['person', 'furniture'])->delete();
        $sort = 0;
        foreach ($layout as $entry) {
            $kind = $entry['kind'] ?? '';
            if (! in_array($kind, ['person', 'furniture'], true)) {
                continue;
            }
            $project->placements()->create([
                'kind' => $kind,
                'label' => Str::limit(trim((string) ($entry['label'] ?? '')), 40, '') ?: ($kind === 'furniture' ? 'table' : null),
                'x' => $this->clampX((float) ($entry['x'] ?? 0)),
                'y' => $this->clampY((float) ($entry['y'] ?? 0)),
                'rotation' => 0,
                'sort' => $sort++,
            ]);
        }
    }

    /**
     * Ask Claude for a layout and put it on the plan. The layout from before
     * is held in plan_undo until the owner keeps or undoes the result.
     *
     * @return string|null an error key, or null when the plan was replaced
     */
    public function generate(Project $project, string $prompt, PlanDescriber $describer): ?string
    {
        @set_time_limit(150);

        $before = $this->snapshot($project);
        $result = $describer->describe($project, $prompt, $before['placements']);

        if (! ($result['ok'] ?? false)) {
            return $result['error'] ?? 'failed';
        }

        $this->applyLayout($project, $result['placements'] ?? []);

        $placed = $project->placements()->where('kind', 'gear')->with('item')->get()
            ->map(fn (Placement $p) => $p->item?->name)->filter()->values()->all();

        $project->update([
            'plan_undo' => $before,
            'ai_result' => [
                'prompt' => $prompt,
                'placed' => $placed,
                'missing' => $result['missing'] ?? [],
                'notes' => $result['notes'] ?? '',
            ],
        ]);

        return null;
    }

    // ---------------------------------------------------------------- state

    /** Everything the Plan screen draws, in one payload. */
    /**
     * The plan as a guest on the secret link may see it: what is drawn and
     * nothing more. No inventory, no checklist, no item or rental details.
     */
    public function publicState(Project $project): array
    {
        $state = $this->state($project);
        $drawn = array_flip([
            'id', 'kind', 'symbol', 'role', 'label', 'sub', 'x', 'y', 'rot', 'fov',
            'number', 'wireless', 'wireless_rx', 'across',
        ]);

        return [
            'canvas' => $state['canvas'],
            'placements' => array_map(fn ($p) => array_intersect_key($p, $drawn), $state['placements']),
            'cables' => $state['cables'],
            'axis' => $state['axis'],
            'plan_warnings' => $state['plan_warnings'],
        ];
    }

    public function state(Project $project): array
    {
        $project->refresh();
        $checklist = $this->builder->build($project);
        $placements = $project->placements()->with('item.type')->get();

        // The chosen lens rides along in the label; a wireless link is noted so
        // no cable gets drawn for it.
        $subs = [];
        $wireless = [];
        foreach ($checklist['sections'] as $section) {
            foreach ($section['prompts'] as $prompt) {
                if (isset($prompt['chosen']['item_id']) && ! isset($subs[$section['placement_id']])) {
                    $subs[$section['placement_id']] = $prompt['chosen']['label'];
                }
                if (! empty($prompt['chosen']['wireless'])) {
                    $wireless[$section['placement_id']] = true;
                }
            }
        }

        $double = [];
        foreach ($checklist['warnings'] as $warning) {
            if ($warning['type'] === 'double') {
                $double[$warning['item_id']][] = $warning['project'];
            }
        }

        $fov = $this->fieldsOfView($checklist);

        // Pieces of the same kit are told apart by a letter: "DJI Mic 2 · A".
        $pieces = $placements->where('kind', 'gear')->groupBy('gear_item_id')
            ->map(fn (Collection $group) => $group->sortBy('id')->pluck('id')->values()->all());

        $out = [];
        foreach ($placements as $p) {
            if ($p->kind === 'gear' && ! $p->item) {
                continue;
            }
            $type = $p->item?->type;
            $label = $p->kind === 'gear' ? $p->item->shortLabel() : $this->setLabel($p);
            if ($p->kind === 'gear' && count($pieces[$p->gear_item_id]) > 1) {
                $label .= ' · '.chr(65 + array_search($p->id, $pieces[$p->gear_item_id], true));
            }
            $out[] = [
                'id' => $p->id,
                'kind' => $p->kind,
                'item_id' => $p->gear_item_id,
                'title' => $p->item?->name,
                'label' => $label,
                // A hand-set angle wins; otherwise a camera follows its lens and a light gets a standard beam.
                'fov' => match (true) {
                    ! in_array($type?->role, ['camera', 'light'], true) => null,
                    $p->fov !== null => $p->fov,
                    $type->role === 'camera' => $fov[$p->id] ?? Optics::DEFAULT_FOV,
                    default => Optics::LIGHT_FOV,
                },
                'fov_manual' => $p->fov !== null,
                'number' => $p->number,
                'sub' => $subs[$p->id] ?? null,
                'wireless' => isset($wireless[$p->id]),
                'symbol' => $p->kind === 'gear' ? ($type->symbol ?: 'box') : ($p->kind === 'person' ? 'person' : 'table'),
                'role' => $type?->role,
                'connection' => $type?->connection,
                'provider' => $p->item && ! $p->item->isMine() ? $p->item->provider : null,
                'x' => $p->x, 'y' => $p->y, 'rot' => $p->rotation,
            ];
        }

        // A wireless camera is marked, and so is the video mixer it sends to
        // (the nearest one, the same one a cable would have gone to).
        $receivers = [];
        $mixers = array_filter($out, fn ($p) => $p['role'] === 'video_mixer');
        foreach ($out as $p) {
            if (! $p['wireless'] || ! $mixers) {
                continue;
            }
            usort($mixers, fn ($a, $b) => (($a['x'] - $p['x']) ** 2 + ($a['y'] - $p['y']) ** 2)
                <=> (($b['x'] - $p['x']) ** 2 + ($b['y'] - $p['y']) ** 2));
            $receivers[$mixers[0]['id']] = true;
        }
        $across = $this->acrossTheAxis($project->axis, $out);
        $planWarnings = [];
        foreach ($out as $i => $p) {
            $out[$i]['wireless_rx'] = isset($receivers[$p['id']]);
            $out[$i]['across'] = isset($across[$p['id']]);
            if (isset($across[$p['id']])) {
                $planWarnings[] = ['type' => 'axis', 'number' => $p['number'], 'label' => $p['label']];
            }
        }

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'live' => (bool) $project->live_mixing,
                'people' => (int) $project->people_count,
            ],
            'canvas' => ['w' => self::W, 'h' => self::H, 'sizes' => self::SIZES, 'zone' => self::MIXER_ZONE],
            'placements' => $out,
            'cables' => $this->cables($project, $out),
            'axis' => $project->axis,
            'plan_warnings' => $planWarnings,
            'gear' => $this->gearGroups($placements, $double),
            'checklist' => $checklist,
            'ai' => $project->ai_result,
            'can_undo' => $project->plan_undo !== null,
        ];
    }

    /**
     * Angle of view per camera placement, from the lens chosen for it and the
     * speed booster the checklist gave it. Cameras without a lens are left out.
     *
     * @return array<int,float>
     */
    protected function fieldsOfView(array $checklist): array
    {
        $names = GearItem::withTrashed()->pluck('name', 'id');
        $out = [];

        foreach ($checklist['sections'] as $section) {
            $focal = null;
            foreach ($section['prompts'] as $prompt) {
                $lens = $prompt['chosen']['item_id'] ?? null;
                if ($lens && ($focal = Optics::focal($names[$lens] ?? null))) {
                    break;
                }
            }
            if (! $focal) {
                continue;
            }

            $factor = 1.0;
            foreach ($section['rows'] as $row) {
                if ($row['kind_key'] === 'lens_adapter' && $row['item_id']) {
                    $factor = Optics::factor($names[$row['item_id']] ?? null);
                    break;
                }
            }

            $out[$section['placement_id']] = Optics::fov($focal, $factor);
        }

        return $out;
    }

    protected function setLabel(Placement $p): string
    {
        return $p->kind === 'furniture' ? '' : (string) $p->label;
    }

    /**
     * Cable runs follow the gear type's connection: HDMI gear runs to the
     * nearest video mixer while live mixing is on, XLR gear to the nearest
     * audio mixer. Wireless gear draws nothing.
     *
     * @param  list<array<string,mixed>>  $placements
     * @return list<array{from:int,to:int,kind:string}>
     */
    protected function cables(Project $project, array $placements): array
    {
        $gear = array_filter($placements, fn ($p) => $p['kind'] === 'gear');
        $targets = [
            'hdmi' => $project->live_mixing ? array_filter($gear, fn ($p) => $p['role'] === 'video_mixer') : [],
            'xlr' => array_filter($gear, fn ($p) => $p['role'] === 'audio_mixer'),
        ];

        $cables = [];
        foreach ($gear as $p) {
            $candidates = $p['wireless'] ? [] : ($targets[$p['connection']] ?? []);
            if (! $candidates) {
                continue;
            }
            usort($candidates, fn ($a, $b) => (($a['x'] - $p['x']) ** 2 + ($a['y'] - $p['y']) ** 2)
                <=> (($b['x'] - $p['x']) ** 2 + ($b['y'] - $p['y']) ** 2));
            $cables[] = ['from' => $p['id'], 'to' => $candidates[0]['id'], 'kind' => $p['connection']];
        }

        return $cables;
    }

    /** The "My gear" sidebar: plan items grouped by type, with what is already placed. */
    protected function gearGroups(Collection $placements, array $double): array
    {
        $placed = $placements->where('kind', 'gear')->groupBy('gear_item_id')
            ->map(fn (Collection $group) => $group->sortBy('id')->pluck('id')->values()->all());
        $groups = [];

        $types = GearType::where('kind', 'plan')->orderBy('sort')->with('items')->get();
        foreach ($types as $type) {
            if ($type->items->isEmpty()) {
                continue;
            }
            $groups[] = [
                'label' => $type->label(),
                'items' => $type->items->map(fn (GearItem $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'short' => $i->shortLabel(),
                    'provider' => $i->isMine() ? null : $i->provider,
                    'units' => max(1, (int) $i->quantity),
                    'placement_ids' => $placed[$i->id] ?? [],
                    'double' => $double[$i->id] ?? [],
                ])->all(),
            ];
        }

        return $groups;
    }
}
