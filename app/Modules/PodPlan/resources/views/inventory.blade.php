@extends('layouts.app')
@section('title', __('podplan::messages.inventory.heading'))
@section('bodyClass', 'ctx-podplan')
@push('head') @include('podplan::partials.styles') @endpush

@php
    $M = 'podplan::messages.inventory.';
    $entryLabel = fn (array $e) => isset($e['type_id'])
        ? ($typeNames[$e['type_id']] ?? '?')
        : ($e['text'] ?? ($e['question'] ?? ''));

    // What one item does differently from its kind, in a few words:
    // "No Light stand", "+ Mic preamp". Empty when it follows the kind.
    $differences = function ($item) use ($entryLabel, $M) {
        if (! $item->hasCustomRules()) {
            return [];
        }
        $own = $item->effectiveRules();
        $kind = $item->type->presetRules();
        $out = [];
        foreach (['always', 'live', 'not_live', 'ask'] as $bucket) {
            $sig = fn (array $e) => $bucket === 'ask' ? 'q:'.$e['question'] : \App\Modules\PodPlan\Support\Rules::signature($e);
            $ownSigs = array_map($sig, $own[$bucket]);
            $kindSigs = array_map($sig, $kind[$bucket]);
            foreach ($kind[$bucket] as $e) {
                if (! in_array($sig($e), $ownSigs, true)) {
                    $out[] = __($M.'minus', ['label' => $entryLabel($e)]);
                }
            }
            foreach ($own[$bucket] as $e) {
                if (! in_array($sig($e), $kindSigs, true)) {
                    $out[] = __($M.'plus', ['label' => $entryLabel($e)]);
                }
            }
        }

        return $out;
    };

    $link = fn (array $params = []) => route('podplan.inventory.index', array_filter(['show' => $show] + $params));
    $total = $types->sum(fn ($t) => $t->items->count());
    $editing = $selected ?? $kind;
@endphp

@section('content')
<div class="pp">
    @include('podplan::partials.nav', ['active' => 'inventory'])

    <div class="pp-top">
        <div class="pp-grow">
            <h1>{{ __($M.'heading') }}</h1>
            <p class="pp-muted">{{ __($M.'subheading', ['count' => $total]) }}</p>
        </div>
        @unless ($rulesView)
            <label style="flex: 0 1 280px; min-width: 200px;">
                <span class="pp-sr">{{ __($M.'search') }}</span>
                <input type="search" class="pp-input" id="pp-inv-search" autocomplete="off" placeholder="{{ __($M.'search') }}">
            </label>
        @endunless
        <a class="pp-btn pp-btn--primary" href="{{ $link(['new' => $show ?: 'camera']) }}#pp-panel">{{ __($M.'add_item') }}</a>
    </div>

    <div class="pp-inv">
        {{-- Kinds of gear, with how many of each. --}}
        <aside class="pp-card pp-rail" aria-label="{{ __($M.'kinds') }}">
            <a href="{{ route('podplan.inventory.index') }}" @class(['is-active' => ! $show && ! $rulesView])>{{ __($M.'all_gear') }} <span>{{ $total }}</span></a>
            <a href="{{ route('podplan.inventory.index', ['view' => 'rules']) }}" @class(['is-active' => $rulesView])>{{ __($M.'general_heading') }} <span>{{ $generalRules->count() }}</span></a>
            @foreach (['plan' => 'plan_gear', 'accessory' => 'accessories'] as $kindKey => $heading)
                <div class="pp-eyebrow">{{ __($M.$heading) }}</div>
                @foreach ($types->where('kind', $kindKey) as $type)
                    @continue($type->items->isEmpty())
                    <a href="{{ route('podplan.inventory.index', ['show' => $type->key]) }}" @class(['is-active' => $show === $type->key])>{{ $type->label() }} <span>{{ $type->items->count() }}</span></a>
                @endforeach
            @endforeach
        </aside>

        <div class="pp-inv-main">
            @if ($rulesView)
                <div class="pp-card pp-stack">
                    <div>
                        <h2>{{ __($M.'general_heading') }}</h2>
                        <p class="pp-help">{{ __($M.'general_help') }}</p>
                    </div>
                    @foreach ($generalRules as $rule)
                        <div class="pp-row pp-between" style="padding-top:10px;border-top:1px solid var(--stroke);">
                            <span class="pp-row" style="gap:8px;">
                                <span class="pp-tag {{ $rule->condition === 'live' ? 'pp-tag--live' : '' }}">{{ __($M.'conditions.'.$rule->condition) }}</span>
                                {{ $rule->gear_type_id ? __($M.'pool_unit', ['type' => $rule->type?->label()]) : $rule->label }}
                            </span>
                            <form method="POST" action="{{ route('podplan.inventory.general.destroy', $rule) }}">@csrf @method('DELETE')
                                <button class="pp-btn pp-btn--danger pp-btn--tight" type="submit">{{ __($M.'remove') }}</button>
                            </form>
                        </div>
                    @endforeach
                    <form method="POST" action="{{ route('podplan.inventory.general.store') }}" class="pp-addrow" style="padding-top:12px;border-top:1px solid var(--stroke);">
                        @csrf
                        <select class="pp-input" name="condition" aria-label="{{ __($M.'rules') }}">
                            @foreach (\App\Modules\PodPlan\Models\GeneralRule::CONDITIONS as $condition)
                                <option value="{{ $condition }}">{{ __($M.'conditions.'.$condition) }}</option>
                            @endforeach
                        </select>
                        <input class="pp-input" name="label" maxlength="160" placeholder="{{ __($M.'general_label') }}" aria-label="{{ __($M.'general_label') }}">
                        <select class="pp-input" name="gear_type_id" aria-label="{{ __($M.'general_pool') }}">
                            <option value="">{{ __($M.'general_pool') }}</option>
                            @foreach ($accessoryTypes as $t) <option value="{{ $t->id }}">{{ $t->label() }}</option> @endforeach
                        </select>
                        <button class="pp-btn" type="submit">{{ __($M.'add') }}</button>
                    </form>
                    @error('label') <div class="pp-error">{{ $message }}</div> @enderror
                </div>
            @else
                <p class="pp-card pp-muted" id="pp-inv-none" hidden>{{ __($M.'no_match') }}</p>

                @foreach ($types as $type)
                    @continue($type->items->isEmpty() || ($show && $show !== $type->key))
                    @php
                        $preset = $type->presetRules();
                        $brings = array_map($entryLabel, array_merge($preset['always'], $preset['live'], $preset['not_live']));
                        $asks = array_column($preset['ask'], 'question');
                        $rented = $type->items->filter(fn ($i) => ! $i->isMine())->count();
                    @endphp
                    <details class="pp-card pp-kind" data-grp="{{ $type->id }}" open>
                        <summary>
                            <h2>{{ $type->label() }}</h2>
                            <span class="meta">
                                {{ $type->isPlan()
                                    ? __($M.'on_plan_count', ['count' => $type->items->count()])
                                    : __($M.'pieces_count', ['count' => $type->items->sum('quantity')]) }}
                                @if ($rented) · {{ __($M.'rented_count', ['count' => $rented]) }} @endif
                            </span>
                            <span class="grow"></span>
                            <a href="{{ $link(['kind' => $type->key]) }}#pp-panel">{{ __($M.'edit_kind') }}</a>
                            <span class="fold" aria-hidden="true"><span class="opened">{{ __($M.'fold_hide') }}</span><span class="closed">{{ __($M.'fold_show') }}</span></span>
                        </summary>

                        {{-- Said once, on the kind, instead of on every row. --}}
                        <div class="pp-brings">
                            @if ($brings || $asks)
                                @if ($brings)
                                    <b>{{ __($M.'every_brings') }}</b>
                                    @foreach ($brings as $b) <span class="it">{{ $b }}</span> @endforeach
                                @endif
                                @if ($asks)
                                    <b>{{ __($M.'and_asks') }}</b>
                                    @foreach ($asks as $a) <span class="it">{{ $a }}</span> @endforeach
                                @endif
                            @else
                                <span class="pp-muted">{{ __($M.'brings_nothing') }}</span>
                            @endif
                        </div>

                        <div class="pp-icols">
                            <span>{{ __($M.'name') }}</span><span>{{ __($M.'owner') }}</span><span class="qty">{{ __($M.'qty') }}</span><span>{{ __($M.'rules') }}</span>
                        </div>
                        @foreach ($type->items as $item)
                            @php($item->setRelation('type', $type))
                            @php($diff = $differences($item))
                            <a class="pp-irow {{ $selected && $selected->id === $item->id ? 'is-selected' : '' }}" href="{{ $link(['item' => $item->id]) }}#pp-panel"
                               data-in="{{ $type->id }}" data-hay="{{ $item->name }} {{ $item->short_name }} {{ $item->provider }} {{ $type->label() }} {{ implode(' ', $diff) }}">
                                <span class="nm">
                                    <strong>{{ $item->name }}</strong>
                                    @if ($item->short_name && $item->short_name !== $item->name) <small>{{ $item->short_name }}</small> @endif
                                </span>
                                <span>
                                    @if ($item->isMine())
                                        <span class="pp-muted pp-small">{{ __($M.'mine') }}</span>
                                    @else
                                        <span class="pp-tag pp-tag--rented">{{ $item->provider }}</span>
                                    @endif
                                </span>
                                <span class="qty">{{ $type->isPlan() && $item->quantity < 2 ? '' : $item->quantity }}</span>
                                <span>
                                    @if ($diff)
                                        <span class="pp-tag pp-tag--rule" style="white-space:normal;">{{ implode(', ', $diff) }}</span>
                                    @elseif ($item->hasCustomRules())
                                        <span class="pp-tag pp-tag--rule">{{ __($M.'custom') }}</span>
                                    @else
                                        <span class="same">{{ __($M.'as_kind') }}</span>
                                    @endif
                                </span>
                            </a>
                        @endforeach
                    </details>
                @endforeach
            @endif
        </div>

        @if ($editing)
            <aside class="pp-detail" id="pp-panel" aria-label="{{ $selected ? $selected->name : $kind->label() }}">
                @if ($selected)
                    @php($type = $selected->type)
                    <div class="pp-row pp-between" style="align-items:flex-start;flex-wrap:nowrap;">
                        <div style="min-width:0;">
                            <div class="pp-eyebrow">{{ $type->label() }}</div>
                            <h2>{{ $selected->exists ? $selected->name : __($M.'new_title') }}</h2>
                        </div>
                        <a class="pp-btn pp-btn--quiet pp-btn--tight" href="{{ $link() }}">{{ __($M.'close') }}</a>
                    </div>

                    <form method="POST" id="pp-item-form" class="pp-stack" style="gap:12px;"
                          action="{{ $selected->exists ? route('podplan.inventory.update', $selected) : route('podplan.inventory.store') }}">
                        @csrf
                        @if ($selected->exists) @method('PUT') @endif
                        <input type="hidden" name="rules_json" id="pp-rules-json">
                        <input type="hidden" name="show" value="{{ $show }}">

                        <div class="pp-field">
                            <label for="pp-i-name">{{ __($M.'name') }}</label>
                            <input class="pp-input" id="pp-i-name" name="name" required maxlength="160" value="{{ old('name', $selected->name) }}">
                            @error('name') <div class="pp-error">{{ $message }}</div> @enderror
                        </div>

                        @if ($selected->exists)
                            <input type="hidden" name="gear_type_id" value="{{ $type->id }}">
                        @else
                            <div class="pp-field">
                                <label for="pp-i-type">{{ __($M.'type') }}</label>
                                <select class="pp-input" id="pp-i-type" name="gear_type_id"
                                        onchange="location = @js(route('podplan.inventory.index')) + '?new=' + this.selectedOptions[0].dataset.key + '#pp-panel'">
                                    @foreach ($types as $t)
                                        <option value="{{ $t->id }}" data-key="{{ $t->key }}" @selected($t->id === $type->id)>{{ $t->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="pp-formgrid" style="grid-template-columns:repeat(2,minmax(0,1fr));">
                            @if ($type->isPlan())
                                <div class="pp-field">
                                    <label for="pp-i-short">{{ __($M.'short_name') }}</label>
                                    <input class="pp-input" id="pp-i-short" name="short_name" maxlength="40" value="{{ old('short_name', $selected->short_name) }}">
                                </div>
                            @endif
                            <div class="pp-field">
                                <label for="pp-i-qty">{{ __($M.($type->isPlan() ? 'pieces' : 'quantity')) }}</label>
                                <input class="pp-input" id="pp-i-qty" type="number" name="quantity" min="1" max="{{ $type->isPlan() ? 12 : 999 }}"
                                       value="{{ old('quantity', $selected->quantity ?: 1) }}">
                            </div>
                            <div class="pp-field" style="grid-column:1 / -1;">
                                <label for="pp-i-provider">{{ __($M.'provider_label') }}</label>
                                <input class="pp-input" id="pp-i-provider" name="provider" maxlength="120" list="pp-providers"
                                       value="{{ old('provider', $selected->provider) }}" placeholder="{{ __($M.'provider_help') }}">
                                <datalist id="pp-providers">
                                    @foreach ($providers as $provider) <option value="{{ $provider }}"> @endforeach
                                </datalist>
                            </div>
                        </div>
                        @if ($type->isPlan())
                            <p class="pp-help">{{ __($M.'pieces_help') }}</p>
                        @endif

                        <div class="pp-part">
                            <div class="pp-row" style="align-items:baseline;gap:6px 10px;margin-bottom:4px;">
                                <h3>{{ __($M.'rules_heading') }}</h3>
                                @if ($selected->hasCustomRules())
                                    <span class="pp-tag pp-tag--rule">{{ __($M.'differs') }}</span>
                                @else
                                    <span class="pp-muted pp-small">{{ __($M.'same_as_kind', ['type' => mb_strtolower($type->label())]) }}</span>
                                @endif
                            </div>
                            <div id="pp-rules"></div>
                        </div>
                    </form>

                    <div class="pp-row" style="padding-top:14px;border-top:1px solid var(--stroke);">
                        <button class="pp-btn pp-btn--primary" type="submit" form="pp-item-form">{{ __($M.'save') }}</button>
                        @if ($selected->exists)
                            @if ($selected->hasCustomRules())
                                <form method="POST" action="{{ route('podplan.inventory.reset', $selected) }}">@csrf
                                    <input type="hidden" name="show" value="{{ $show }}">
                                    <button class="pp-btn" type="submit">{{ __($M.'reset_rules') }}</button>
                                </form>
                                <form method="POST" action="{{ route('podplan.inventory.apply', $selected) }}">@csrf
                                    <input type="hidden" name="show" value="{{ $show }}">
                                    <button class="pp-btn" type="submit">{{ __($M.'use_for_kind', ['type' => mb_strtolower($type->label())]) }}</button>
                                </form>
                            @endif
                            <span style="flex:1;"></span>
                            <form method="POST" action="{{ route('podplan.inventory.destroy', $selected) }}"
                                  onsubmit="return confirm(@js(__($M.'delete_confirm')))">@csrf @method('DELETE')
                                <input type="hidden" name="show" value="{{ $show }}">
                                <button class="pp-btn pp-btn--danger" type="submit">{{ __($M.'delete') }}</button>
                            </form>
                        @endif
                    </div>
                @else
                    {{-- The kind itself: what every item of it brings. --}}
                    <div class="pp-row pp-between" style="align-items:flex-start;flex-wrap:nowrap;">
                        <div style="min-width:0;">
                            <div class="pp-eyebrow">{{ __($M.'kind_eyebrow') }}</div>
                            <h2>{{ $kind->label() }}</h2>
                        </div>
                        <a class="pp-btn pp-btn--quiet pp-btn--tight" href="{{ $link() }}">{{ __($M.'close') }}</a>
                    </div>
                    <p class="pp-help">{{ __($M.'kind_help', ['type' => mb_strtolower($kind->label())]) }}</p>

                    <form method="POST" id="pp-item-form" action="{{ route('podplan.inventory.types.update', $kind) }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="rules_json" id="pp-rules-json">
                        <input type="hidden" name="show" value="{{ $show }}">
                        <div class="pp-part"><div id="pp-rules"></div></div>
                    </form>

                    <div class="pp-row" style="padding-top:14px;border-top:1px solid var(--stroke);">
                        <button class="pp-btn pp-btn--primary" type="submit" form="pp-item-form">{{ __($M.'save') }}</button>
                    </div>
                @endif
            </aside>
        @endif
    </div>
</div>
@endsection

@unless ($rulesView)
@push('scripts')
<script>
// Filters the list as you type: every word has to be found in the item's name,
// its kind, who it is rented from, or what it does differently.
(function () {
    var box = document.getElementById('pp-inv-search');
    var rows = Array.prototype.slice.call(document.querySelectorAll('.pp-irow[data-hay]'));
    var groups = Array.prototype.slice.call(document.querySelectorAll('.pp-kind[data-grp]'));
    var none = document.getElementById('pp-inv-none');
    function fold(str) {
        return String(str || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ø/g, 'o');
    }
    var hay = rows.map(function (r) { return fold(r.getAttribute('data-hay')); });

    box.addEventListener('input', function () {
        var words = fold(box.value).split(/\s+/).filter(Boolean), seen = {}, any = false;
        rows.forEach(function (r, i) {
            var show = words.every(function (w) { return hay[i].indexOf(w) !== -1; });
            r.hidden = !show;
            if (show) { seen[r.getAttribute('data-in')] = true; any = true; }
        });
        groups.forEach(function (g) {
            g.hidden = !seen[g.getAttribute('data-grp')];
            if (words.length) g.open = true;
        });
        none.hidden = any;
    });
})();
</script>
@endpush
@endunless

@if ($editing)
@push('scripts')
<script>
(function () {
    var T = @json(__('podplan::messages.inventory'));
    var POOLS = @json($accessoryTypes->map(fn ($t) => ['id' => $t->id, 'label' => $t->label()])->values());
    var rules = @json($selected ? $selected->effectiveRules() : $kind->presetRules());
    var root = document.getElementById('pp-rules');
    var out = document.getElementById('pp-rules-json');
    var BUCKETS = ['always', 'live', 'not_live'];

    function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'})[c]; }); }
    function poolName(id) { var p = POOLS.find(function (x) { return x.id === id; }); return p ? p.label : '?'; }
    function poolOptions(first) {
        return '<option value="">' + esc(first) + '</option>' + POOLS.map(function (p) {
            return '<option value="' + p.id + '">' + esc(p.label) + '</option>';
        }).join('');
    }
    function chip(text, key) {
        return '<span class="pp-chip">' + esc(text) + '<button type="button" data-rm="' + key + '" aria-label="' + esc(T.remove) + '">&times;</button></span>';
    }

    function render() {
        var h = '';
        BUCKETS.forEach(function (b) {
            h += '<div class="pp-bucket"><span class="ttl">' + esc(T.buckets[b]) + '</span><div class="body"><div class="pp-row" style="gap:6px;">';
            if (!rules[b].length) h += '<span class="pp-muted pp-small">' + esc(T.bucket_empty) + '</span>';
            rules[b].forEach(function (e, i) { h += chip(e.type_id != null ? poolName(e.type_id) : e.text, b + ':' + i); });
            h += '</div><div class="pp-addrow">' +
                 '<input class="pp-input" data-text="' + b + '" placeholder="' + esc(T.add_text) + '" aria-label="' + esc(T.add_text) + '" maxlength="160">' +
                 '<select class="pp-input" data-pool="' + b + '" aria-label="' + esc(T.add_pool) + '">' + poolOptions(T.add_pool) + '</select>' +
                 '</div></div></div>';
        });

        h += '<div class="pp-bucket"><span class="ttl">' + esc(T.buckets.ask) + '</span><div class="body"><div class="pp-row" style="gap:6px;">';
        if (!rules.ask.length) h += '<span class="pp-muted pp-small">' + esc(T.bucket_empty) + '</span>';
        rules.ask.forEach(function (a, i) {
            var from = a.type_id != null ? poolName(a.type_id)
                : (a.options || []).map(function (o) { return typeof o === 'string' ? o : o.label; }).join(' / ');
            h += chip(a.question + ': ' + from, 'ask:' + i);
        });
        h += '</div><div class="pp-addrow">' +
             '<input class="pp-input" id="pp-ask-q" placeholder="' + esc(T.ask_question) + '" aria-label="' + esc(T.ask_question) + '" maxlength="120">' +
             '<select class="pp-input" id="pp-ask-pool" aria-label="' + esc(T.ask_from) + '">' + poolOptions(T.ask_from) + '</select>' +
             '<input class="pp-input" id="pp-ask-opts" placeholder="' + esc(T.ask_options) + '" aria-label="' + esc(T.ask_options) + '">' +
             '<button type="button" class="pp-btn pp-btn--tight" data-add="ask">' + esc(T.add) + '</button></div></div></div>';

        root.innerHTML = h;
        out.value = JSON.stringify(rules);
    }

    function addTo(bucket) {
        if (bucket === 'ask') {
            var q = document.getElementById('pp-ask-q').value.trim();
            var pool = document.getElementById('pp-ask-pool').value;
            var opts = document.getElementById('pp-ask-opts').value.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
            if (!q || (!pool && !opts.length)) return;
            rules.ask.push(pool ? {question: q, type_id: +pool} : {question: q, options: opts});
        } else {
            var text = root.querySelector('[data-text="' + bucket + '"]').value.trim();
            var sel = root.querySelector('[data-pool="' + bucket + '"]').value;
            if (sel) rules[bucket].push({type_id: +sel});
            else if (text) rules[bucket].push({text: text});
            else return;
        }
        render();
    }

    root.addEventListener('click', function (e) {
        var rm = e.target.closest('[data-rm]');
        if (rm) {
            var parts = rm.getAttribute('data-rm').split(':');
            rules[parts[0]].splice(+parts[1], 1);
            render();
            return;
        }
        var add = e.target.closest('[data-add]');
        if (add) addTo(add.getAttribute('data-add'));
    });
    // Enter in an add box adds the entry instead of saving the whole form;
    // picking from a dropdown adds straight away.
    root.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || e.target.tagName !== 'INPUT') return;
        e.preventDefault();
        addTo(e.target.hasAttribute('data-text') ? e.target.getAttribute('data-text') : 'ask');
    });
    root.addEventListener('change', function (e) {
        if (e.target.hasAttribute('data-pool') && e.target.value) addTo(e.target.getAttribute('data-pool'));
    });
    // Text typed but not yet added still counts when the form is saved.
    document.getElementById('pp-item-form').addEventListener('submit', function () {
        BUCKETS.forEach(function (b) {
            var box = root.querySelector('[data-text="' + b + '"]');
            if (box && box.value.trim()) rules[b].push({text: box.value.trim()});
        });
        out.value = JSON.stringify(rules);
    });

    render();
})();
</script>
@endpush
@endif
