<?php

declare(strict_types=1);

namespace App\Application\Tasks\Actions;

use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Task as DomainTask;
use App\Models\Task;
use App\Repositories\TaskRepositoryInterface;
use DateTimeImmutable;

class CreateTask
{
    public function __construct(
        private TaskRepositoryInterface $tasks,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Task
    {
        $task = DomainTask::create(
            userId: (int) $data['user_id'],
            title: (string) $data['title'],
            createdAt: new DateTimeImmutable,
            description: $data['description'] ?? null,
            status: array_key_exists('status', $data)
                ? $this->statusFrom($data['status'])
                : TaskStatus::New,
        );

        return $this->tasks->create($task->toPersistenceArray());
    }

    private function statusFrom(mixed $status): TaskStatus
    {
        if ($status instanceof TaskStatus) {
            return $status;
        }

        return TaskStatus::from((string) $status);
    }
}
