<?php

namespace App\Modules\Receipts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Receipts\Models\Allocation;
use App\Modules\Receipts\Models\Client;
use App\Modules\Receipts\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPDF;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AllocationController extends Controller
{
    public function index(): View
    {
        $allocations = Allocation::query()
            ->with('client')
            ->withCount('receipts')
            ->withSum('receipts', 'amount')
            ->latest()
            ->get();

        return view('receipts::allocations.index', [
            'allocations' => $allocations,
            'baseCurrency' => config('receipts.base_currency', 'RON'),
        ]);
    }

    /** One-screen builder: invoice number + client + month + pick receipts. */
    public function create(): View
    {
        $candidates = Receipt::query()
            ->whereNull('allocation_id')
            ->with('category')
            ->latest('purchased_at')->latest('id')
            ->get();

        // Month options for the client-side filter (distinct receipt months + now).
        $months = $candidates
            ->map(fn (Receipt $r) => optional($r->purchased_at)->format('Y-m'))
            ->toBase()
            ->filter()
            ->push(now()->format('Y-m'))
            ->unique()->sortDesc()->values();

        return view('receipts::allocations.create', [
            'clients' => Client::orderBy('name')->get(),
            'candidates' => $candidates,
            'months' => $months,
            'currentMonth' => now()->format('Y-m'),
            'baseCurrency' => config('receipts.base_currency', 'RON'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:255'],
            'client_id' => ['required', 'integer', 'exists:receipt_clients,id'],
            'period_month' => ['nullable', 'string'],
            'receipt_ids' => ['required', 'array', 'min:1'],
            'receipt_ids.*' => ['integer'],
        ]);

        $allocation = Allocation::create([
            'client_id' => $data['client_id'],
            'invoice_number' => $data['invoice_number'],
            'title' => $data['invoice_number'], // legacy NOT NULL column, kept in sync
            'period_month' => $this->month($data['period_month'] ?? null),
        ]);

        Receipt::whereIn('id', $data['receipt_ids'])
            ->whereNull('allocation_id')
            ->update(['allocation_id' => $allocation->id, 'client_id' => $allocation->client_id]);

        // Redirect to a plain GET download — reliable filename/content-type across
        // servers (a file streamed from a POST response gets named after the URL).
        return redirect()->route('receipts.allocations.pdf', ['allocation' => $allocation, 'download' => 1]);
    }

    /** Render a PDF from the form without saving anything. */
    public function preview(Request $request): Response
    {
        $data = $request->validate([
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'client_id' => ['nullable', 'integer', 'exists:receipt_clients,id'],
            'period_month' => ['nullable', 'string'],
            'receipt_ids' => ['nullable', 'array'],
            'receipt_ids.*' => ['integer'],
        ]);

        $client = ! empty($data['client_id']) ? Client::find($data['client_id']) : null;
        $receipts = Receipt::whereIn('id', $data['receipt_ids'] ?? [])
            ->with('category')->orderBy('purchased_at')->get();

        return $this->makePdf($data['invoice_number'] ?? '—', $client, $this->month($data['period_month'] ?? null), $receipts)
            ->stream('preview.pdf');
    }

    public function show(Allocation $allocation): View
    {
        $allocation->load(['client', 'receipts' => fn ($q) => $q->with('category')->latest('purchased_at')]);

        $candidates = Receipt::query()
            ->whereNull('allocation_id')
            ->with('category')
            ->latest('purchased_at')->latest('id')
            ->limit(100)->get();

        return view('receipts::allocations.show', [
            'allocation' => $allocation,
            'candidates' => $candidates,
            'total' => $allocation->receipts->sum('amount'),
            'baseCurrency' => config('receipts.base_currency', 'RON'),
        ]);
    }

    public function update(Request $request, Allocation $allocation): RedirectResponse
    {
        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:255'],
            'period_month' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $allocation->update([
            'invoice_number' => $data['invoice_number'],
            'title' => $data['invoice_number'], // legacy NOT NULL column, kept in sync
            'period_month' => $this->month($data['period_month'] ?? null),
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('receipts.allocations.show', $allocation)
            ->with('status', __('receipts::messages.allocations.flash.saved'));
    }

    public function attach(Request $request, Allocation $allocation): RedirectResponse
    {
        $data = $request->validate([
            'receipt_ids' => ['required', 'array'],
            'receipt_ids.*' => ['integer'],
        ]);

        Receipt::whereIn('id', $data['receipt_ids'])
            ->whereNull('allocation_id')
            ->update(['allocation_id' => $allocation->id, 'client_id' => $allocation->client_id]);

        return redirect()->route('receipts.allocations.show', $allocation)
            ->with('status', __('receipts::messages.allocations.flash.attached'));
    }

    public function detach(Allocation $allocation, Receipt $receipt): RedirectResponse
    {
        if ($receipt->allocation_id === $allocation->id) {
            $receipt->update(['allocation_id' => null]);
        }

        return redirect()->route('receipts.allocations.show', $allocation)
            ->with('status', __('receipts::messages.allocations.flash.detached'));
    }

    public function destroy(Allocation $allocation): RedirectResponse
    {
        $allocation->receipts()->update(['allocation_id' => null]);
        $allocation->delete();

        return redirect()->route('receipts.allocations.index')
            ->with('status', __('receipts::messages.allocations.flash.deleted'));
    }

    public function pdf(Request $request, Allocation $allocation): Response
    {
        $allocation->load(['client', 'receipts' => fn ($q) => $q->with('category')->orderBy('purchased_at')]);

        $pdf = $this->makePdf($allocation->invoice_number, $allocation->client, $allocation->period_month, $allocation->receipts);
        $filename = $this->filename($allocation->invoice_number);

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    protected function makePdf(?string $invoiceNumber, ?Client $client, ?Carbon $periodMonth, Collection $receipts): DomPDF
    {
        return Pdf::loadView('receipts::allocations.pdf', [
            'invoiceNumber' => $invoiceNumber,
            'client' => $client,
            'periodMonth' => $periodMonth,
            'receipts' => $receipts,
            'total' => $receipts->sum('amount'),
            'baseCurrency' => config('receipts.base_currency', 'RON'),
            'company' => config('receipts.company'),
        ])->setPaper('a4');
    }

    protected function month(?string $value): ?Carbon
    {
        return $value && preg_match('/^\d{4}-\d{2}$/', $value)
            ? Carbon::createFromFormat('Y-m', $value)->startOfMonth()
            : null;
    }

    protected function filename(?string $invoiceNumber): string
    {
        return 'alocare-'.Str::slug($invoiceNumber ?: 'factura').'.pdf';
    }
}
