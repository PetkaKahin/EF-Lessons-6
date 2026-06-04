<?php

declare(strict_types=1);

namespace App\Application\Logs\Actions;

use App\Application\Logs\Enums\LogType;
use Illuminate\Support\Facades\File;

class AppendJsonLogLine
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(LogType $type, array $payload): void
    {
        File::ensureDirectoryExists(storage_path('logs'));
        File::append(
            storage_path('logs/'.$type->filename()),
            json_encode($payload, JSON_THROW_ON_ERROR).PHP_EOL,
        );
    }
}
