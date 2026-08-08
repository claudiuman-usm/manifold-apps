<?php

namespace App\Modules\Receipts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Receipts\Models\Allocation;
use App\Modules\Receipts\Models\Client;
use App\Modules\Receipts\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\View\View;

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

    public function create(): View
    {
        return view('receipts::allocations.create', [
            'clients' => Client::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:receipt_clients,id'],
            'title' => ['required', 'string', 'max:255'],
            'period_month' => ['nullable', 'date_format:Y-m'],
            'notes' => ['nullable', 'string'],
        ]);

        $allocation = Allocation::create([
            'client_id' => $data['client_id'],
            'title' => $data['title'],
            'period_month' => ! empty($data['period_month'])
                ? Carbon::createFromFormat('Y-m', $data['period_month'])->startOfMonth()
                : null,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('receipts.allocations.show', $allocation)
            ->with('status', __('receipts::messages.allocations.flash.created'));
    }

    public function show(Allocation $allocation): View
    {
        $allocation->load(['client', 'receipts' => fn ($q) => $q->with('category')->latest('purchased_at')]);

        // Unallocated receipts available to add.
        $candidates = Receipt::query()
            ->whereNull('allocation_id')
            ->with('category')
            ->latest('purchased_at')->latest('id')
            ->limit(100)
            ->get();

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
            'title' => ['required', 'string', 'max:255'],
            'period_month' => ['nullable', 'date_format:Y-m'],
            'notes' => ['nullable', 'string'],
        ]);

        $allocation->update([
            'title' => $data['title'],
            'period_month' => ! empty($data['period_month'])
                ? Carbon::createFromFormat('Y-m', $data['period_month'])->startOfMonth()
                : null,
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

        // Only pull in currently-unallocated receipts; tag them to this client.
        Receipt::whereIn('id', $data['receipt_ids'])
            ->whereNull('allocation_id')
            ->update([
                'allocation_id' => $allocation->id,
                'client_id' => $allocation->client_id,
            ]);

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

    public function pdf(Allocation $allocation): Response
    {
        $allocation->load(['client', 'receipts' => fn ($q) => $q->with('category')->orderBy('purchased_at')]);

        $pdf = Pdf::loadView('receipts::allocations.pdf', [
            'allocation' => $allocation,
            'total' => $allocation->receipts->sum('amount'),
            'baseCurrency' => config('receipts.base_currency', 'RON'),
            'appName' => config('app.name'),
        ])->setPaper('a4');

        return $pdf->stream('allocation-'.$allocation->id.'.pdf');
    }
}
