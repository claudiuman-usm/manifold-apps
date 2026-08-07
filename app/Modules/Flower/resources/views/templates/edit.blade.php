@extends('layouts.app')

@section('title', __('flower::messages.template.edit_heading'))
@section('bodyClass', 'ctx-flower')

@section('content')
    <div class="crumbs">
        <a href="{{ route('dashboard') }}">{{ __('hub.nav.dashboard') }}</a>
        <span class="sep">/</span>
        <a href="{{ route('flower.index') }}">{{ __('flower::messages.title') }}</a>
        <span class="sep">/</span>
        <span>{{ $template->name }}</span>
    </div>

    <div class="page-head"><h1>{{ __('flower::messages.template.edit_heading') }}</h1></div>

    <form method="POST" action="{{ route('flower.templates.update', $template) }}">
        @csrf
        @method('PUT')

        <div class="card card-pad" style="max-width:560px;margin-bottom:20px;">
            @include('flower::templates._fields')
        </div>

        <div class="card card-pad" style="max-width:560px;margin-bottom:20px;">
            <div class="row-between" style="margin-bottom:6px;">
                <h2 style="font-size:1.05rem;margin:0;">{{ __('flower::messages.steps.heading') }}</h2>
            </div>
            <p class="muted" style="margin-top:0;font-size:.85rem;">{{ __('flower::messages.steps.hint') }}</p>

            <div id="steps-list">
                @foreach ($template->steps as $step)
                    <div class="step-row" draggable="true">
                        <span class="drag-handle" title="drag">⠿</span>
                        <input type="hidden" name="steps[{{ $loop->index }}][id]" value="{{ $step->id }}">
                        <input class="input" name="steps[{{ $loop->index }}][name]" value="{{ $step->name }}"
                               placeholder="{{ __('flower::messages.steps.name_placeholder') }}">
                        <button type="button" class="btn btn-sm btn-danger remove-step">
                            {{ __('flower::messages.steps.remove') }}
                        </button>
                    </div>
                @endforeach
            </div>

            <p id="steps-empty" class="muted" @if($template->steps->isNotEmpty()) hidden @endif
               style="font-size:.85rem;">{{ __('flower::messages.steps.empty') }}</p>

            <button type="button" id="add-step" class="btn btn-sm mt">+ {{ __('flower::messages.steps.add') }}</button>
        </div>

        <div class="flex" style="max-width:560px;">
            <button type="submit" class="btn btn-primary">{{ __('flower::messages.template.save') }}</button>
            <a href="{{ route('flower.index') }}" class="btn">{{ __('flower::messages.template.back') }}</a>
        </div>
    </form>

    <form method="POST" action="{{ route('flower.templates.destroy', $template) }}"
          style="max-width:560px;margin-top:24px;"
          onsubmit="return confirm(@js(__('flower::messages.template.delete_confirm')))">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger btn-sm">{{ __('flower::messages.template.delete') }}</button>
    </form>

    <datalist id="flower-clients">
        @foreach ($clientNames as $name)<option value="{{ $name }}"></option>@endforeach
    </datalist>
    <datalist id="flower-types">
        @foreach ($typeNames as $name)<option value="{{ $name }}"></option>@endforeach
    </datalist>
@endsection

@push('scripts')
<script>
(function () {
    const list = document.getElementById('steps-list');
    const addBtn = document.getElementById('add-step');
    const emptyMsg = document.getElementById('steps-empty');
    const placeholder = @js(__('flower::messages.steps.name_placeholder'));
    const removeLabel = @js(__('flower::messages.steps.remove'));
    let nextIndex = {{ $template->steps->count() }};

    function rowHtml(index) {
        return `<div class="step-row" draggable="true">
            <span class="drag-handle" title="drag">⠿</span>
            <input type="hidden" name="steps[${index}][id]" value="">
            <input class="input" name="steps[${index}][name]" value="" placeholder="${placeholder}">
            <button type="button" class="btn btn-sm btn-danger remove-step">${removeLabel}</button>
        </div>`;
    }

    function refreshEmpty() {
        emptyMsg.hidden = list.querySelectorAll('.step-row').length > 0;
    }

    addBtn.addEventListener('click', function () {
        list.insertAdjacentHTML('beforeend', rowHtml(nextIndex++));
        const rows = list.querySelectorAll('.step-row');
        rows[rows.length - 1].querySelector('input.input').focus();
        refreshEmpty();
    });

    list.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-step')) {
            e.target.closest('.step-row').remove();
            refreshEmpty();
        }
    });

    // Drag to reorder. Field indices stay fixed; submit order follows DOM order.
    let dragEl = null;
    list.addEventListener('dragstart', function (e) {
        dragEl = e.target.closest('.step-row');
        if (dragEl) { dragEl.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; }
    });
    list.addEventListener('dragend', function () {
        if (dragEl) dragEl.classList.remove('dragging');
        dragEl = null;
    });
    list.addEventListener('dragover', function (e) {
        e.preventDefault();
        if (!dragEl) return;
        const after = getDragAfter(e.clientY);
        if (after == null) list.appendChild(dragEl);
        else list.insertBefore(dragEl, after);
    });

    function getDragAfter(y) {
        const els = [...list.querySelectorAll('.step-row:not(.dragging)')];
        return els.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) return { offset, element: child };
            return closest;
        }, { offset: -Infinity }).element;
    }
})();
</script>
@endpush
