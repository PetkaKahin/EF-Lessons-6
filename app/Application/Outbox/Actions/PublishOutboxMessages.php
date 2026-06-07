<?php

declare(strict_types=1);

namespace App\Application\Outbox\Actions;

use App\Application\Logs\Actions\AppendJsonLogLine;
use App\Application\Logs\Enums\LogType;
use App\Application\Outbox\Contracts\OutboxRepositoryInterface;
use App\Domain\Outbox\Enums\OutboxMessageType;
use App\Domain\Outbox\OutboxMessage;
use App\Domain\Task\Enums\TaskStatus;
use App\Events\TaskCompleted;
use DateTimeImmutable;
use Throwable;

class PublishOutboxMessages
{
    public function __construct(
        private OutboxRepositoryInterface $outboxMessages,
        private AppendJsonLogLine $appendJsonLogLine,
    ) {}

    public function handle(int $limit = 100): int
    {
        $published = 0;

        foreach ($this->outboxMessages->findNew($limit) as $outboxMessage) {
            try {
                $this->publish($outboxMessage);
                $outboxMessage->markPublished();
                $this->outboxMessages->save($outboxMessage);
                $this->appendJsonLogLine->handle(LogType::Outbox, [
                    'action' => 'published',
                    'message_id' => $outboxMessage->id,
                    'type' => $outboxMessage->type->value,
                    'payload' => $outboxMessage->payload,
                ]);
                $published++;
            } catch (Throwable $exception) {
                $this->appendJsonLogLine->handle(LogType::Outbox, [
                    'action' => 'publish_failed',
                    'message_id' => $outboxMessage->id,
                    'type' => $outboxMessage->type->value,
                    'payload' => $outboxMessage->payload,
                    'reason' => $exception->getMessage(),
                ]);
                report($exception);
            }
        }

        return $published;
    }

    private function publish(OutboxMessage $outboxMessage): void
    {
        match ($outboxMessage->type) {
            OutboxMessageType::TaskCompleted => $this->publishTaskCompleted($outboxMessage),
        };
    }

    private function publishTaskCompleted(OutboxMessage $outboxMessage): void
    {
        $payload = $outboxMessage->payload;

        TaskCompleted::dispatch(
            (int) $payload['task_id'],
            (int) $payload['user_id'],
            (int) $payload['completed_by_user_id'],
            TaskStatus::from((string) $payload['status']),
            TaskStatus::InProgress,
            new DateTimeImmutable((string) $payload['occurred_at']),
        );
    }
}
