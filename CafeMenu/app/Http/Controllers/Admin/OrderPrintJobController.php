<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePrintJobRequest;
use App\Jobs\PrintDocumentJob;
use App\Models\Order;
use App\Models\PrintJob;
use Illuminate\Http\RedirectResponse;

class OrderPrintJobController extends Controller
{
    public function store(StorePrintJobRequest $request, Order $order): RedirectResponse
    {
        $printJob = PrintJob::create([
            'printable_type' => Order::class,
            'printable_id' => $order->id,
            'document_type' => 'receipt',
            'category_id' => null,
            'printer_id' => $request->validated('printer_id'),
            'status' => 'queued',
        ]);

        PrintDocumentJob::dispatch($printJob);

        return redirect()->route('admin.orders.show', $order)->with('status', __('admin.flash.receipt_print_queued'));
    }
}
