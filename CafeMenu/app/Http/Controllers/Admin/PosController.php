<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AddItemsToOrderAction;
use App\Actions\CreateStaffOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddPosOrderItemsRequest;
use App\Http\Requests\Admin\StorePosCounterOrderRequest;
use App\Http\Requests\Admin\StorePosDineInOrderRequest;
use App\Models\Category;
use App\Models\Customer;
use App\Models\LoyaltyReward;
use App\Models\Order;
use App\Models\Table;
use App\Models\TableSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(): View
    {
        $this->authorize('create', Order::class);

        $sections = TableSection::query()
            ->active()
            ->orderBy('sort_order')
            ->with(['tables' => fn ($query) => $query->active()->orderBy('name')->with([
                // Only the open session (if any) is needed to show an
                // "occupied" indicator — no need to pull a table's entire
                // session history for the table picker.
                'sessions' => fn ($q) => $q->open(),
            ])])
            ->get();

        return view('admin.pos.index', ['sections' => $sections]);
    }

    public function table(Table $table): View
    {
        $this->authorize('create', Order::class);

        $openSession = $table->sessions()->open()->first();
        $existingOrder = $openSession
            ? $openSession->orders()->with('items.options')->whereIn('status', ['pending', 'confirmed', 'preparing'])->latest()->first()
            : null;

        return view('admin.pos.table', [
            'table' => $table->load('section'),
            'existingOrder' => $existingOrder,
            'categories' => $this->catalogForPos(),
            'customers' => Customer::active()->orderBy('name')->get(),
            'loyaltyRewards' => LoyaltyReward::active()->orderBy('points_cost')->get(),
        ]);
    }

    public function counter(): View
    {
        $this->authorize('create', Order::class);

        return view('admin.pos.counter', [
            'categories' => $this->catalogForPos(),
            'customers' => Customer::active()->orderBy('name')->get(),
            'loyaltyRewards' => LoyaltyReward::active()->orderBy('points_cost')->get(),
        ]);
    }

    public function storeDineIn(Table $table, StorePosDineInOrderRequest $request, CreateStaffOrderAction $action): RedirectResponse
    {
        $data = $request->validated();
        $data['type'] = 'dine_in';
        $data['table_id'] = $table->id;

        try {
            $order = $action->execute($data, $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('admin.pos.table', $table)->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.orders.show', $order)->with('status', __('admin.flash.pos_order_created'));
    }

    public function storeCounter(StorePosCounterOrderRequest $request, CreateStaffOrderAction $action): RedirectResponse
    {
        try {
            $order = $action->execute($request->validated(), $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('admin.pos.counter')->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.orders.show', $order)->with('status', __('admin.flash.pos_order_created'));
    }

    public function addItems(Order $order, AddPosOrderItemsRequest $request, AddItemsToOrderAction $action): RedirectResponse
    {
        try {
            $action->execute($order, $request->validated('items'), $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('admin.pos.table', $order->table)->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.orders.show', $order)->with('status', __('admin.flash.pos_items_added'));
    }

    private function catalogForPos(): Collection
    {
        return Category::query()
            ->active()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with([
                'products' => fn ($query) => $query->active()->available()->orderBy('sort_order')->with([
                    'optionGroups' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                    'optionGroups.options' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                ]),
                'children' => fn ($query) => $query->active()->orderBy('sort_order'),
                'children.products' => fn ($query) => $query->active()->available()->orderBy('sort_order')->with([
                    'optionGroups' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                    'optionGroups.options' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                ]),
            ])
            ->get()
            ->filter(fn (Category $category) => $category->products->isNotEmpty()
                || $category->children->contains(fn (Category $child) => $child->products->isNotEmpty()))
            ->values();
    }
}
