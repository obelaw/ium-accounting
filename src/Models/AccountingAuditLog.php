<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Obelaw\Ium\Accounting\Base\ModelBase;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Exceptions\ImmutableJournalEntryException;

/**
 * Immutable Forensic Accounting Audit Trail Log.
 *
 * Automatically maps to table `ium_accounting_audit_logs`.
 */
class AccountingAuditLog extends ModelBase
{
    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'action',
        'origin_context',
        'auditable_type',
        'auditable_id',
        'before_state',
        'after_state',
        'reason',
        'created_at',
    ];

    protected $casts = [
        'action' => AuditAction::class,
        'auditable_id' => 'integer',
        'before_state' => 'array',
        'after_state' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Audit logs are strictly append-only.
     */
    protected static function booted(): void
    {
        parent::booted();

        static::updating(function (self $log): void {
            throw new ImmutableJournalEntryException('Audit log entries are append-only and cannot be updated.');
        });

        static::deleting(function (self $log): void {
            throw new ImmutableJournalEntryException('Audit log entries are append-only and cannot be deleted.');
        });
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
