<?php

declare(strict_types=1);

namespace App\Application\Outbox\Contracts;

use App\Domain\Outbox\OutboxMessage as DomainOutboxMessage;
use Illuminate\Support\Collection;

interface OutboxRepositoryInterface
{
    public function create(DomainOutboxMessage $message): void;

    /**
     * @return Collection<int, DomainOutboxMessage>
     */
    public function findNew(int $limit): Collection;

    public function save(DomainOutboxMessage $message): void;
}
