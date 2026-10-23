<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * A thin, explicit wrapper around audit_logs writes. Call sites are added
 * deliberately at the specific actions CLAUDE.md calls out (auth, user,
 * product, order, payment, settings changes) rather than via a generic
 * model-observer that would audit everything indiscriminately.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function log(string $action, ?Model $subject = null, array $old = [], array $new = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
