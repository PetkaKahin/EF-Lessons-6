<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Logs\Actions\AppendJsonLogLine;
use App\Application\Logs\Enums\LogType;
use App\Application\Messages\Actions\MarkMessageProcessed;
use App\Events\TaskCompletedNotificationFailed;
use App\Models\Task;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Sleep;
use RuntimeException;
use Throwable;

class SendTaskCompletedNotification implements ShouldQueue
{
    use Queueable;

    private const NOTIFICATION_RATE_LIMIT_PER_SECOND = 5;

    public function __construct(
        public Task $task,
        public int $userId,
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
        MarkMessageProcessed $markMessageProcessed,
        AppendJsonLogLine $appendJsonLogLine,
    ): void {
        $taskId = (int) $this->task->id;

        if ($taskId % 5 === 0) {
            throw new RuntimeException("Simulated notification failure for task {$taskId}.");
        }

        $idempotencyKey = "notification:{$taskId}";

        if (! $markMessageProcessed->handle($idempotencyKey)) {
            return;
        }

        Sleep::usleep((int) ceil(1_000_000 / self::NOTIFICATION_RATE_LIMIT_PER_SECOND));

        $payload = [
            'taskId' => $taskId,
            'userId' => $this->userId,
            'occurredAt' => $this->occurredAt->format(DATE_ATOM),
            'channel' => 'email',
        ];

        try {
            $appendJsonLogLine->handle(LogType::Notifications, $payload);
        } catch (Throwable $exception) {
            $markMessageProcessed->forget($idempotencyKey);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        TaskCompletedNotificationFailed::dispatch(
            $this->task,
            $exception?->getMessage() ?: 'Unknown queue failure.',
        );
    }
}
