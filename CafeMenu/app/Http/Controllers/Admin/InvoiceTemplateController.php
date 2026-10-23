<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Invoices\SetDefaultInvoiceTemplateAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInvoiceTemplateRequest;
use App\Http\Requests\Admin\UpdateInvoiceTemplateRequest;
use App\Models\InvoiceTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InvoiceTemplateController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', InvoiceTemplate::class);

        $templates = InvoiceTemplate::query()->orderByDesc('is_default')->orderBy('name')->get();

        return view('admin.invoice-templates.index', ['templates' => $templates]);
    }

    public function create(): View
    {
        $this->authorize('create', InvoiceTemplate::class);

        return view('admin.invoice-templates.create');
    }

    public function store(StoreInvoiceTemplateRequest $request): RedirectResponse
    {
        InvoiceTemplate::create($request->validated());

        return redirect()->route('admin.invoice-templates.index')->with('status', __('admin.flash.invoice_template_created'));
    }

    public function edit(InvoiceTemplate $invoiceTemplate): View
    {
        $this->authorize('update', $invoiceTemplate);

        return view('admin.invoice-templates.edit', ['template' => $invoiceTemplate]);
    }

    public function update(UpdateInvoiceTemplateRequest $request, InvoiceTemplate $invoiceTemplate): RedirectResponse
    {
        $invoiceTemplate->update($request->validated());

        return redirect()->route('admin.invoice-templates.index')->with('status', __('admin.flash.invoice_template_updated'));
    }

    public function destroy(InvoiceTemplate $invoiceTemplate): RedirectResponse
    {
        $this->authorize('delete', $invoiceTemplate);

        if ($invoiceTemplate->invoices()->exists()) {
            return redirect()->route('admin.invoice-templates.index')
                ->with('error', __('admin.flash.invoice_template_delete_blocked'));
        }

        $invoiceTemplate->delete();

        return redirect()->route('admin.invoice-templates.index')->with('status', __('admin.flash.invoice_template_deleted'));
    }

    public function makeDefault(InvoiceTemplate $invoiceTemplate, SetDefaultInvoiceTemplateAction $action): RedirectResponse
    {
        $this->authorize('update', $invoiceTemplate);

        $action->execute($invoiceTemplate);

        return redirect()->route('admin.invoice-templates.index')->with('status', __('admin.flash.invoice_template_default_set'));
    }
}
