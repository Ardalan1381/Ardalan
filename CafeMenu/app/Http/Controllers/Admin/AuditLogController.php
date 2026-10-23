<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('audit.view');

        $logs = AuditLog::query()
            ->with('user')
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
            ->latest()
            ->paginate(SystemSetting::get('pagination_per_page', 20));

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'actionFilter' => $request->string('action')->toString(),
        ]);
    }
}
