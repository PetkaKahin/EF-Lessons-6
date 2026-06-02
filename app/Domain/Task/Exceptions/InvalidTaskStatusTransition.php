<?php

declare(strict_types=1);

namespace App\Domain\Task\Exceptions;

use App\Domain\Shared\Exceptions\DomainValidationException;
use App\Domain\Task\Enums\TaskStatus;

final class InvalidTaskStatusTransition extends DomainValidationException
{
    public function __construct(
        public readonly TaskStatus $from,
        public readonly TaskStatus $to,
    ) {
        $message = "The task status cannot transition from {$from->value} to {$to->value}.";

        parent::__construct([
            'status' => [$message],
        ], $message);
    }
}
