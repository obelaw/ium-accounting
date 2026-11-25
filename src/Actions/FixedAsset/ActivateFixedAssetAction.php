<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\FixedAsset;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\FixedAssetStatus;
use Obelaw\Ium\Accounting\Models\FixedAsset;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * @extends Action<int, FixedAsset>
 */
final class ActivateFixedAssetAction extends Action
{
    protected ?string $ability = 'accounting.fixed_asset.activate';

    public function __construct(
        private readonly GenerateDepreciationScheduleAction $generateSchedule,
        private readonly AuditRecorder $audit,
    ) {}

    protected function handle(mixed $input, Actor $actor): FixedAsset
    {
        $asset = FixedAsset::lockForUpdate()->findOrFail((int) $input);

        if ($asset->status !== FixedAssetStatus::DRAFT) {
            throw new InvalidArgumentException("Fixed asset [{$asset->asset_code}] can only be activated from DRAFT status.");
        }

        $before = $asset->toArray();
        $asset->update(['status' => FixedAssetStatus::ACTIVE]);
        $this->generateSchedule->execute($asset->id);

        $fresh = $asset->fresh(['schedules']);

        $this->audit->record(
            action: AuditAction::ASSET_ACTIVATED,
            auditable: $fresh,
            before: $before,
            after: $fresh->toArray(),
            actorId: $actor->id,
            originContext: self::class,
        );

        return $fresh;
    }
}
