<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Outbox\Enums\OutboxMessageStatus;
use App\Domain\Outbox\Enums\OutboxMessageType;
use Illuminate\Database\Eloquent\Model;

class OutboxMessage extends Model
{
    public $timestamps = false;

    protected $table = 'outbox_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'payload',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OutboxMessageType::class,
            'payload' => 'array',
            'status' => OutboxMessageStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
