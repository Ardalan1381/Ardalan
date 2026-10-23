<?php

namespace App\Jobs;

use App\Models\PrintJob;
use App\Services\Printing\EscPosPrinterService;
use App\Services\Printing\ReceiptFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Prints one queued PrintJob. Network printers can be offline — this
 * retries with backoff and leaves a clear failure trail (PrintJob.status +
 * error_message) rather than failing silently.
 */
class PrintDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 90];

    public function __construct(public PrintJob $printJob) {}

    public function handle(ReceiptFormatter $formatter, EscPosPrinterService $printerService): void
    {
        $this->printJob->increment('attempts');
        $printer = $this->printJob->printer;
        $printable = $this->printJob->printable;

        try {
            $bytes = match ($this->printJob->document_type) {
                'receipt', 'invoice' => $formatter->formatReceipt($printable, $printer),
                'kitchen_docket' => $formatter->formatDocket($printable, $printer, $this->printJob->category_id, __('printing.kitchen_docket_title')),
                'bar_docket' => $formatter->formatDocket($printable, $printer, $this->printJob->category_id, __('printing.bar_docket_title')),
            };

            $printerService->print($printer, $bytes);

            $this->printJob->update(['status' => 'sent', 'error_message' => null]);
        } catch (\Throwable $e) {
            $this->printJob->update(['status' => 'failed', 'error_message' => $e->getMessage()]);

            throw $e;
        }
    }
}
