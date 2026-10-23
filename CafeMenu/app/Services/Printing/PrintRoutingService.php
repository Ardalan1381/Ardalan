<?php

namespace App\Services\Printing;

use App\Jobs\PrintDocumentJob;
use App\Models\Order;
use App\Models\PrintJob;
use App\Models\PrintRoute;

/**
 * Resolves which printer(s) a confirmed order's kitchen/bar dockets go to.
 *
 * Rule (deliberately simple for v1): if any active category-specific route
 * exists for a document type, ONLY the items matching a configured
 * category are printed (one docket per matched category/printer) — items
 * in an unrouted category are silently skipped for that docket type rather
 * than guessed at. If NO category-specific route exists at all, the
 * catch-all route (if configured) prints the WHOLE order. This avoids the
 * ambiguity of merging "leftover" items into a catch-all once some
 * categories already have their own explicit route.
 */
class PrintRoutingService
{
    /**
     * @return array<int, PrintJob>
     */
    public function queueDocketJobsForOrder(Order $order): array
    {
        $order->loadMissing('items.product');

        $jobs = [];

        foreach (['kitchen_docket', 'bar_docket'] as $documentType) {
            array_push($jobs, ...$this->queueForDocumentType($order, $documentType));
        }

        return $jobs;
    }

    /**
     * @return array<int, PrintJob>
     */
    private function queueForDocumentType(Order $order, string $documentType): array
    {
        $categoryIds = $order->items->pluck('product.category_id')->filter()->unique()->values();

        $specificRoutes = PrintRoute::active()
            ->where('document_type', $documentType)
            ->whereIn('category_id', $categoryIds)
            ->get()
            ->keyBy('category_id');

        if ($specificRoutes->isNotEmpty()) {
            return $specificRoutes->map(fn (PrintRoute $route, $categoryId) => $this->createAndDispatch(
                $order, $documentType, (int) $categoryId, $route->printer_id
            ))->values()->all();
        }

        $catchAll = PrintRoute::active()->where('document_type', $documentType)->whereNull('category_id')->first();

        if (! $catchAll) {
            return [];
        }

        return [$this->createAndDispatch($order, $documentType, null, $catchAll->printer_id)];
    }

    private function createAndDispatch(Order $order, string $documentType, ?int $categoryId, int $printerId): PrintJob
    {
        $job = PrintJob::create([
            'printable_type' => Order::class,
            'printable_id' => $order->id,
            'document_type' => $documentType,
            'category_id' => $categoryId,
            'printer_id' => $printerId,
            'status' => 'queued',
        ]);

        PrintDocumentJob::dispatch($job);

        return $job;
    }
}
