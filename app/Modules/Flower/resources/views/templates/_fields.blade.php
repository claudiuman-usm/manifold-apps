@php($t = $template ?? null)

<div class="form-row">
    <label for="client">{{ __('flower::messages.template.client') }}</label>
    <input id="client" name="client" class="input" list="flower-clients"
           placeholder="{{ __('flower::messages.template.client_placeholder') }}"
           value="{{ old('client', $t?->type?->client?->name) }}" required autocomplete="off">
    @error('client')<div class="field-error">{{ $message }}</div>@enderror
</div>

<div class="form-row">
    <label for="type">{{ __('flower::messages.template.type') }}</label>
    <input id="type" name="type" class="input" list="flower-types"
           placeholder="{{ __('flower::messages.template.type_placeholder') }}"
           value="{{ old('type', $t?->type?->name) }}" required autocomplete="off">
    @error('type')<div class="field-error">{{ $message }}</div>@enderror
</div>

<div class="form-row">
    <label for="name">{{ __('flower::messages.template.name') }}</label>
    <input id="name" name="name" class="input"
           placeholder="{{ __('flower::messages.template.name_placeholder') }}"
           value="{{ old('name', $t?->name) }}" required autocomplete="off">
    @error('name')<div class="field-error">{{ $message }}</div>@enderror
</div>
