@extends('layouts.app')

@section('title', __('flower::messages.template.create_heading'))
@section('bodyClass', 'ctx-flower')

@section('content')
    <div class="crumbs">
        <a href="{{ route('dashboard') }}">{{ __('hub.nav.dashboard') }}</a>
        <span class="sep">/</span>
        <a href="{{ route('flower.index') }}">{{ __('flower::messages.title') }}</a>
        <span class="sep">/</span>
        <span>{{ __('flower::messages.template.create_heading') }}</span>
    </div>

    <div class="page-head"><h1>{{ __('flower::messages.template.create_heading') }}</h1></div>

    <div class="card card-pad" style="max-width:560px;">
        <form method="POST" action="{{ route('flower.templates.store') }}">
            @csrf
            @include('flower::templates._fields')
            <button type="submit" class="btn btn-primary">{{ __('flower::messages.template.create') }}</button>
            <a href="{{ route('flower.index') }}" class="btn">{{ __('flower::messages.template.back') }}</a>
        </form>
    </div>

    <datalist id="flower-clients">
        @foreach ($clientNames as $name)<option value="{{ $name }}"></option>@endforeach
    </datalist>
    <datalist id="flower-types">
        @foreach ($typeNames as $name)<option value="{{ $name }}"></option>@endforeach
    </datalist>
@endsection
