<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class TaskCompletedNotificationFailed
{
    use Dispatchable;

    public function __construct(
        public int $taskId,
        public string $reason,
    ) {}
}
