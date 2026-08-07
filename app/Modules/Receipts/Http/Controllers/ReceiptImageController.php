<?php

namespace App\Modules\Receipts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Receipts\Models\Receipt;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams receipt images from the private local disk (no storage:link needed).
 * Sits behind the module's web+auth middleware, so images are never public.
 */
class ReceiptImageController extends Controller
{
    public function show(Receipt $receipt, string $variant = 'square'): StreamedResponse|Response
    {
        $path = $variant === 'original' ? $receipt->original_path : $receipt->image_path;
        $disk = Storage::disk('local');

        if (! $path || ! $disk->exists($path)) {
            abort(404);
        }

        $mime = $disk->mimeType($path) ?: 'image/jpeg';

        return $disk->response($path, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
