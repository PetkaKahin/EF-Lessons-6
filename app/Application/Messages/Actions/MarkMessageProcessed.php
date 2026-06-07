<?php

declare(strict_types=1);

namespace App\Application\Messages\Actions;

use Illuminate\Support\Facades\DB;

class MarkMessageProcessed
{
    /**
     * Возвращает `true` если ключ был сохранён 1-й раз
     */
    public function tryMarkAsProcessed(string $key): bool
    {
        $now = now();

        return DB::table('processed_messages')->insertOrIgnore([
            'message_key' => $key,
            'processed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]) === 1;
    }

    public function forget(string $key): void
    {
        DB::table('processed_messages')
            ->where('message_key', $key)
            ->delete();
    }
}
