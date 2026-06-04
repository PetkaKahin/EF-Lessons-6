<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Task;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class SendTaskCompletedNotification implements ShouldQueue
{
    use Queueable;

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

    public function handle(): void
    {
        $taskId = (int) $this->task->id;

        if ($taskId % 5 === 0) {
            throw new RuntimeException("Simulated notification failure for task {$taskId}.");
        }

        $payload = [
            'taskId' => $taskId,
            'userId' => $this->userId,
            'occurredAt' => $this->occurredAt->format(DATE_ATOM),
            'channel' => 'email',
        ];

        File::ensureDirectoryExists(storage_path('logs'));
        File::append(
            storage_path('logs/notifications.log'),
            json_encode($payload, JSON_THROW_ON_ERROR).PHP_EOL,
        );
    }

    public function failed(?Throwable $exception): void
    {
        $payload = [
            'taskId' => (int) $this->task->id,
            'reason' => $exception?->getMessage() ?: 'Unknown queue failure.',
        ];

        File::ensureDirectoryExists(storage_path('logs'));
        File::append(
            storage_path('logs/failed.log'),
            json_encode($payload, JSON_THROW_ON_ERROR).PHP_EOL,
        );
    }
}
