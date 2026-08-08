@extends('layouts.app')
@section('title', __('receipts::messages.create.heading'))
@section('bodyClass', 'ctx-receipts')

@section('content')
    <div class="crumbs">
        <a href="{{ route('dashboard') }}">{{ __('hub.nav.dashboard') }}</a>
        <span class="sep">/</span>
        <a href="{{ route('receipts.index') }}">{{ __('receipts::messages.title') }}</a>
        <span class="sep">/</span>
        <span>{{ __('receipts::messages.create.heading') }}</span>
    </div>

    <div class="page-head"><h1>{{ __('receipts::messages.create.heading') }}</h1></div>

    <form method="POST" action="{{ route('receipts.store') }}" enctype="multipart/form-data"
          id="receipt-form" class="card card-pad" style="max-width:520px;">
        @csrf
        <input type="file" id="original" name="original" accept="image/*" capture="environment" hidden required>
        <input type="hidden" name="square_data" id="square_data">

        <div id="dropzone" class="receipt-dropzone">
            <div id="dz-empty">
                <div style="font-weight:600;">{{ __('receipts::messages.create.pick') }}</div>
                <div class="muted" style="font-size:.85rem;margin-top:6px;">{{ __('receipts::messages.create.hint') }}</div>
            </div>
            <img id="preview" alt="" hidden>
        </div>
        @error('original')<div class="field-error">{{ $message }}</div>@enderror

        <canvas id="canvas" width="1200" height="1200" hidden></canvas>

        <div class="flex mt">
            <button type="submit" id="submit-btn" class="btn btn-primary" disabled>
                {{ __('receipts::messages.create.submit') }}
            </button>
            <a href="{{ route('receipts.index') }}" class="btn btn-ghost">{{ __('receipts::messages.create.cancel') }}</a>
            <span id="analyzing" class="muted" hidden>{{ __('receipts::messages.create.analyzing') }}</span>
        </div>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    const input = document.getElementById('original');
    const dropzone = document.getElementById('dropzone');
    const dzEmpty = document.getElementById('dz-empty');
    const preview = document.getElementById('preview');
    const canvas = document.getElementById('canvas');
    const squareData = document.getElementById('square_data');
    const submitBtn = document.getElementById('submit-btn');
    const form = document.getElementById('receipt-form');
    const analyzing = document.getElementById('analyzing');

    dropzone.addEventListener('click', () => input.click());

    input.addEventListener('change', () => {
        const file = input.files && input.files[0];
        if (!file) return;
        const img = new Image();
        img.onload = () => {
            // Fit the whole receipt into a 1:1 white square (contain + pad).
            const size = canvas.width;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, size, size);
            const scale = Math.min(size / img.width, size / img.height);
            const w = img.width * scale, h = img.height * scale;
            ctx.drawImage(img, (size - w) / 2, (size - h) / 2, w, h);

            const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
            squareData.value = dataUrl;
            preview.src = dataUrl;
            preview.hidden = false;
            dzEmpty.hidden = true;
            submitBtn.disabled = false;
            URL.revokeObjectURL(img.src);
        };
        img.src = URL.createObjectURL(file);
    });

    form.addEventListener('submit', () => {
        submitBtn.disabled = true;
        analyzing.hidden = false;
    });
})();
</script>
@endpush
