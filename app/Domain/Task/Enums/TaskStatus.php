<?php

declare(strict_types=1);

namespace App\Domain\Task\Enums;

enum TaskStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function next(): ?self
    {
        return match ($this) {
            self::New => self::InProgress,
            self::InProgress => self::Done,
            self::Done => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
