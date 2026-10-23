<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Models\ProductOption;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a product's (or add-on's) entire recipe from an admin form
 * submission. The list is always short, so delete-then-recreate is simpler
 * and just as correct as diffing.
 */
class SyncRecipeItemsAction
{
    /**
     * @param  array<int, array{inventory_item_id: int, quantity: string, unit_id: int}>  $lines
     */
    public function execute(Product|ProductOption $recipable, array $lines): void
    {
        DB::transaction(function () use ($recipable, $lines) {
            $recipable->recipeItems()->delete();

            foreach ($lines as $line) {
                $recipable->recipeItems()->create([
                    'inventory_item_id' => $line['inventory_item_id'],
                    'quantity' => $line['quantity'],
                    'unit_id' => $line['unit_id'],
                ]);
            }
        });
    }
}
