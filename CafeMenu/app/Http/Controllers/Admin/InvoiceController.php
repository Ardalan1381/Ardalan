<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Invoices\GenerateInvoiceAction;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function generate(Order $order, GenerateInvoiceAction $action): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        $invoice = $action->execute($order, request()->user());

        return redirect()->route('admin.invoices.show', $invoice);
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        $invoice->load(['lineItems', 'template', 'order', 'generatedBy']);

        return view('admin.invoices.show', ['invoice' => $invoice]);
    }
}
