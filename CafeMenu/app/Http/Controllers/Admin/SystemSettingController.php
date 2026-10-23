<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSystemSettingRequest;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function edit(): View
    {
        $this->authorize('settings.manage');

        return view('admin.settings.system', [
            'paginationPerPage' => SystemSetting::get('pagination_per_page', 20),
        ]);
    }

    public function update(UpdateSystemSettingRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $previous = SystemSetting::get('pagination_per_page', 20);
        $value = $request->validated('pagination_per_page');

        SystemSetting::set('pagination_per_page', $value);

        $auditLogger->log('settings.system_updated', old: ['pagination_per_page' => $previous], new: ['pagination_per_page' => $value]);

        return redirect()->route('admin.settings.system.edit')->with('status', __('admin.flash.system_settings_updated'));
    }
}
