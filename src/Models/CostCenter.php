<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Obelaw\Ium\Accounting\Base\ModelBase;
use Obelaw\Ium\Accounting\Enums\DimensionType;

/**
 * Analytical Accounting Dimension / Cost Center.
 *
 * Automatically maps to table `ium_accounting_cost_centers`.
 */
class CostCenter extends ModelBase
{
    protected $table = 'cost_centers';

    protected $fillable = [
        'code',
        'name',
        'dimension_type',
        'parent_id',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'dimension_type' => DimensionType::class,
        'parent_id' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'cost_center_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, DimensionType $type): Builder
    {
        return $query->where('dimension_type', $type->value);
    }
}
