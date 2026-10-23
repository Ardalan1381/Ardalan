<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Inventory\RecordWasteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWasteRecordRequest;
use App\Models\InventoryItem;
use App\Models\SystemSetting;
use App\Models\WasteRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WasteRecordController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', WasteRecord::class);

        $wasteRecords = WasteRecord::query()
            ->with(['inventoryItem', 'createdBy'])
            ->latest('wasted_at')
            ->paginate(SystemSetting::get('pagination_per_page', 20));

        return view('admin.waste-records.index', ['wasteRecords' => $wasteRecords]);
    }

    public function create(): View
    {
        $this->authorize('create', WasteRecord::class);

        return view('admin.waste-records.create', [
            'items' => InventoryItem::active()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreWasteRecordRequest $request, RecordWasteAction $action): RedirectResponse
    {
        $item = InventoryItem::findOrFail($request->validated('inventory_item_id'));

        try {
            $action->execute($item, $request->validated(), $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('admin.waste-records.create')->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.waste-records.index')->with('status', __('admin.flash.waste_record_created'));
    }
}
