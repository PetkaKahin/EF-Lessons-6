<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\PingJob;
use Illuminate\Console\Command;

class ProducePingJobs extends Command
{
    protected $signature = 'ping:produce';

    protected $description = 'Dispatch 10 ping jobs to the queue';

    public function handle(): int
    {
        for ($i = 0; $i < 10; $i++) {
            PingJob::dispatch();
        }

        $this->info('Dispatched 10 ping jobs');

        return self::SUCCESS;
    }
}
