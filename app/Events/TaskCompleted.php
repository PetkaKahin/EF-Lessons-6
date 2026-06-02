<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\Task\Enums\TaskStatus;
use App\Models\Task;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCompleted
{
    use Dispatchable, SerializesModels;

    public DateTimeInterface $occurredAt;

    public function __construct(
        public Task $task,
        public ?int $completedByUserId,
        public TaskStatus $previousStatus,
        ?DateTimeInterface $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable;
    }
}
