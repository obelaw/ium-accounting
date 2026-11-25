<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Obelaw\Ium\Accounting\Base\ModelBase;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Exceptions\ImmutableJournalEntryException;

class AccountingAuditLog extends ModelBase
{
    public $timestamps = false;

    protected $table = 'audit_logs';

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

    protected static function booted(): void
    {
        parent::booted();

        static::updating(static function (self $log): void {
            throw ImmutableJournalEntryException::cannotMutate('AuditLog#' . $log->id, 'update');
        });

        static::deleting(static function (self $log): void {
            throw ImmutableJournalEntryException::cannotMutate('AuditLog#' . $log->id, 'delete');
        });
    }
}
