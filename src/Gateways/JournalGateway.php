<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Gateways;

use Illuminate\Database\Eloquent\Collection;
use Obelaw\Ium\Accounting\Actions\Journal\CreateJournalAction;
use Obelaw\Ium\Accounting\Data\CreateJournalData;
use Obelaw\Ium\Accounting\Enums\JournalType;
use Obelaw\Ium\Accounting\Models\Journal;

final class JournalGateway
{
    public function __construct(private readonly CreateJournalAction $createAction) {}

    public function create(CreateJournalData $data): Journal
    {
        return $this->createAction->execute($data);
    }

    public function find(int $id): ?Journal
    {
        return Journal::find($id);
    }

    public function findByCode(string $code): ?Journal
    {
        return Journal::where('code', strtoupper(trim($code)))->first();
    }

    /**
     * @return Collection<int, Journal>
     */
    public function all(bool $onlyActive = true): Collection
    {
        $query = Journal::query();
        if ($onlyActive) {
            $query->active();
        }

        return $query->orderBy('code')->get();
    }

    public function seedDefaults(): void
    {
        $defaults = [
            ['GJ', 'General Journal', JournalType::GENERAL, 'GJ'],
            ['SJ', 'Sales Journal', JournalType::SALES, 'SJ'],
            ['PJ', 'Purchase Journal', JournalType::PURCHASE, 'PJ'],
            ['BNK', 'Bank Journal', JournalType::BANK, 'BNK'],
            ['CSH', 'Cash Journal', JournalType::CASH, 'CSH'],
            ['FA', 'Fixed Assets Journal', JournalType::FIXED_ASSET, 'FA'],
            ['FX', 'Foreign Exchange Journal', JournalType::FX, 'FX'],
        ];

        foreach ($defaults as [$code, $name, $type, $prefix]) {
            if (! Journal::where('code', $code)->exists()) {
                $this->create(new CreateJournalData($code, $name, $type, $prefix));
            }
        }
    }
}
