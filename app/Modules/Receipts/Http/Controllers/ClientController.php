<?php

namespace App\Modules\Receipts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Receipts\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(): View
    {
        $clients = Client::query()
            ->withCount(['receipts', 'allocations'])
            ->orderBy('name')
            ->get();

        return view('receipts::clients.index', compact('clients'));
    }

    public function create(): View
    {
        return view('receipts::clients.form', ['client' => new Client()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Client::create($this->validated($request));

        return redirect()->route('receipts.clients.index')
            ->with('status', __('receipts::messages.clients.flash.created'));
    }

    public function edit(Client $client): View
    {
        return view('receipts::clients.form', compact('client'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $client->update($this->validated($request));

        return redirect()->route('receipts.clients.index')
            ->with('status', __('receipts::messages.clients.flash.saved'));
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return redirect()->route('receipts.clients.index')
            ->with('status', __('receipts::messages.clients.flash.deleted'));
    }

    /** @return array<string,mixed> */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
