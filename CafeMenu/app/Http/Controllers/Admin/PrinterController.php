<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePrinterRequest;
use App\Http\Requests\Admin\UpdatePrinterRequest;
use App\Models\Printer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PrinterController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Printer::class);

        $printers = Printer::query()->orderBy('name')->get();

        return view('admin.printers.index', ['printers' => $printers]);
    }

    public function create(): View
    {
        $this->authorize('create', Printer::class);

        return view('admin.printers.create');
    }

    public function store(StorePrinterRequest $request): RedirectResponse
    {
        Printer::create($request->validated());

        return redirect()->route('admin.printers.index')->with('status', __('admin.flash.printer_created'));
    }

    public function edit(Printer $printer): View
    {
        $this->authorize('update', $printer);

        return view('admin.printers.edit', ['printer' => $printer]);
    }

    public function update(UpdatePrinterRequest $request, Printer $printer): RedirectResponse
    {
        $printer->update($request->validated());

        return redirect()->route('admin.printers.index')->with('status', __('admin.flash.printer_updated'));
    }

    public function destroy(Printer $printer): RedirectResponse
    {
        $this->authorize('delete', $printer);

        if ($printer->printJobs()->exists() || $printer->routes()->exists()) {
            return redirect()->route('admin.printers.index')
                ->with('error', __('admin.flash.printer_delete_blocked'));
        }

        $printer->delete();

        return redirect()->route('admin.printers.index')->with('status', __('admin.flash.printer_deleted'));
    }
}
