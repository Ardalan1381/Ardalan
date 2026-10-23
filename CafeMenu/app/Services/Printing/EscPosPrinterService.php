<?php

namespace App\Services\Printing;

use App\Models\Printer;
use App\Services\Printing\Connectors\NetworkPrintConnector;
use App\Services\Printing\Connectors\PrintConnectorInterface;
use App\Services\Printing\Connectors\UsbPrintConnector;
use RuntimeException;

class EscPosPrinterService
{
    public function print(Printer $printer, string $bytes): void
    {
        $this->connectorFor($printer)->send($bytes);
    }

    private function connectorFor(Printer $printer): PrintConnectorInterface
    {
        return match ($printer->connection_type) {
            'network' => new NetworkPrintConnector($printer->ip_address, $printer->port ?? 9100),
            'usb' => new UsbPrintConnector($printer->device_path),
            default => throw new RuntimeException("Unknown printer connection type \"{$printer->connection_type}\"."),
        };
    }
}
