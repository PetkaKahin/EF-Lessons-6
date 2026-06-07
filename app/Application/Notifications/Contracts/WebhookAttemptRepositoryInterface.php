<?php

declare(strict_types=1);

namespace App\Application\Notifications\Contracts;

interface WebhookAttemptRepositoryInterface
{
    /**
     * @param  array{
     *     task_id: int,
     *     idempotency_key: string,
     *     url: string,
     *     payload: array<string, mixed>,
     *     response_status: int|null,
     *     error: string|null,
     *     attempted_at: \DateTimeInterface,
     * }  $attempt
     */
    public function create(array $attempt): void;
}
