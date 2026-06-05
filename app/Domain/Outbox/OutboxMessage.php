<?php

declare(strict_types=1);

namespace App\Domain\Outbox;

use App\Domain\Outbox\Enums\OutboxMessageStatus;
use App\Domain\Outbox\Enums\OutboxMessageType;

final class OutboxMessage
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly ?int $id,
        public readonly OutboxMessageType $type,
        public private(set) array $payload,
        public private(set) OutboxMessageStatus $status,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function new(OutboxMessageType $type, array $payload): self
    {
        return new self(
            id: null,
            type: $type,
            payload: $payload,
            status: OutboxMessageStatus::New,
        );
    }

    public function markPublished(): void
    {
        $this->status = OutboxMessageStatus::Published;
    }

    /**
     * @return array{type: string, payload: array<string, mixed>, status: string}
     */
    public function toPersistenceArray(): array
    {
        return [
            'type' => $this->type->value,
            'payload' => $this->payload,
            'status' => $this->status->value,
        ];
    }
}
