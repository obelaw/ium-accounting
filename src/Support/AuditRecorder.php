<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Models\AccountingAuditLog;
use Obelaw\Ium\Facades\Ium;

final class AuditRecorder
{
    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function record(
        AuditAction $action,
        Model $auditable,
        ?array $before = null,
        ?array $after = null,
        ?string $actorId = null,
        ?string $reason = null,
        ?string $originContext = null,
    ): ?AccountingAuditLog {
        if (! Config::get('ium.accounting.audit.enabled', true)) {
            return null;
        }

        return AccountingAuditLog::create([
            'actor_id' => $actorId ?? Ium::actor()->id,
            'action' => $action,
            'origin_context' => $originContext ?? 'AccountingDomain',
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'before_state' => $before,
            'after_state' => $after,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
