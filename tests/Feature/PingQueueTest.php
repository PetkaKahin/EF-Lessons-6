<?php

declare(strict_types=1);

use App\Jobs\PingJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

it('dispatches ten ping jobs', function (): void {
    Queue::fake();

    $this->artisan('ping:produce')
        ->expectsOutput('Dispatched 10 ping jobs')
        ->assertSuccessful();

    Queue::assertPushed(PingJob::class, 10);
});

it('writes log line with timestamp when ping job is handled', function (): void {
    Log::spy();

    (new PingJob)->handle();

    Log::shouldHaveReceived('info')
        ->once()
        ->withArgs(fn (string $message): bool => preg_match(
            '/^ping processed: \d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $message,
        ) === 1);
});
