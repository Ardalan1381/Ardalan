<?php

namespace App\Http\Controllers;

use App\Actions\PlaceOrderAction;
use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function store(CheckoutRequest $request, PlaceOrderAction $action): JsonResponse
    {
        $order = $action->execute($request->validated(), session('dining_table_id'));

        return response()->json([
            'redirect' => route('orders.show', $order),
        ]);
    }

    public function show(Order $order, PaymentGatewayManager $gatewayManager): View
    {
        $order->load(['items.options', 'table.section', 'payments']);

        return view('orders.show', [
            'order' => $order,
            'availableGateways' => $gatewayManager->availableForCustomers(),
        ]);
    }
}
