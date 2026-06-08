<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Messages\Actions\MarkMessageProcessed;
use App\Application\Notifications\Actions\SendTaskCompletedWebhook;
use App\Domain\Task\Enums\TaskStatus;
use App\Events\TaskCompletedNotificationFailed;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendTaskCompletedNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $taskId,
        public int $userId,
        public TaskStatus $status,
        public DateTimeInterface $occurredAt,
    ) {}

    public function tries(): int
    {
        return 3;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(
        SendTaskCompletedWebhook $sendTaskCompletedWebhook,
        MarkMessageProcessed $processedMessages,
    ): void {
        $idempotencyKey = "notification:{$this->taskId}";

        if ($processedMessages->isProcessed($idempotencyKey)) {
            return;
        }

        $sendTaskCompletedWebhook->send($this->taskId, $this->status, $this->occurredAt);

        $processedMessages->markAsProcessed($idempotencyKey);
    }

    public function failed(?Throwable $exception): void
    {
        TaskCompletedNotificationFailed::dispatch(
            $this->taskId,
            $exception?->getMessage() ?: 'Unknown queue failure',
        );
    }
}
