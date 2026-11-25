<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Gateways;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Obelaw\Ium\Accounting\Actions\FixedAsset\ActivateFixedAssetAction;
use Obelaw\Ium\Accounting\Actions\FixedAsset\DisposeFixedAssetAction;
use Obelaw\Ium\Accounting\Actions\FixedAsset\GenerateDepreciationScheduleAction;
use Obelaw\Ium\Accounting\Actions\FixedAsset\ProcessDepreciationLineAction;
use Obelaw\Ium\Accounting\Actions\FixedAsset\RegisterFixedAssetAction;
use Obelaw\Ium\Accounting\Data\CreateFixedAssetData;
use Obelaw\Ium\Accounting\Data\DisposeFixedAssetData;
use Obelaw\Ium\Accounting\Enums\DepreciationScheduleStatus;
use Obelaw\Ium\Accounting\Enums\FixedAssetStatus;
use Obelaw\Ium\Accounting\Exceptions\FiscalPeriodClosedException;
use Obelaw\Ium\Accounting\Models\DepreciationSchedule;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;
use Obelaw\Ium\Accounting\Models\FixedAsset;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Facades\Ium;

final class FixedAssetGateway
{
    public function __construct(
        private readonly RegisterFixedAssetAction $registerAction,
        private readonly ActivateFixedAssetAction $activateAction,
        private readonly GenerateDepreciationScheduleAction $generateScheduleAction,
        private readonly ProcessDepreciationLineAction $processLineAction,
        private readonly DisposeFixedAssetAction $disposeAction,
    ) {}

    public function register(CreateFixedAssetData $data): FixedAsset
    {
        return $this->registerAction->execute($data);
    }

    public function activate(int $assetId): FixedAsset
    {
        return $this->activateAction->execute($assetId);
    }

    /**
     * @return Collection<int, DepreciationSchedule>
     */
    public function generateSchedule(int $assetId): Collection
    {
        return $this->generateScheduleAction->execute($assetId);
    }

    public function processScheduleLine(int $scheduleId, ?string $makerId = null, ?string $checkerId = null, bool $autoPost = true): JournalEntry
    {
        return $this->processLineAction->execute([
            'schedule_id' => $scheduleId,
            'maker_id' => $makerId,
            'checker_id' => $checkerId,
            'auto_post' => $autoPost,
        ]);
    }

    /**
     * @return Collection<int, JournalEntry>
     */
    public function runDepreciationForPeriod(int $fiscalPeriodId, ?string $makerId = null, ?string $checkerId = null, bool $autoPost = true): Collection
    {
        return Ium::transaction(function () use ($fiscalPeriodId, $makerId, $checkerId, $autoPost): Collection {
            $period = FiscalPeriod::findOrFail($fiscalPeriodId);
            if (! $period->status->allowsPosting(false)) {
                throw FiscalPeriodClosedException::forPeriod($period->name, $period->status->value);
            }

            $schedules = DepreciationSchedule::query()
                ->where('status', DepreciationScheduleStatus::PENDING->value)
                ->whereDate('schedule_date', '>=', Carbon::parse($period->start_date)->format('Y-m-d'))
                ->whereDate('schedule_date', '<=', Carbon::parse($period->end_date)->format('Y-m-d'))
                ->whereHas('fixedAsset', static fn ($q) => $q->where('status', FixedAssetStatus::ACTIVE->value))
                ->orderBy('schedule_date')
                ->get();

            $entries = new Collection();
            foreach ($schedules as $schedule) {
                $entries->push($this->processScheduleLine($schedule->id, $makerId, $checkerId, $autoPost));
            }

            return $entries;
        });
    }

    public function dispose(DisposeFixedAssetData $data): FixedAsset
    {
        return $this->disposeAction->execute($data);
    }

    public function find(int $assetId): ?FixedAsset
    {
        return FixedAsset::with('schedules')->find($assetId);
    }

    public function findByCode(string $assetCode): ?FixedAsset
    {
        return FixedAsset::with('schedules')->where('asset_code', $assetCode)->first();
    }

    /**
     * @return Collection<int, FixedAsset>
     */
    public function list(?FixedAssetStatus $status = null): Collection
    {
        $query = FixedAsset::query()->with('schedules');
        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return $query->orderBy('asset_code')->get();
    }
}
