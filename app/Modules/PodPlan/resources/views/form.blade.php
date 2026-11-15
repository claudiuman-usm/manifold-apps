@extends('layouts.app')
@section('title', $project->exists ? $project->name : __('podplan::messages.form.new_heading'))
@section('bodyClass', 'ctx-podplan')
@push('head') @include('podplan::partials.styles') @endpush

@section('content')
<div class="pp" style="max-width: 760px;">
    @include('podplan::partials.nav', ['active' => $project->exists ? 'plan' : 'projects', 'project' => $project->exists ? $project : null])

    <h1 style="margin-bottom:20px;">{{ $project->exists ? __('podplan::messages.form.edit_heading') : __('podplan::messages.form.new_heading') }}</h1>

    <form method="POST" class="pp-card pp-stack" style="gap:18px;padding:24px;border-radius:var(--r-card);"
          action="{{ $project->exists ? route('podplan.projects.update', $project) : route('podplan.projects.store') }}">
        @csrf
        @if ($project->exists) @method('PUT') @endif

        <div class="pp-field">
            <label for="pp-name">{{ __('podplan::messages.form.name') }}</label>
            <input class="pp-input" id="pp-name" name="name" required maxlength="160"
                   value="{{ old('name', $project->name) }}" placeholder="{{ __('podplan::messages.form.name_placeholder') }}">
            @error('name') <div class="pp-error">{{ $message }}</div> @enderror
        </div>

        <div class="pp-formgrid">
            <div class="pp-field">
                <label for="pp-type">{{ __('podplan::messages.form.type') }}</label>
                <select class="pp-input" id="pp-type" name="shoot_type">
                    @foreach (\App\Modules\PodPlan\Models\Project::SHOOT_TYPES as $type)
                        <option value="{{ $type }}" @selected(old('shoot_type', $project->shoot_type) === $type)>{{ __('podplan::messages.shoot_types.'.$type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="pp-field">
                <label for="pp-date">{{ __('podplan::messages.form.date') }}</label>
                <input class="pp-input" id="pp-date" type="date" name="shoot_date"
                       value="{{ old('shoot_date', $project->shoot_date?->format('Y-m-d')) }}">
                @error('shoot_date') <div class="pp-error">{{ $message }}</div> @enderror
            </div>
            <div class="pp-field">
                <label for="pp-location">{{ __('podplan::messages.form.location') }}</label>
                <input class="pp-input" id="pp-location" name="location" maxlength="160" value="{{ old('location', $project->location) }}">
            </div>
            <div class="pp-field">
                <label for="pp-people">{{ __('podplan::messages.form.people') }}</label>
                <input class="pp-input" id="pp-people" type="number" name="people_count" min="0" max="8" required
                       value="{{ old('people_count', $project->people_count) }}">
                @error('people_count') <div class="pp-error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="pp-field">
            <span class="pp-label" id="pp-live-label">{{ __('podplan::messages.form.live') }}</span>
            @php($live = (int) old('live_mixing', $project->live_mixing ? 1 : 0))
            <div class="pp-seg" role="radiogroup" aria-labelledby="pp-live-label" style="align-self:flex-start;">
                <label class="is-live"><input type="radio" name="live_mixing" value="1" @checked($live === 1)>{{ __('podplan::messages.form.yes') }}</label>
                <label><input type="radio" name="live_mixing" value="0" @checked($live === 0)>{{ __('podplan::messages.form.no') }}</label>
            </div>
            <div class="pp-help">{{ __('podplan::messages.form.live_help') }}</div>
        </div>

        @unless ($project->exists)
            <div class="pp-field">
                <label for="pp-description">{{ __('podplan::messages.form.describe') }}</label>
                <textarea class="pp-input" id="pp-description" name="description" maxlength="4000"
                          placeholder="{{ __('podplan::messages.form.describe_placeholder') }}">{{ old('description') }}</textarea>
                <div class="pp-help">{{ $aiEnabled ? __('podplan::messages.form.describe_help') : __('podplan::messages.form.describe_off') }}</div>
            </div>
            <div class="pp-row">
                @if ($aiEnabled)
                    <button class="pp-btn pp-btn--primary" type="submit" name="start" value="generate">{{ __('podplan::messages.form.generate') }}</button>
                    <button class="pp-btn" type="submit" name="start" value="empty">{{ __('podplan::messages.form.start_empty') }}</button>
                @else
                    <button class="pp-btn pp-btn--primary" type="submit" name="start" value="empty">{{ __('podplan::messages.form.start_empty') }}</button>
                @endif
                <a class="pp-btn pp-btn--quiet" href="{{ route('podplan.index') }}">{{ __('podplan::messages.form.cancel') }}</a>
            </div>
        @else
            <input type="hidden" name="description" value="{{ $project->description }}">
            <div class="pp-row">
                <button class="pp-btn pp-btn--primary" type="submit">{{ __('podplan::messages.form.save') }}</button>
                <a class="pp-btn pp-btn--quiet" href="{{ route('podplan.plan', $project) }}">{{ __('podplan::messages.form.cancel') }}</a>
            </div>
        @endunless
    </form>

    @if ($project->exists)
        <form method="POST" action="{{ route('podplan.projects.destroy', $project) }}" style="margin-top:16px;"
              onsubmit="return confirm(@js(__('podplan::messages.form.delete_confirm')))">
            @csrf @method('DELETE')
            <button class="pp-btn pp-btn--danger" type="submit">{{ __('podplan::messages.form.delete') }}</button>
        </form>
    @endif
</div>
@endsection
