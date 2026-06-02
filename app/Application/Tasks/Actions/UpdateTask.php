<?php

declare(strict_types=1);

namespace App\Application\Tasks\Actions;

use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Task as DomainTask;
use App\Events\TaskCompleted;
use App\Models\Task;
use App\Repositories\TaskRepositoryInterface;
use DateTimeImmutable;

class UpdateTask
{
    public function __construct(
        private TaskRepositoryInterface $tasks,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Task $task, array $data, int $actorId): Task
    {
        $previousStatus = $task->status;
        $domainTask = new DomainTask(
            id: (int) $task->id,
            userId: (int) $task->user_id,
            title: (string) $task->title,
            description: $task->description,
            status: $previousStatus,
            createdAt: $task->created_at?->toDateTimeImmutable() ?? new DateTimeImmutable,
            updatedAt: $task->updated_at?->toDateTimeImmutable(),
        );

        if (array_key_exists('title', $data)) {
            $domainTask->rename((string) $data['title']);
        }

        if (array_key_exists('description', $data)) {
            $domainTask->changeDescription($data['description']);
        }

        if (array_key_exists('status', $data)) {
            $domainTask->changeStatus($this->statusFrom($data['status']));
        }

        $task = $this->tasks->update($task, $domainTask->toPersistenceArray());

        if ($previousStatus !== TaskStatus::Done && $domainTask->status === TaskStatus::Done) {
            TaskCompleted::dispatch($task, $actorId, $previousStatus);
        }

        return $task;
    }

    private function statusFrom(mixed $status): TaskStatus
    {
        if ($status instanceof TaskStatus) {
            return $status;
        }

        return TaskStatus::from((string) $status);
    }
}
