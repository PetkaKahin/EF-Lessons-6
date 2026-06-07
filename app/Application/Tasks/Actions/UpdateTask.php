<?php

declare(strict_types=1);

namespace App\Application\Tasks\Actions;

use App\Application\Logs\Actions\AppendJsonLogLine;
use App\Application\Logs\Enums\LogType;
use App\Application\Outbox\Contracts\OutboxRepositoryInterface;
use App\Application\Tasks\Contracts\TaskRepositoryInterface;
use App\Domain\Outbox\Enums\OutboxMessageType;
use App\Domain\Outbox\OutboxMessage;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Task as DomainTask;
use App\Models\Task;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

class UpdateTask
{
    public function __construct(
        private TaskRepositoryInterface $tasks,
        private OutboxRepositoryInterface $outboxMessages,
        private AppendJsonLogLine $appendJsonLogLine,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Task $task, array $data, int $actorId): Task
    {
        $outboxLogPayload = null;

        $task = DB::transaction(function () use ($task, $data, $actorId, &$outboxLogPayload): Task {
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
                $messagePayload = [
                    'task_id' => (int) $task->id,
                    'user_id' => (int) $task->user_id,
                    'completed_by_user_id' => $actorId,
                    'status' => $domainTask->status->value,
                    'occurred_at' => (new DateTimeImmutable)->format(DATE_ATOM),
                ];

                $this->outboxMessages->create(OutboxMessage::new(
                    OutboxMessageType::TaskCompleted,
                    $messagePayload,
                ));

                $outboxLogPayload = [
                    'action' => 'created',
                    'type' => OutboxMessageType::TaskCompleted->value,
                    'payload' => $messagePayload,
                ];
            }

            return $task;
        });

        if ($outboxLogPayload !== null) {
            $this->appendJsonLogLine->handle(LogType::Outbox, $outboxLogPayload);
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
