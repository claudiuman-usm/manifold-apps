@extends('layouts.app')

@section('title', __('hub.password.title'))
@section('bodyClass', 'ctx-hub')

@section('content')
    <div class="page-head">
        <div class="crumbs">
            <a href="{{ route('dashboard') }}">{{ __('hub.nav.dashboard') }}</a>
            <span class="sep">/</span>
            <span>{{ __('hub.password.title') }}</span>
        </div>
        <h1>{{ __('hub.password.heading') }}</h1>
        <p>{{ __('hub.password.subheading') }}</p>
    </div>

    <div class="card card-pad" style="max-width:520px;">
        <form method="POST" action="{{ route('password.update') }}" id="password-form">
            @csrf
            @method('PUT')

            <div class="form-row">
                <label for="current_password">{{ __('hub.password.current') }}</label>
                <input id="current_password" name="current_password" type="password" class="input"
                       autocomplete="current-password" required autofocus>
                @error('current_password')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-row">
                <label for="password">{{ __('hub.password.new') }}</label>
                <input id="password" name="password" type="password" class="input"
                       autocomplete="new-password" minlength="{{ $minLength }}" required>
                <div class="muted" style="font-size:.8rem;margin-top:6px;">
                    {{ __('hub.password.hint', ['min' => $minLength]) }}
                </div>
                @error('password')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-row">
                <label for="password_confirmation">{{ __('hub.password.confirm') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="input"
                       autocomplete="new-password" minlength="{{ $minLength }}" required>
                @error('password_confirmation')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-row checkbox-row">
                <input id="show-passwords" type="checkbox">
                <label for="show-passwords" style="margin:0;font-weight:500;">{{ __('hub.password.show') }}</label>
            </div>

            <div class="flex gap-sm" style="align-items:center;">
                <button type="submit" class="btn btn-primary">{{ __('hub.password.submit') }}</button>
                <a href="{{ route('dashboard') }}" class="btn btn-ghost">{{ __('hub.password.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        var toggle = document.getElementById('show-passwords');
        var form = document.getElementById('password-form');
        if (!toggle || !form) return;
        var fields = Array.prototype.slice.call(form.querySelectorAll('input[type="password"]'));
        toggle.addEventListener('change', function () {
            fields.forEach(function (input) {
                input.type = toggle.checked ? 'text' : 'password';
            });
        });
    })();
</script>
@endpush
