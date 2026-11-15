<?php

namespace App\Modules\PodPlan\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PodPlan\Models\GearItem;
use App\Modules\PodPlan\Models\GearType;
use App\Modules\PodPlan\Models\GeneralRule;
use App\Modules\PodPlan\Support\Rules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Inventory and rules: the gear, who it belongs to, and what goes with it. */
class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $types = GearType::orderBy('sort')->with('items')->get();
        $accessoryTypes = $types->where('kind', 'accessory')->values();

        // The panel on the right edits one thing at a time: an item (existing
        // or new), or a kind's own rules.
        $selected = null;
        $kind = null;
        if ($request->filled('item')) {
            $selected = GearItem::with('type')->find((int) $request->query('item'));
        } elseif ($request->filled('new')) {
            $type = $types->firstWhere('key', $request->query('new')) ?? $types->first();
            // A wireless kit usually has two transmitters, so a new one starts at 2 pieces.
            $selected = new GearItem(['gear_type_id' => $type->id, 'quantity' => $type->connection === 'wireless' ? 2 : 1]);
            $selected->setRelation('type', $type);
        } elseif ($request->filled('kind')) {
            $kind = $types->firstWhere('key', $request->query('kind'));
        }

        return view('podplan::inventory', [
            'types' => $types,
            'accessoryTypes' => $accessoryTypes,
            'typeNames' => $types->mapWithKeys(fn (GearType $t) => [$t->id => $t->label()]),
            'selected' => $selected,
            'kind' => $kind,
            'show' => $types->firstWhere('key', $request->query('show'))?->key,
            'rulesView' => $request->query('view') === 'rules',
            'providers' => GearItem::whereNotNull('provider')->where('provider', '!=', '')
                ->distinct()->orderBy('provider')->pluck('provider'),
            'generalRules' => GeneralRule::orderBy('sort')->orderBy('id')->with('type')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['sort'] = (int) GearItem::max('sort') + 10;
        $item = GearItem::create($data);

        return $this->back($request, ['item' => $item->id])
            ->with('status', __('podplan::messages.flash.item_added'));
    }

    public function update(Request $request, GearItem $item): RedirectResponse
    {
        $item->update($this->validated($request));

        return $this->back($request, ['item' => $item->id])
            ->with('status', __('podplan::messages.flash.item_saved'));
    }

    public function destroy(Request $request, GearItem $item): RedirectResponse
    {
        $item->delete();

        return $this->back($request)
            ->with('status', __('podplan::messages.flash.item_deleted'));
    }

    /** Change what every item of a kind brings (the kind's own rules). */
    public function updateType(Request $request, GearType $type): RedirectResponse
    {
        $data = $request->validate(['rules_json' => ['required', 'string', 'max:20000']]);
        $type->update(['rules' => $this->cleanRules(json_decode($data['rules_json'], true))]);

        return $this->back($request, ['kind' => $type->key])
            ->with('status', __('podplan::messages.flash.kind_saved', ['type' => $type->label()]));
    }

    public function resetRules(Request $request, GearItem $item): RedirectResponse
    {
        $item->update(['rule_overrides' => null]);

        return $this->back($request, ['item' => $item->id])
            ->with('status', __('podplan::messages.flash.rules_reset'));
    }

    /**
     * Make this item's rules the rules of its whole kind: the kind takes them
     * over, and every item of the kind goes back to following the kind.
     */
    public function applyToType(Request $request, GearItem $item): RedirectResponse
    {
        $item->type->update(['rules' => $item->effectiveRules()]);
        GearItem::where('gear_type_id', $item->gear_type_id)->get()->each->update(['rule_overrides' => null]);

        return $this->back($request, ['item' => $item->id])
            ->with('status', __('podplan::messages.flash.rules_applied', ['type' => $item->type->label()]));
    }

    public function storeGeneral(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'condition' => ['required', Rule::in(GeneralRule::CONDITIONS)],
            'label' => ['nullable', 'string', 'max:160', 'required_without:gear_type_id'],
            'gear_type_id' => ['nullable', 'integer', Rule::exists('podplan_gear_types', 'id')->where('kind', 'accessory')],
        ]);

        GeneralRule::create([
            'condition' => $data['condition'],
            'label' => empty($data['gear_type_id']) ? trim((string) $data['label']) : null,
            'gear_type_id' => $data['gear_type_id'] ?? null,
            'sort' => (int) GeneralRule::max('sort') + 10,
        ]);

        return redirect()->route('podplan.inventory.index', ['view' => 'rules'])
            ->with('status', __('podplan::messages.flash.rule_added'));
    }

    public function destroyGeneral(GeneralRule $rule): RedirectResponse
    {
        $rule->delete();

        return redirect()->route('podplan.inventory.index', ['view' => 'rules'])
            ->with('status', __('podplan::messages.flash.rule_deleted'));
    }

    /** Back to the inventory, keeping the kind filter the request came from. */
    protected function back(Request $request, array $params = []): RedirectResponse
    {
        $show = GearType::where('key', (string) $request->input('show'))->value('key');

        return redirect()->route('podplan.inventory.index', array_filter(['show' => $show] + $params));
    }

    /** @return array<string,mixed> */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'short_name' => ['nullable', 'string', 'max:40'],
            'gear_type_id' => ['required', 'integer', Rule::exists('podplan_gear_types', 'id')],
            'provider' => ['nullable', 'string', 'max:120'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'rules_json' => ['nullable', 'string', 'max:20000'],
        ]);

        $type = GearType::findOrFail($data['gear_type_id']);

        $out = [
            'name' => trim($data['name']),
            'short_name' => trim((string) ($data['short_name'] ?? '')) ?: null,
            'gear_type_id' => $type->id,
            'provider' => trim((string) ($data['provider'] ?? '')) ?: null,
            // Accessories: how many are in the pool. Plan gear: how many pieces
            // of it can be placed (two lavs in one wireless kit).
            'quantity' => $type->isPlan() ? min(12, (int) ($data['quantity'] ?? 1)) : (int) ($data['quantity'] ?? 1),
        ];

        if (array_key_exists('rules_json', $data) && $data['rules_json'] !== null) {
            $rules = $this->cleanRules(json_decode($data['rules_json'], true));
            // Rules identical to the preset are stored as "no override".
            $out['rule_overrides'] = Rules::same($rules, $type->rules) ? null : $rules;
        }

        return $out;
    }

    /** Drop rule entries that point at something that is not an accessory pool. */
    protected function cleanRules(mixed $rules): array
    {
        $rules = Rules::normalize($rules);
        $pools = GearType::where('kind', 'accessory')->pluck('id')->all();
        $valid = fn (array $entry) => ! isset($entry['type_id']) || in_array($entry['type_id'], $pools, true);

        foreach (array_keys($rules) as $bucket) {
            $rules[$bucket] = array_values(array_filter($rules[$bucket], $valid));
        }

        return $rules;
    }
}
