<?php

declare(strict_types=1);

namespace App\Domain\Task\Exceptions;

use App\Domain\Shared\Exceptions\DomainValidationException;

final class InvalidTaskTitle extends DomainValidationException
{
    public function __construct(string $message)
    {
        parent::__construct([
            'title' => [$message],
        ], $message);
    }
}
