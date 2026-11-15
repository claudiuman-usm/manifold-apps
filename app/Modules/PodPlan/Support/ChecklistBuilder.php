<?php

namespace App\Modules\PodPlan\Support;

use App\Modules\PodPlan\Models\GearItem;
use App\Modules\PodPlan\Models\GearType;
use App\Modules\PodPlan\Models\GeneralRule;
use App\Modules\PodPlan\Models\Placement;
use App\Modules\PodPlan\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds a project's packing checklist from what is on the plan.
 *
 * Nothing generated is stored: rows are recomputed from placements, rules,
 * the live-mixing switch and the answers, and only the packed state is kept
 * (podplan_ticks), keyed by a stable row key so a regenerated row keeps its
 * tick. Keys are scoped to the placement ("p12:..."), so removing gear and
 * adding it back starts with clean ticks.
 *
 * Accessories are counted pools. Every rule that needs one takes a unit from
 * the pool: the owner's own units first, rented ones only when those run out,
 * and a "short" row when the pool is empty.
 */
class ChecklistBuilder
{
    /** @var array<int,GearItem> */
    protected array $items = [];

    /** @var array<int,GearType> */
    protected array $types = [];

    /** @var array<int,int> units still free, by accessory item id */
    protected array $remaining = [];

    /** @var array<string,bool> */
    protected array $usedKeys = [];

    public function build(Project $project): array
    {
        $this->usedKeys = [];
        $this->types = GearType::withTrashed()->get()->keyBy('id')->all();
        $this->items = GearItem::with('type')->orderBy('sort')->orderBy('id')->get()->keyBy('id')->all();
        $this->remaining = [];
        foreach ($this->items as $item) {
            if (! $item->type->isPlan()) {
                $this->remaining[$item->id] = max(0, (int) $item->quantity);
            }
        }

        $placements = $project->placements()->where('kind', 'gear')->with('item.type')->get()
            ->filter(fn (Placement $p) => $p->item !== null)->values();
        $answers = $project->answers()->get()->groupBy('placement_id');
        $live = (bool) $project->live_mixing;

        // Pass 1: collect what every placement asks for, in plan order.
        // A kit with several pieces on the plan (two lavs) is still one kit to pack.
        $sections = [];
        $seen = [];
        foreach ($placements as $placement) {
            if (isset($seen[$placement->gear_item_id])) {
                continue;
            }
            $seen[$placement->gear_item_id] = true;

            $section = [
                'placement_id' => $placement->id,
                'item_id' => $placement->item->id,
                'title' => $placement->item->name,
                'provider' => $placement->item->isMine() ? null : $placement->item->provider,
                'type' => $placement->item->type->label(),
                // The gear itself is the first thing to pack, before what goes with it.
                'demands' => [['kind' => 'self', 'item_id' => $placement->item->id, 'scope' => 'p'.$placement->id, 'tags' => []]],
                'prompts' => [],
            ];
            $this->collect($placement->item, 'p'.$placement->id, $live, $section,
                $answers->get($placement->id, collect())->keyBy('question'), true);
            $sections[] = $section;
        }

        $general = ['demands' => []];
        if ($placements->isNotEmpty()) {
            foreach (GeneralRule::orderBy('sort')->orderBy('id')->get() as $rule) {
                $applies = $rule->condition === 'always'
                    || ($rule->condition === 'live' && $live)
                    || ($rule->condition === 'not_live' && ! $live);
                if (! $applies) {
                    continue;
                }
                $tags = ['live' => $rule->condition === 'live'];
                if ($rule->gear_type_id) {
                    $general['demands'][] = ['kind' => 'pool', 'type_id' => (int) $rule->gear_type_id, 'scope' => 'g', 'tags' => $tags];
                } elseif (trim((string) $rule->label) !== '') {
                    $general['demands'][] = ['kind' => 'text', 'label' => $rule->label, 'scope' => 'g', 'tags' => $tags];
                }
            }
        }

        // Pass 2: reserve the specifically chosen items first (a lens picked by
        // name), then hand out pool units, so a pick never loses to a pool take.
        foreach ($sections as $section) {
            foreach ($section['demands'] as $demand) {
                if ($demand['kind'] === 'item') {
                    $this->remaining[$demand['item_id']] = ($this->remaining[$demand['item_id']] ?? 0) - 1;
                }
            }
        }

        $ticks = $project->ticks()->pluck('row_key')->flip();
        $total = 0;
        $packed = 0;
        $short = [];
        $counter = [];

        $resolve = function (array $demand) use (&$short, &$counter, $ticks, &$total, &$packed): array {
            $tags = $demand['tags'] + ['live' => false, 'rule' => false];
            // kind / kind_sort let the checklist regroup rows by what they are
            // (all tripods together) instead of by the gear they belong to.
            $row = [
                'label' => '', 'live' => (bool) $tags['live'], 'rule' => (bool) $tags['rule'], 'rented' => null,
                'short' => false, 'self' => false, 'item_id' => null, 'kind' => null, 'kind_key' => null, 'kind_sort' => null,
            ];
            $kind = function (int $typeId) use (&$row): void {
                if ($type = $this->types[$typeId] ?? null) {
                    $row['kind'] = $type->label();
                    $row['kind_key'] = $type->key;
                    $row['kind_sort'] = (int) $type->sort;
                }
            };
            $scope = $demand['scope'];

            if ($demand['kind'] === 'self') {
                $item = $this->items[$demand['item_id']] ?? GearItem::withTrashed()->with('type')->find($demand['item_id']);
                $row['label'] = $item->name;
                $row['self'] = true;
                $row['item_id'] = $item->id;
                $kind($item->gear_type_id);
                $row['rented'] = $item->isMine() ? null : $item->provider;
                $row['key'] = $this->key($scope.':self');
            } elseif ($demand['kind'] === 'text') {
                $row['label'] = $demand['label'];
                $row['key'] = $this->key($scope.':t:'.$this->slug($demand['label']));
            } elseif ($demand['kind'] === 'item') {
                $item = $this->items[$demand['item_id']];
                $row['label'] = ($demand['prefix'] ?? '').$item->name;
                $row['item_id'] = $item->id;
                $kind($item->gear_type_id);
                $row['rented'] = $item->isMine() ? null : $item->provider;
                $row['short'] = ($this->remaining[$item->id] ?? 0) < 0;
                if ($row['short']) {
                    $short[$item->name] = ($short[$item->name] ?? 0) + 1;
                }
                $row['key'] = $this->key($scope.':q:'.$item->id);
            } else {
                $kind($demand['type_id']);
                $item = $this->take($demand['type_id']);
                if ($item) {
                    $row['item_id'] = $item->id;
                    $n = $counter[$scope.':'.$item->id] = ($counter[$scope.':'.$item->id] ?? 0) + 1;
                    $row['label'] = $item->name;
                    $row['rented'] = $item->isMine() ? null : $item->provider;
                    $row['key'] = $this->key($scope.':a:'.$item->id.':'.$n);
                } else {
                    $label = isset($this->types[$demand['type_id']]) ? $this->types[$demand['type_id']]->label() : '?';
                    $n = $counter[$scope.':x'.$demand['type_id']] = ($counter[$scope.':x'.$demand['type_id']] ?? 0) + 1;
                    $row['label'] = $label;
                    $row['short'] = true;
                    $short[$label] = ($short[$label] ?? 0) + 1;
                    $row['key'] = $this->key($scope.':a:x'.$demand['type_id'].':'.$n);
                }
            }

            $row['packed'] = isset($ticks[$row['key']]);
            $total++;
            $packed += $row['packed'] ? 1 : 0;

            return $row;
        };

        $open = 0;
        $outSections = [];
        foreach ($sections as $section) {
            $rows = array_map($resolve, $section['demands']);
            foreach ($section['prompts'] as $prompt) {
                $open += $prompt['chosen'] === null ? 1 : 0;
            }
            unset($section['demands']);
            $section['rows'] = $rows;
            $outSections[] = $section;
        }
        $generalRows = array_map($resolve, $general['demands']);

        $manual = [];
        foreach ($project->manualItems as $item) {
            $manual[] = ['id' => $item->id, 'label' => $item->label, 'packed' => (bool) $item->packed];
            $total++;
            $packed += $item->packed ? 1 : 0;
        }

        return [
            'sections' => $outSections,
            'general' => $generalRows,
            'manual' => $manual,
            'warnings' => $this->warnings($project, $placements, $short),
            'total' => $total,
            'packed' => $packed,
            'open' => $open,
        ];
    }

    /**
     * Turn one item's rules into demands on $section. $withAsk is false for an
     * item that was itself chosen as an answer (a lens), so its own rules add
     * rows but never ask further questions.
     */
    protected function collect(GearItem $item, string $scope, bool $live, array &$section, Collection $answers, bool $withAsk): void
    {
        $rules = $item->effectiveRules();
        $preset = $item->type->presetRules();

        foreach (Rules::BUCKETS as $bucket) {
            if (($bucket === 'live' && ! $live) || ($bucket === 'not_live' && $live)) {
                continue;
            }
            $presetSignatures = array_map([Rules::class, 'signature'], $preset[$bucket]);
            foreach ($rules[$bucket] as $entry) {
                $tags = [
                    'live' => $bucket === 'live',
                    'rule' => $item->hasCustomRules() && ! in_array(Rules::signature($entry), $presetSignatures, true),
                ];
                $section['demands'][] = isset($entry['type_id'])
                    ? ['kind' => 'pool', 'type_id' => $entry['type_id'], 'scope' => $scope, 'tags' => $tags]
                    : ['kind' => 'text', 'label' => $entry['text'], 'scope' => $scope, 'tags' => $tags];
            }
        }

        if (! $withAsk) {
            return;
        }

        foreach ($rules['ask'] as $ask) {
            // Some questions only make sense in one mode (the link to the mixer needs live mixing).
            if (isset($ask['when']) && ($ask['when'] === 'live') !== $live) {
                continue;
            }

            $answer = $answers->get($ask['question']);
            $prompt = [
                'placement_id' => $section['placement_id'], 'question' => $ask['question'],
                'options' => [], 'chosen' => null, 'required' => isset($ask['default']),
            ];

            if (isset($ask['type_id'])) {
                foreach ($this->items as $option) {
                    if ($option->gear_type_id === $ask['type_id']) {
                        $prompt['options'][] = [
                            'item_id' => $option->id, 'label' => $option->name,
                            'provider' => $option->isMine() ? null : $option->provider,
                        ];
                    }
                }
                $chosen = $answer && $answer->gear_item_id ? ($this->items[$answer->gear_item_id] ?? null) : null;
                if ($chosen) {
                    $prompt['chosen'] = ['item_id' => $chosen->id, 'label' => $chosen->name];
                    $section['demands'][] = ['kind' => 'item', 'item_id' => $chosen->id, 'scope' => $scope, 'tags' => []];
                    $this->collect($chosen, $scope, $live, $section, collect(), false);
                }
            } else {
                $wanted = $answer && trim((string) $answer->value) !== '' ? $answer->value : null;
                $byLabel = [];
                foreach ($ask['options'] as $option) {
                    $label = Rules::optionLabel($option);
                    $byLabel[$label] = $option;
                    $prompt['options'][] = ['value' => $label, 'label' => $label, 'provider' => null];
                }

                // The stored answer, or the default when nothing (valid) is stored.
                $label = isset($byLabel[$wanted]) ? $wanted : ($ask['default'] ?? null);
                if ($label !== null && isset($byLabel[$label])) {
                    $picked = $byLabel[$label];
                    $prompt['chosen'] = ['value' => $label, 'label' => $label, 'wireless' => is_array($picked) && ! empty($picked['wireless'])];
                    $tags = ['live' => ($ask['when'] ?? null) === 'live'];

                    if (is_array($picked)) {
                        // The answer brings its own things: a cable, or a transmitter and a receiver.
                        foreach ($picked['adds'] ?? [] as $entry) {
                            $section['demands'][] = isset($entry['type_id'])
                                ? ['kind' => 'pool', 'type_id' => $entry['type_id'], 'scope' => $scope, 'tags' => $tags]
                                : ['kind' => 'text', 'label' => $entry['text'], 'scope' => $scope, 'tags' => $tags];
                        }
                    } else {
                        $section['demands'][] = [
                            'kind' => 'text', 'label' => $ask['question'].': '.$label,
                            'scope' => $scope.':q', 'tags' => $tags,
                        ];
                    }
                }
            }

            $section['prompts'][] = $prompt;
        }
    }

    /** One free unit of an accessory type: the owner's first, then rented. */
    protected function take(int $typeId): ?GearItem
    {
        $candidates = array_filter($this->items, fn (GearItem $i) => $i->gear_type_id === $typeId);
        usort($candidates, fn (GearItem $a, GearItem $b) => [$a->isMine() ? 0 : 1, $a->sort, $a->id] <=> [$b->isMine() ? 0 : 1, $b->sort, $b->id]);

        foreach ($candidates as $item) {
            if (($this->remaining[$item->id] ?? 0) > 0) {
                $this->remaining[$item->id]--;

                return $item;
            }
        }

        return null;
    }

    /** @return list<array<string,mixed>> */
    protected function warnings(Project $project, Collection $placements, array $short): array
    {
        $warnings = [];

        $hasMixer = $placements->contains(fn (Placement $p) => $p->item->type->role === 'video_mixer');
        if ($project->live_mixing && ! $hasMixer) {
            $onPlan = $placements->pluck('gear_item_id')->all();
            $candidate = collect($this->items)->first(
                fn (GearItem $i) => $i->type->role === 'video_mixer' && ! in_array($i->id, $onPlan, true)
            );
            $warnings[] = ['type' => 'need_mixer', 'item_id' => $candidate?->id];
        }

        foreach ($short as $label => $count) {
            $warnings[] = ['type' => 'short', 'label' => $label, 'count' => $count];
        }

        if ($project->shoot_date && $placements->isNotEmpty()) {
            $clashes = Placement::query()
                ->where('kind', 'gear')
                ->whereIn('gear_item_id', $placements->pluck('gear_item_id'))
                ->where('project_id', '!=', $project->id)
                ->whereHas('project', fn ($q) => $q->whereDate('shoot_date', $project->shoot_date))
                ->with(['project', 'item'])
                ->get();
            foreach ($clashes as $clash) {
                $warnings[] = [
                    'type' => 'double',
                    'label' => $clash->item?->name,
                    'item_id' => $clash->gear_item_id,
                    'project' => $clash->project->name,
                ];
            }
        }

        return $warnings;
    }

    protected function slug(string $label): string
    {
        $slug = Str::limit(Str::slug($label), 60, '');

        return $slug !== '' ? $slug : substr(md5($label), 0, 10);
    }

    /** Keys must be unique per project; a repeated label gets a counter. */
    protected function key(string $key): string
    {
        $candidate = $key;
        $n = 1;
        while (isset($this->usedKeys[$candidate])) {
            $candidate = $key.'~'.(++$n);
        }
        $this->usedKeys[$candidate] = true;

        return $candidate;
    }
}
