<?php

declare(strict_types=1);

namespace App\Application\Notifications\Actions;

use App\Application\Logs\Actions\AppendJsonLogLine;
use App\Application\Logs\Enums\LogType;
use App\Events\TaskCompletedNotificationFailed;

readonly class LogFailedTaskCompletedNotification
{
    public function __construct(
        private AppendJsonLogLine $appendJsonLogLine,
    ) {}

    public function handle(TaskCompletedNotificationFailed $event): void
    {
        $this->appendJsonLogLine->handle(LogType::Failed, [
            'taskId' => $event->taskId,
            'reason' => $event->reason,
        ]);
    }
}
