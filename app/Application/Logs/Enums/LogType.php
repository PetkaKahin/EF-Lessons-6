<?php

declare(strict_types=1);

namespace App\Application\Logs\Enums;

enum LogType: string
{
    case Notifications = 'notifications';
    case Failed = 'failed';
    case Outbox = 'outbox';

    public function filename(): string
    {
        return "{$this->value}.log";
    }
}
