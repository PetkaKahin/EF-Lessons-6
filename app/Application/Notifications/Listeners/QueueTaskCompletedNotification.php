<?php

declare(strict_types=1);

namespace App\Application\Notifications\Listeners;

use App\Events\TaskCompleted;
use App\Jobs\SendTaskCompletedNotification;

class QueueTaskCompletedNotification
{
    public function handle(TaskCompleted $event): void
    {
        SendTaskCompletedNotification::dispatch(
            $event->taskId,
            $event->userId,
            $event->status,
            $event->occurredAt,
        );
    }
}
