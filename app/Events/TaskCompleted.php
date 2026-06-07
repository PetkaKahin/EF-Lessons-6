<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\Task\Enums\TaskStatus;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;

class TaskCompleted
{
    use Dispatchable;

    public DateTimeInterface $occurredAt;

    public function __construct(
        public int $taskId,
        public int $userId,
        public int $completedByUserId,
        public TaskStatus $status,
        public TaskStatus $previousStatus,
        ?DateTimeInterface $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable;
    }
}
