<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Task;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;

class SendTaskCompletedNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Task $task,
        public int $userId,
        public DateTimeInterface $occurredAt,
    ) {}

    public function handle(): void
    {
        $payload = [
            'taskId' => (int) $this->task->id,
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
}
