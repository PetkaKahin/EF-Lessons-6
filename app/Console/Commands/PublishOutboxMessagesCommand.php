<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Outbox\Actions\PublishOutboxMessages;
use Illuminate\Console\Command;

class PublishOutboxMessagesCommand extends Command
{
    protected $signature = 'outbox:publish {--limit=100}';

    protected $description = 'Publish new outbox messages to the queue';

    public function handle(PublishOutboxMessages $publishOutboxMessages): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $published = $publishOutboxMessages->handle($limit);

        $this->info("Published {$published} outbox messages");

        return self::SUCCESS;
    }
}
