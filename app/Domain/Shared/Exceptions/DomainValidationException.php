<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use DomainException;

abstract class DomainValidationException extends DomainException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        private readonly array $errors,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
