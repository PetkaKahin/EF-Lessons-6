<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PingJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $message = 'ping processed: '.now()->toISOString();

        Log::info($message);
    }
}
