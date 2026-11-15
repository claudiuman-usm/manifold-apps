@extends('layouts.app')
@section('title', $project->name)
@section('bodyClass', 'ctx-podplan')
@push('head') @include('podplan::partials.styles') @endpush

@section('content')
<div class="pp">
    @include('podplan::partials.nav', ['active' => 'checklist', 'project' => $project])
    <div class="pp-top">
        <div class="pp-grow">
            <h1>{{ $project->name }}</h1>
            <p class="pp-muted">
                {{ __('podplan::messages.shoot_types.'.$project->shoot_type) }}
                @if ($project->shoot_date) · {{ $project->shoot_date->translatedFormat('j M Y') }} @endif
                @if ($project->location) · {{ $project->location }} @endif
            </p>
        </div>
    </div>

    @include('podplan::partials.checklist-app', [
        'owner' => true,
        'payload' => $payload,
        'urls' => [
            'state' => route('podplan.checklist.state', $project),
            'tick' => route('podplan.checklist.tick', $project),
            'manual' => route('podplan.checklist.manual.add', $project),
            'reset' => route('podplan.checklist.reset', $project),
            'share' => route('podplan.checklist.share', $project),
            'plan' => route('podplan.plan', $project),
        ],
    ])
</div>
@endsection
