<?php

declare(strict_types=1);

namespace App\Application\Messages\Actions;

use Illuminate\Support\Facades\DB;

class MarkMessageProcessed
{
    public function isProcessed(string $key): bool
    {
        return DB::table('processed_messages')
            ->where('message_key', $key)
            ->exists();
    }

    public function markAsProcessed(string $key): void
    {
        $now = now();

        DB::table('processed_messages')->insertOrIgnore([
            'message_key' => $key,
            'processed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
