<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Qr\QrCodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class MenuQrController extends Controller
{
    /** Locales a QR code can be pre-set to — matches SetLocale::SUPPORTED. */
    private const QR_LOCALES = ['en', 'fa', 'ar'];

    public function __construct(private readonly QrCodeGenerator $qrCodeGenerator) {}

    public function show(): View
    {
        $this->authorize('qr.manage');

        return view('admin.qr.menu', ['url' => url('/'), 'locales' => self::QR_LOCALES]);
    }

    public function png(Request $request): Response
    {
        $this->authorize('qr.manage');

        $result = $this->qrCodeGenerator->png($this->publicUrl($request));

        return response($result->getString(), 200, ['Content-Type' => $result->getMimeType()]);
    }

    public function svg(Request $request): Response
    {
        $this->authorize('qr.manage');

        $result = $this->qrCodeGenerator->svg($this->publicUrl($request));

        return response($result->getString(), 200, ['Content-Type' => $result->getMimeType()]);
    }

    private function publicUrl(Request $request): string
    {
        $lang = $request->query('lang');
        $url = url('/');

        return is_string($lang) && in_array($lang, self::QR_LOCALES, true)
            ? $url.'?'.http_build_query(['lang' => $lang])
            : $url;
    }
}
