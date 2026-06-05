<?php

declare(strict_types=1);

namespace App\Domain\Outbox\Enums;

enum OutboxMessageType: string
{
    case TaskCompleted = 'TaskCompleted';
}
