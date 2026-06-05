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
        ->and($createdLog['payload']['status'])->toBe(TaskStatus::Done->value);

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
        fn (SendTaskCompletedNotification $job): bool => $job->task->is($task)
            && $job->userId === $user->id
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

it('writes task completed notification JSON line', function (): void {
    $user = User::factory()->create();

    do {
        $task = Task::factory()->for($user)->create([
            'status' => TaskStatus::Done,
        ]);
    } while ($task->id % 5 === 0);

    $occurredAt = new DateTimeImmutable('2026-06-02T12:34:56+03:00');

    app()->call([(new SendTaskCompletedNotification($task, $user->id, $occurredAt)), 'handle']);

    $lines = array_values(array_filter(
        explode(PHP_EOL, trim(File::get(storage_path('logs/notifications.log')))),
    ));
    $payload = json_decode((string) end($lines), true, 512, JSON_THROW_ON_ERROR);

    expect($payload['taskId'])->toBe($task->id)
        ->and($payload['userId'])->toBe($user->id)
        ->and($payload['occurredAt'])->toBe('2026-06-02T12:34:56+03:00')
        ->and($payload['channel'])->toBe('email');
});

it('throttles task completed notification processing with sleep', function (): void {
    File::delete(storage_path('logs/notifications.log'));

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

        app()->call([(new SendTaskCompletedNotification($task, $user->id, $occurredAt)), 'handle']);
    } finally {
        Sleep::fake(false);
    }

    $lines = array_values(array_filter(
        explode(PHP_EOL, trim(File::get(storage_path('logs/notifications.log')))),
    ));

    expect($lines)->toHaveCount(1)
        ->and($sleepDurations)->toBe([200_000]);
});

it('does not write duplicate notification when message was already processed', function (): void {
    File::delete(storage_path('logs/notifications.log'));

    $user = User::factory()->create();

    do {
        $task = Task::factory()->for($user)->create([
            'status' => TaskStatus::Done,
        ]);
    } while ($task->id % 5 === 0);

    $occurredAt = new DateTimeImmutable('2026-06-02T12:34:56+03:00');

    app()->call([(new SendTaskCompletedNotification($task, $user->id, $occurredAt)), 'handle']);
    app()->call([(new SendTaskCompletedNotification($task, $user->id, $occurredAt)), 'handle']);

    $lines = array_values(array_filter(
        explode(PHP_EOL, trim(File::get(storage_path('logs/notifications.log')))),
    ));
    $payload = json_decode((string) end($lines), true, 512, JSON_THROW_ON_ERROR);

    expect($lines)->toHaveCount(1)
        ->and($payload['taskId'])->toBe($task->id)
        ->and(DB::table('processed_messages')
            ->where('message_key', "notification:{$task->id}")
            ->count())->toBe(1);
});

it('configures task completed notification retries and backoff', function (): void {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create([
        'status' => TaskStatus::Done,
    ]);
    $occurredAt = new DateTimeImmutable('2026-06-02T12:34:56+03:00');
    $job = new SendTaskCompletedNotification($task, $user->id, $occurredAt);

    expect($job->tries())->toBe(3)
        ->and($job->backoff())->toBe([5, 15, 30]);
});

it('fails task completed notification when task id is divisible by five', function (): void {
    $user = User::factory()->create();

    do {
        $task = Task::factory()->for($user)->create([
            'status' => TaskStatus::Done,
        ]);
    } while ($task->id % 5 !== 0);

    $occurredAt = new DateTimeImmutable('2026-06-02T12:34:56+03:00');

    expect($task->id % 5)->toBe(0);

    expect(fn () => app()->call([(new SendTaskCompletedNotification($task, $user->id, $occurredAt)), 'handle']))
        ->toThrow(RuntimeException::class, "Simulated notification failure for task {$task->id}.");
});

it('stores failed notification in failed jobs and writes failed log after three attempts', function (): void {
    config([
        'queue.default' => 'database',
        'queue.failed.driver' => 'database-uuids',
        'queue.failed.database' => config('database.default'),
        'queue.failed.table' => 'failed_jobs',
    ]);
    app()->forgetInstance('queue.failer');

    File::delete(storage_path('logs/failed.log'));

    $user = User::factory()->create();

    do {
        $task = Task::factory()->for($user)->create([
            'status' => TaskStatus::Done,
        ]);
    } while ($task->id % 5 !== 0);

    $occurredAt = new DateTimeImmutable('2026-06-02T12:34:56+03:00');

    expect($task->id % 5)->toBe(0);

    SendTaskCompletedNotification::dispatch($task, $user->id, $occurredAt);

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

    $failedJobs = DB::connection(config('queue.failed.database'))->table('failed_jobs');
    $failedJob = $failedJobs->first();
    $lines = array_values(array_filter(
        explode(PHP_EOL, trim(File::get(storage_path('logs/failed.log')))),
    ));
    $payload = json_decode((string) end($lines), true, 512, JSON_THROW_ON_ERROR);

    expect(DB::table('jobs')->count())->toBe(0)
        ->and($failedJobs->count())->toBe(1)
        ->and($failedJob->exception)->toContain("Simulated notification failure for task {$task->id}.")
        ->and($payload['taskId'])->toBe($task->id)
        ->and($payload['reason'])->toBe("Simulated notification failure for task {$task->id}.");
});
