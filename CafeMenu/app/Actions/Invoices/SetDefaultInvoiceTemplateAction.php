<?php

namespace App\Actions\Invoices;

use App\Models\InvoiceTemplate;
use Illuminate\Support\Facades\DB;

/**
 * Ensures at most one InvoiceTemplate has is_default=true — the same
 * "exactly one primary" pattern MediaUploader::makePrimary() already uses.
 */
class SetDefaultInvoiceTemplateAction
{
    public function execute(InvoiceTemplate $template): InvoiceTemplate
    {
        DB::transaction(function () use ($template) {
            InvoiceTemplate::where('id', '!=', $template->id)->update(['is_default' => false]);
            $template->update(['is_default' => true]);
        });

        return $template->fresh();
    }
}
