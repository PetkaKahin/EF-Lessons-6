<?php

declare(strict_types=1);

namespace App\Application\Logs\Enums;

enum LogType: string
{
    case Notifications = 'notifications';
    case Failed = 'failed';

    public function filename(): string
    {
        return "{$this->value}.log";
    }
}
