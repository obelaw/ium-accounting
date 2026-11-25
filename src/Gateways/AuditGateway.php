<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Gateways;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Models\AccountingAuditLog;
use Obelaw\Ium\Accounting\Support\AuditRecorder;

final class AuditGateway
{
    public function __construct(private readonly AuditRecorder $recorder) {}

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
        return $this->recorder->record($action, $auditable, $before, $after, $actorId, $reason, $originContext);
    }

    /**
     * @return Collection<int, AccountingAuditLog>
     */
    public function historyFor(Model $auditable): Collection
    {
        return AccountingAuditLog::where('auditable_type', $auditable->getMorphClass())
            ->where('auditable_id', $auditable->getKey())
            ->orderByDesc('id')
            ->get();
    }
}
