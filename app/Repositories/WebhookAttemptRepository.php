<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Application\Notifications\Contracts\WebhookAttemptRepositoryInterface;
use Illuminate\Support\Facades\DB;

class WebhookAttemptRepository implements WebhookAttemptRepositoryInterface
{
    public function create(array $attempt): void
    {
        $now = now();

        DB::table('webhook_attempts')->insert([
            'task_id' => $attempt['task_id'],
            'idempotency_key' => $attempt['idempotency_key'],
            'url' => $attempt['url'],
            'payload' => json_encode($attempt['payload'], JSON_THROW_ON_ERROR),
            'response_status' => $attempt['response_status'],
            'error' => $attempt['error'],
            'attempted_at' => $attempt['attempted_at'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
