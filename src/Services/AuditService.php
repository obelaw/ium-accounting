<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Models\AccountingAuditLog;

class AuditService
{
    /**
     * Record an immutable audit log entry.
     */
    public function record(
        AuditAction|string $action,
        Model $auditable,
        ?array $before = null,
        ?array $after = null,
        ?string $actorId = null,
        ?string $reason = null,
        ?string $originContext = null
    ): ?AccountingAuditLog {
        if (!Config::get('ium.accounting.audit.enabled', true)) {
            return null;
        }

        $auditAction = $action instanceof AuditAction ? $action : AuditAction::from((string) $action);

        return AccountingAuditLog::create([
            'actor_id' => $actorId ?? 'system',
            'action' => $auditAction,
            'origin_context' => $originContext ?? 'AccountingDomain',
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'before_state' => $before,
            'after_state' => $after,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /**
     * Retrieve audit history for a specific model.
     *
     * @return Collection<int, AccountingAuditLog>
     */
    public function historyFor(Model $auditable): Collection
    {
        return AccountingAuditLog::where('auditable_type', $auditable->getMorphClass())
            ->where('auditable_id', $auditable->getKey())
            ->orderBy('id', 'desc')
            ->get();
    }
}
