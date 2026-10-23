<?php

namespace App\Services\Printing\Connectors;

interface PrintConnectorInterface
{
    /**
     * Sends raw bytes to the physical printer. Throws a
     * \RuntimeException on any connection/write failure — PrintDocumentJob
     * catches this to mark the job failed and retry.
     */
    public function send(string $bytes): void;
}
