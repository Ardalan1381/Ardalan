<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Billing\CreateBillSplitAction;
use App\Actions\Billing\RecordSplitPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBillSplitRequest;
use App\Http\Requests\Admin\StoreSplitPaymentRequest;
use App\Models\BillSplit;
use App\Models\BillSplitPart;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BillSplitController extends Controller
{
    public function create(Order $order): View|RedirectResponse
    {
        $this->authorize('create', BillSplit::class);

        if ($order->billSplits()->exists() || $order->balanceDue() <= 0) {
            return redirect()->route('admin.orders.show', $order);
        }

        $order->load('items.options');

        return view('admin.bill-splits.create', ['order' => $order]);
    }

    public function store(StoreBillSplitRequest $request, Order $order, CreateBillSplitAction $action): RedirectResponse
    {
        try {
            $action->execute($order, $request->validated('split_type'), $request->validated('parts'), $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('admin.orders.bill-split.create', $order)->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.orders.show', $order)->with('status', __('admin.flash.bill_split_created'));
    }

    public function storePayment(StoreSplitPaymentRequest $request, BillSplitPart $part, RecordSplitPaymentAction $action): RedirectResponse
    {
        try {
            $action->execute($part, $request->validated(), $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('admin.orders.show', $part->billSplit->order)->withErrors($e->errors());
        }

        return redirect()->route('admin.orders.show', $part->billSplit->order)->with('status', __('admin.flash.split_payment_recorded'));
    }
}
