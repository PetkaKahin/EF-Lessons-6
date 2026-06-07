<?php

declare(strict_types=1);

use App\Application\Outbox\Actions\PublishOutboxMessages;
use App\Application\Tasks\Actions\UpdateTask;
use App\Domain\Outbox\Enums\OutboxMessageStatus;
use App\Domain\Outbox\Enums\OutboxMessageType;
use App\Domain\Task\Enums\TaskStatus;
use App\Jobs\SendTaskCompletedNotification;
use App\Models\OutboxMessage;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

it('queues task completed notification job when task completed outbox message is published', function (): void {
    Queue::fake([SendTaskCompletedNotification::class]);
    File::delete(storage_path('logs/outbox.log'));

    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create([
        'status' => TaskStatus::InProgress,
    ]);

    app(UpdateTask::class)->handle(
        $task,
        ['status' => TaskStatus::Done->value],
        $user->id,
    );

    $lines = array_values(array_filter(
        explode(PHP_EOL, trim(File::get(storage_path('logs/outbox.log')))),
    ));
    $createdLog = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);

    expect($createdLog['action'])->toBe('created')
        ->and($createdLog['type'])->toBe(OutboxMessageType::TaskCompleted->value)
        ->and($createdLog['payload']['task_id'])->toBe($task->id)
        ->and($createdLog['payload']['status'])->toBe(TaskStatus::Done->value)
        ->and($createdLog['payload']['occurred_at'])->toBeString();

    Queue::assertNothingPushed();

    $published = app(PublishOutboxMessages::class)->handle();
    $lines = array_values(array_filter(
        explode(PHP_EOL, trim(File::get(storage_path('logs/outbox.log')))),
    ));
    $publishedLog = json_decode($lines[1], true, 512, JSON_THROW_ON_ERROR);

    expect($published)->toBe(1)
        ->and(OutboxMessage::query()->firstOrFail()->status)->toBe(OutboxMessageStatus::Published)
        ->and($lines)->toHaveCount(2)
        ->and($publishedLog['action'])->toBe('published')
        ->and($publishedLog['type'])->toBe(OutboxMessageType::TaskCompleted->value)
        ->and($publishedLog['payload']['task_id'])->toBe($task->id);

    Queue::assertPushed(SendTaskCompletedNotification::class, 1);
    Queue::assertPushed(
        SendTaskCompletedNotification::class,
        fn (SendTaskCompletedNotification $job): bool => $job->taskId === $task->id
            && $job->userId === $user->id
            && $job->status === TaskStatus::Done
    );
});

it('publishes task completed outbox message even when task was deleted later', function (): void {
    Queue::fake([SendTaskCompletedNotification::class]);

    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create([
        'status' => TaskStatus::InProgress,
    ]);

    app(UpdateTask::class)->handle(
        $task,
        ['status' => TaskStatus::Done->value],
        $user->id,
    );

    $task->delete();

    $published = app(PublishOutboxMessages::class)->handle();

    expect($published)->toBe(1)
        ->and(OutboxMessage::query()->firstOrFail()->status)->toBe(OutboxMessageStatus::Published);

    Queue::assertPushed(
        SendTaskCompletedNotification::class,
        fn (SendTaskCompletedNotification $job): bool => $job->taskId === $task->id
            && $job->userId === $user->id
            && $job->status === TaskStatus::Done
    );
});

it('keeps outbox message new when publishing fails', function (): void {
    File::delete(storage_path('logs/outbox.log'));

    OutboxMessage::query()->create([
        'type' => OutboxMessageType::TaskCompleted,
        'payload' => [
            'task_id' => 999999,
            'user_id' => 1,
            'completed_by_user_id' => 1,
            'status' => TaskStatus::Done->value,
            'occurred_at' => 'not-a-date',
        ],
        'status' => OutboxMessageStatus::New,
    ]);

    $published = app(PublishOutboxMessages::class)->handle();
    $lines = array_values(array_filter(
        explode(PHP_EOL, trim(File::get(storage_path('logs/outbox.log')))),
    ));
    $failedLog = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);

    expect($published)->toBe(0)
        ->and(OutboxMessage::query()->firstOrFail()->status)->toBe(OutboxMessageStatus::New)
        ->and($failedLog['action'])->toBe('publish_failed')
        ->and($failedLog['type'])->toBe(OutboxMessageType::TaskCompleted->value)
        ->and($failedLog['payload']['task_id'])->toBe(999999);
});

it('publishes outbox messages with artisan command', function (): void {
    Queue::fake([SendTaskCompletedNotification::class]);

    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create([
        'status' => TaskStatus::InProgress,
    ]);

    app(UpdateTask::class)->handle(
        $task,
        ['status' => TaskStatus::Done->value],
        $user->id,
    );

    $this->artisan('outbox:publish', ['--limit' => 1])
        ->expectsOutput('Published 1 outbox messages')
        ->assertSuccessful();

    Queue::assertPushed(SendTaskCompletedNotification::class, 1);
});

it('posts task completed webhook and logs attempt', function (): void {
    $url = 'https://example.test/webhooks/tasks/completed';
    $timeout = null;

    config([
        'services.notifications.webhook_url' => $url,
        'services.notifications.webhook_timeout' => 7,
    ]);

    Http::fake(function ($request, array $options) use (&$timeout) {
        $timeout = $options['timeout'];

        return Http::response(['ok' => true], 200);
    });
    Sleep::fake();

    $user = User::factory()->create();

    $task = Task::factory()->for($user)->create([
        'status' => TaskStatus::Done,
    ]);

    $occurredAt = new DateTimeImmutable('2026-06-02T12:34:56+03:00');

    try {
        app()->call([(new SendTaskCompletedNotification(
            (int) $task->id,
            (int) $user->id,
            TaskStatus::Done,
            $occurredAt,
        )), 'handle']);
    } finally {
        Sleep::fake(false);
    }

    $attempt = DB::table('webhook_attempts')->first();
    $payload = json_decode($attempt->payload, true, 512, JSON_THROW_ON_ERROR);

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === $url
        && $request->hasHeader('Idempotency-Key', (string) $task->id)
        && $request['taskId'] === $task->id
        && $request['status'] === TaskStatus::Done->value
        && $request['occurredAt'] === '2026-06-02T12:34:56+03:00');

    expect($timeout)->toBe(7)
        ->and($attempt->task_id)->toBe($task->id)
        ->and($attempt->idempotency_key)->toBe((string) $task->id)
        ->and($attempt->url)->toBe($url)
        ->and($attempt->response_status)->toBe(200)
        ->and($attempt->error)->toBeNull()
        ->and($payload['taskId'])->toBe($task->id)
        ->and($payload['status'])->toBe(TaskStatus::Done->value)
        ->and($payload['occurredAt'])->toBe('2026-06-02T12:34:56+03:00');
});

it('does not deliver duplicate webhook when notification was already processed', function (): void {
    $url = 'https://example.test/webhooks/tasks/completed';

    config(['services.notifications.webhook_url' => $url]);
    Http::fake([$url => Http::response(['ok' => true], 200)]);
    Sleep::fake();

    $user = User::factory()->create();

    do {
        $task = Task::factory()->for($user)->create([
            'status' => TaskStatus::Done,
        ]);
    } while ($task->id % 5 === 0);

    $occurredAt = new DateTimeImmutable('2026-06-02T12:34:56+03:00');
    $job = new SendTaskCompletedNotification((int) $task->id, (int) $user->id, TaskStatus::Done, $occurredAt);

    try {
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);
    } finally {
        Sleep::fake(false);
    }

    Http::assertSentCount(1);

    expect(DB::table('processed_messages')
        ->where('message_key', "notification:{$task->id}")
        ->count())->toBe(1)
        ->and(DB::table('webhook_attempts')->count())->toBe(1);
});

it('throttles task completed notification processing with sleep', function (): void {
    config(['services.notifications.webhook_url' => 'https://example.test/webhooks/tasks/completed']);
    Http::fake(['https://example.test/*' => Http::response(['ok' => true], 200)]);

    $sleepDurations = [];

    Sleep::fake();
    Sleep::whenFakingSleep(function ($duration) use (&$sleepDurations): void {
        $sleepDurations[] = (int) $duration->totalMicroseconds;
    });

    try {
        $user = User::factory()->create();

        do {
            $task = Task::factory()->for($user)->create([
                'status' => TaskStatus::Done,
            ]);
        } while ($task->id % 5 === 0);

        $occurredAt = new DateTimeImmutable('2026-06-02T12:34:56+03:00');

        app()->call([(new SendTaskCompletedNotification(
            (int) $task->id,
            (int) $user->id,
            TaskStatus::Done,
            $occurredAt,
        )), 'handle']);
    } finally {
        Sleep::fake(false);
    }

    expect(DB::table('webhook_attempts')->count())->toBe(1)
        ->and($sleepDurations)->toBe([200_000]);
});

it('configures task completed notification retries and backoff', function (): void {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create([
        'status' => TaskStatus::Done,
    ]);
    $occurredAt = new DateTimeImmutable('2026-06-02T12:34:56+03:00');
    $job = new SendTaskCompletedNotification((int) $task->id, (int) $user->id, TaskStatus::Done, $occurredAt);

    expect($job->tries())->toBe(3)
        ->and($job->backoff())->toBe([5, 15, 30]);
});

it('fails task completed notification when task id is divisible by five and logs the attempt', function (): void {
    $url = 'https://example.test/webhooks/tasks/completed';

    config(['services.notifications.webhook_url' => $url]);
    Http::fake();
    Sleep::fake();

    $user = User::factory()->create();

    do {
        $task = Task::factory()->for($user)->create([
            'status' => TaskStatus::Done,
        ]);
    } while ($task->id % 5 !== 0);

    $occurredAt = new DateTimeImmutable('2026-06-02T12:34:56+03:00');

    try {
        expect(fn () => app()->call([(new SendTaskCompletedNotification(
            (int) $task->id,
            (int) $user->id,
            TaskStatus::Done,
            $occurredAt,
        )), 'handle']))
            ->toThrow(RuntimeException::class, "Simulated notification failure for task {$task->id}");
    } finally {
        Sleep::fake(false);
    }

    $attempt = DB::table('webhook_attempts')->first();

    expect(DB::table('webhook_attempts')->count())->toBe(1)
        ->and($attempt->task_id)->toBe($task->id)
        ->and($attempt->error)->toBe("Simulated notification failure for task {$task->id}")
        ->and($attempt->response_status)->toBeNull();

    Http::assertNothingSent();
});

it('fails task completed webhook when endpoint returns server error', function (): void {
    $url = 'https://example.test/webhooks/tasks/completed';

    config(['services.notifications.webhook_url' => $url]);
    Http::fake([$url => Http::response(['error' => true], 500)]);
    Sleep::fake();

    $user = User::factory()->create();

    $task = Task::factory()->for($user)->create([
        'status' => TaskStatus::Done,
    ]);

    $occurredAt = new DateTimeImmutable('2026-06-02T12:34:56+03:00');

    try {
        expect(fn () => app()->call([(new SendTaskCompletedNotification(
            (int) $task->id,
            (int) $user->id,
            TaskStatus::Done,
            $occurredAt,
        )), 'handle']))
            ->toThrow(RuntimeException::class, 'Webhook delivery failed with status 500');
    } finally {
        Sleep::fake(false);
    }

    $attempt = DB::table('webhook_attempts')->first();

    expect($attempt->task_id)->toBe($task->id)
        ->and($attempt->response_status)->toBe(500)
        ->and($attempt->error)->toBe('Webhook delivery failed with status 500');
});

it('stores failed notification in failed jobs and writes failed log after three attempts', function (): void {
    $url = 'https://example.test/webhooks/tasks/completed';

    config([
        'queue.default' => 'database',
        'queue.failed.driver' => 'database-uuids',
        'queue.failed.database' => config('database.default'),
        'queue.failed.table' => 'failed_jobs',
        'services.notifications.webhook_url' => $url,
    ]);
    app()->forgetInstance('queue.failer');

    Http::fake([$url => Http::response(['error' => true], 500)]);
    Sleep::fake();
    File::delete(storage_path('logs/failed.log'));

    $user = User::factory()->create();

    $task = Task::factory()->for($user)->create([
        'status' => TaskStatus::Done,
    ]);

    $occurredAt = new DateTimeImmutable('2026-06-02T12:34:56+03:00');

    SendTaskCompletedNotification::dispatch((int) $task->id, (int) $user->id, TaskStatus::Done, $occurredAt);

    try {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->artisan('queue:work', [
                'connection' => 'database',
                '--once' => true,
                '--queue' => 'default',
                '--sleep' => 0,
                '--tries' => 0,
            ])->assertSuccessful();

            DB::table('jobs')->update([
                'available_at' => 0,
                'reserved_at' => null,
            ]);
        }
    } finally {
        Sleep::fake(false);
    }

    $failedJobs = DB::connection(config('queue.failed.database'))->table('failed_jobs');
    $failedJob = $failedJobs->first();
    $lines = array_values(array_filter(
        explode(PHP_EOL, trim(File::get(storage_path('logs/failed.log')))),
    ));
    $payload = json_decode((string) end($lines), true, 512, JSON_THROW_ON_ERROR);

    expect(DB::table('jobs')->count())->toBe(0)
        ->and($failedJobs->count())->toBe(1)
        ->and($failedJob->exception)->toContain('Webhook delivery failed with status 500')
        ->and($payload['taskId'])->toBe($task->id)
        ->and($payload['reason'])->toBe('Webhook delivery failed with status 500')
        ->and(DB::table('webhook_attempts')->count())->toBe(3);
});
