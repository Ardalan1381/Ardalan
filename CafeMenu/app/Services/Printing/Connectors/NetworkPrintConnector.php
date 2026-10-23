<?php

namespace App\Services\Printing\Connectors;

/**
 * Raw-socket ESC/POS transmission — this is how the overwhelming majority
 * of network thermal printers accept print data: a plain TCP connection on
 * port 9100 (the de facto "raw"/JetDirect port), no protocol handshake,
 * just write bytes and close.
 */
class NetworkPrintConnector implements PrintConnectorInterface
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly float $timeoutSeconds = 5.0,
    ) {}

    public function send(string $bytes): void
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeoutSeconds);

        if ($socket === false) {
            throw new \RuntimeException("Could not connect to printer at {$this->host}:{$this->port} — {$errstr} ({$errno}).");
        }

        try {
            $written = fwrite($socket, $bytes);

            if ($written === false || $written < strlen($bytes)) {
                throw new \RuntimeException("Failed to write the full print job to {$this->host}:{$this->port}.");
            }
        } finally {
            fclose($socket);
        }
    }
}
