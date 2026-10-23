<?php

namespace App\Services\Qr;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use Endroid\QrCode\Writer\SvgWriter;

class QrCodeGenerator
{
    public function png(string $data, int $size = 500): ResultInterface
    {
        return $this->build(new PngWriter, $data, $size);
    }

    public function svg(string $data, int $size = 500): ResultInterface
    {
        return $this->build(new SvgWriter, $data, $size);
    }

    private function build(PngWriter|SvgWriter $writer, string $data, int $size): ResultInterface
    {
        return (new Builder(
            writer: $writer,
            data: $data,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 10,
        ))->build();
    }
}
