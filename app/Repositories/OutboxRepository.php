<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Application\Outbox\Contracts\OutboxRepositoryInterface;
use App\Domain\Outbox\Enums\OutboxMessageStatus;
use App\Domain\Outbox\OutboxMessage as DomainOutboxMessage;
use App\Models\OutboxMessage as OutboxMessageModel;
use Illuminate\Support\Collection;

class OutboxRepository implements OutboxRepositoryInterface
{
    public function create(DomainOutboxMessage $message): void
    {
        OutboxMessageModel::query()->create($message->toPersistenceArray());
    }

    /**
     * @return Collection<int, DomainOutboxMessage>
     */
    public function findNew(int $limit): Collection
    {
        return OutboxMessageModel::query()
            ->where('status', OutboxMessageStatus::New->value)
            ->oldest()
            ->limit($limit)
            ->get()
            ->map(fn (OutboxMessageModel $message): DomainOutboxMessage => $this->toDomain($message));
    }

    public function save(DomainOutboxMessage $message): void
    {
        OutboxMessageModel::query()
            ->whereKey($message->id)
            ->update($message->toPersistenceArray());
    }

    private function toDomain(OutboxMessageModel $message): DomainOutboxMessage
    {
        return new DomainOutboxMessage(
            id: (int) $message->id,
            type: $message->type,
            payload: $message->payload,
            status: $message->status,
        );
    }
}
