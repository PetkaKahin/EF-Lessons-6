<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Outbox\Actions\PublishOutboxMessages as PublishOutboxMessagesAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishOutboxMessages implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $limit = 100,
    ) {}

    public function handle(PublishOutboxMessagesAction $publishOutboxMessages): int
    {
        return $publishOutboxMessages->handle($this->limit);
    }
}
