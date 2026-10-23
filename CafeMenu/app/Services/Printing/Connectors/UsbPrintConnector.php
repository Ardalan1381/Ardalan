<?php

namespace App\Services\Printing\Connectors;

/**
 * Best-effort: writes raw bytes directly to a USB printer's Linux character
 * device (commonly /dev/usb/lp0), which is how a USB thermal printer
 * without a CUPS driver installed is typically addressed on a self-hosted
 * Linux box. This assumes the web server process has write permission on
 * that device node (usually via the `lp` group) — a real deployment
 * concern the operator must configure, documented in the admin UI rather
 * than solved here.
 */
class UsbPrintConnector implements PrintConnectorInterface
{
    public function __construct(
        private readonly string $devicePath,
    ) {}

    public function send(string $bytes): void
    {
        $result = @file_put_contents($this->devicePath, $bytes);

        if ($result === false) {
            throw new \RuntimeException("Failed to write to USB printer device \"{$this->devicePath}\" — check the path and permissions.");
        }
    }
}
