<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTableRequest;
use App\Http\Requests\Admin\UpdateTableRequest;
use App\Models\SystemSetting;
use App\Models\Table;
use App\Models\TableSection;
use App\Services\Qr\QrCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TableController extends Controller
{
    /** Locales a QR code can be pre-set to — matches SetLocale::SUPPORTED. */
    private const QR_LOCALES = ['en', 'fa', 'ar'];

    public function __construct(private readonly QrCodeGenerator $qrCodeGenerator) {}

    public function index(): View
    {
        $this->authorize('viewAny', Table::class);

        $tables = Table::query()->with('section')->orderBy('table_section_id')->orderBy('name')->paginate(SystemSetting::get('pagination_per_page', 20));

        return view('admin.tables.index', ['tables' => $tables]);
    }

    public function create(): View
    {
        $this->authorize('create', Table::class);

        return view('admin.tables.create', ['sections' => TableSection::orderBy('name')->get()]);
    }

    public function store(StoreTableRequest $request): RedirectResponse
    {
        Table::create($request->validated());

        return redirect()->route('admin.tables.index')->with('status', __('admin.flash.table_created'));
    }

    public function edit(Table $table): View
    {
        $this->authorize('update', $table);

        return view('admin.tables.edit', ['table' => $table, 'sections' => TableSection::orderBy('name')->get()]);
    }

    public function update(UpdateTableRequest $request, Table $table): RedirectResponse
    {
        $table->update($request->validated());

        return redirect()->route('admin.tables.index')->with('status', __('admin.flash.table_updated'));
    }

    public function destroy(Table $table): RedirectResponse
    {
        $this->authorize('delete', $table);

        $table->delete();

        return redirect()->route('admin.tables.index')->with('status', __('admin.flash.table_deleted'));
    }

    public function qr(Table $table): View
    {
        $this->authorize('view', $table);

        return view('admin.tables.qr', [
            'table' => $table,
            'url' => $this->publicUrl($table),
            'locales' => self::QR_LOCALES,
        ]);
    }

    public function qrPng(Table $table, Request $request): Response
    {
        $this->authorize('view', $table);

        $result = $this->qrCodeGenerator->png($this->publicUrl($table, $this->requestedLocale($request)));

        return response($result->getString(), 200, ['Content-Type' => $result->getMimeType()]);
    }

    public function qrSvg(Table $table, Request $request): Response
    {
        $this->authorize('view', $table);

        $result = $this->qrCodeGenerator->svg($this->publicUrl($table, $this->requestedLocale($request)));

        return response($result->getString(), 200, ['Content-Type' => $result->getMimeType()]);
    }

    private function requestedLocale(Request $request): ?string
    {
        $lang = $request->query('lang');

        return is_string($lang) && in_array($lang, self::QR_LOCALES, true) ? $lang : null;
    }

    public function regenerateQr(Table $table): RedirectResponse
    {
        $this->authorize('update', $table);

        $table->qr_token = Table::generateUniqueQrToken();
        $table->save();

        return redirect()->route('admin.tables.qr', $table)->with('status', __('admin.flash.qr_regenerated'));
    }

    /**
     * Builds the public URL a printed QR code points to. The customer-facing
     * route behind it (/t/{qr_token}) is implemented in the public menu
     * phase; the token and URL shape are fixed now so printed QR codes stay
     * valid once that route exists.
     *
     * An optional locale appends ?lang=, which SetLocale reads and persists
     * to session on that very first request — so a QR code printed for a
     * specific language opens the menu already in that language, not just
     * on the next page view.
     */
    private function publicUrl(Table $table, ?string $locale = null): string
    {
        $url = url('/t/'.$table->qr_token);

        return $locale ? $url.'?'.http_build_query(['lang' => $locale]) : $url;
    }
}
