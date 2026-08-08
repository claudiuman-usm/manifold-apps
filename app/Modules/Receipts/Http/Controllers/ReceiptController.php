<?php

namespace App\Modules\Receipts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Receipts\Models\Category;
use App\Modules\Receipts\Models\Receipt;
use App\Modules\Receipts\Support\ReceiptExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function index(Request $request): View
    {
        $month = $this->resolveMonth($request->query('month'));
        $categories = Category::orderBy('name')->get();

        // Filtered list.
        $query = Receipt::query()->with('category')->latest('purchased_at')->latest('id');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where('merchant', 'like', "%{$search}%");
        }
        if ($request->filled('category')) {
            $query->where('category_id', $request->integer('category'));
        }
        if ($request->query('month') !== 'all') {
            $query->whereBetween('purchased_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]);
        }
        $receipts = $query->get();

        // Dashboard stats for the selected month.
        $monthReceipts = Receipt::query()
            ->whereBetween('purchased_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->get();
        $monthTotal = $monthReceipts->sum('amount');
        $byCategory = $monthReceipts->groupBy('category_id')->map(fn ($group) => $group->sum('amount'));

        $breakdown = $categories
            ->map(fn (Category $c) => ['name' => $c->name, 'total' => (float) ($byCategory[$c->id] ?? 0)])
            ->filter(fn ($row) => $row['total'] > 0)
            ->sortByDesc('total')
            ->values();

        return view('receipts::index', [
            'receipts' => $receipts,
            'categories' => $categories,
            'month' => $month,
            'monthParam' => $request->query('month'),
            'monthTotal' => $monthTotal,
            'breakdown' => $breakdown,
            'baseCurrency' => config('receipts.base_currency', 'RON'),
            'search' => $search,
            'activeCategory' => $request->integer('category'),
        ]);
    }

    public function create(): View
    {
        return view('receipts::create');
    }

    public function store(Request $request, ReceiptExtractor $extractor): RedirectResponse
    {
        $request->validate([
            'original' => ['required', 'image', 'max:12288'],
            'square_data' => ['required', 'string'],
        ]);

        // Store the original upload.
        $uuid = (string) Str::uuid();
        $ext = $request->file('original')->extension() ?: 'jpg';
        $originalPath = $request->file('original')->storeAs('receipts', "{$uuid}.{$ext}", 'local');

        // Store the client-produced 1:1 white square (JPEG data URL).
        $squareBinary = base64_decode(preg_replace('#^data:image/\w+;base64,#', '', $request->string('square_data')));
        $squarePath = "receipts/{$uuid}_sq.jpg";
        Storage::disk('local')->put($squarePath, $squareBinary);

        // Try AI extraction on the square image (fails soft -> manual review).
        $categoryNames = Category::orderBy('name')->pluck('name')->all();
        $fields = $extractor->extract(base64_encode($squareBinary), 'image/jpeg', $categoryNames);

        $categoryId = null;
        if ($fields && $fields['category']) {
            $categoryId = Category::whereRaw('LOWER(name) = ?', [strtolower($fields['category'])])->value('id');
        }

        $receipt = Receipt::create([
            'original_path' => $originalPath,
            'image_path' => $squarePath,
            'merchant' => $fields['merchant'] ?? null,
            'amount' => $fields['amount'] ?? null,
            'currency' => ($fields['currency'] ?? null) ?: config('receipts.base_currency', 'RON'),
            'purchased_at' => $fields['purchased_at'] ?? null,
            'category_id' => $categoryId,
            'status' => 'review',
        ]);

        return redirect()
            ->route('receipts.show', $receipt)
            ->with('status', __('receipts::messages.flash.created'.($fields ? '_ai' : '')));
    }

    public function show(Receipt $receipt): View
    {
        $receipt->load('category');

        return view('receipts::show', [
            'receipt' => $receipt,
            'categories' => Category::orderBy('name')->get(),
            'baseCurrency' => config('receipts.base_currency', 'RON'),
        ]);
    }

    public function update(Request $request, Receipt $receipt): RedirectResponse
    {
        $data = $request->validate([
            'merchant' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'purchased_at' => ['nullable', 'date'],
            'category_id' => ['nullable', 'integer', 'exists:receipt_categories,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $receipt->update([
            ...$data,
            'currency' => strtoupper($data['currency']),
            'status' => 'done',
        ]);

        return redirect()
            ->route('receipts.index')
            ->with('status', __('receipts::messages.flash.saved'));
    }

    public function destroy(Receipt $receipt): RedirectResponse
    {
        $receipt->delete();

        return redirect()
            ->route('receipts.index')
            ->with('status', __('receipts::messages.flash.deleted'));
    }

    protected function resolveMonth(?string $month): Carbon
    {
        if ($month && $month !== 'all' && preg_match('/^\d{4}-\d{2}$/', $month)) {
            return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        }

        return Carbon::now()->startOfMonth();
    }
}
