<?php

namespace App\Http\Controllers;

use App\Actions\CallWaiterAction;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class WaiterCallController extends Controller
{
    /**
     * The table always comes from the dine-in session set by scanning a
     * table's QR code (MenuController::table()) — never from client input,
     * so a customer at one table can't summon staff to another.
     */
    public function store(CallWaiterAction $action): JsonResponse
    {
        $tableId = session('dining_table_id');
        $table = $tableId ? Table::query()->active()->find($tableId) : null;

        if (! $table) {
            throw ValidationException::withMessages([
                'table' => __('menu.errors.call_waiter_no_table'),
            ]);
        }

        $action->execute($table);

        return response()->json(['message' => __('menu.call_waiter_sent')]);
    }
}
