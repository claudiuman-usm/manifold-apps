{{--
    Module bar, on every PodPlan screen: the hub breadcrumb with PodPlan's
    version, and the pill menu. $active is projects | plan | checklist |
    inventory. Plan and Checklist open the current project (see
    Support\CurrentProject); where the page is not about that project, its name
    is the last crumb so it is clear which one they open.
--}}
@php($navProject = \App\Modules\PodPlan\Support\CurrentProject::resolve($project ?? null))
@php($navVersion = app(\App\Modules\ModuleRegistry::class)->get('podplan')?->manifest['version'] ?? null)
<div class="pp-bar">
    <div class="crumbs">
        <a href="{{ route('dashboard') }}">{{ __('hub.nav.dashboard') }}</a>
        <span class="sep">/</span>
        <a href="{{ route('podplan.index') }}">{{ __('podplan::messages.title') }}</a>
        @if ($navVersion)
            <span class="pp-ver">{{ __('podplan::messages.version', ['version' => $navVersion]) }}</span>
        @endif
        @if ($navProject && ! in_array($active, ['plan', 'checklist'], true))
            <span class="sep">/</span>
            <a href="{{ route('podplan.plan', $navProject) }}" title="{{ __('podplan::messages.nav.current') }}" style="font-weight:500;color:var(--text-2);">{{ $navProject->name }}</a>
        @endif
    </div>
    <nav class="pp-nav" aria-label="{{ __('podplan::messages.title') }}">
        <a href="{{ route('podplan.index') }}" @class(['is-active' => $active === 'projects'])
           @if ($active === 'projects') aria-current="page" @endif>{{ __('podplan::messages.nav.projects') }}</a>
        @if ($navProject)
            <a href="{{ route('podplan.plan', $navProject) }}" @class(['is-active' => $active === 'plan'])
               @if ($active === 'plan') aria-current="page" @endif>{{ __('podplan::messages.nav.plan') }}</a>
            <a href="{{ route('podplan.checklist', $navProject) }}" @class(['is-active' => $active === 'checklist'])
               @if ($active === 'checklist') aria-current="page" @endif>{{ __('podplan::messages.nav.checklist') }}</a>
        @endif
        <a href="{{ route('podplan.inventory.index') }}" @class(['is-active' => $active === 'inventory'])
           @if ($active === 'inventory') aria-current="page" @endif>{{ __('podplan::messages.nav.inventory_short') }}</a>
    </nav>
</div>
