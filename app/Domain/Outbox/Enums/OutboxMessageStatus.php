<?php

declare(strict_types=1);

namespace App\Domain\Outbox\Enums;

enum OutboxMessageStatus: string
{
    case New = 'new';
    case Published = 'published';
}
