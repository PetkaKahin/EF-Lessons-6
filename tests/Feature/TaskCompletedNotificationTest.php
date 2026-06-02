<?php

declare(strict_types=1);

use App\Application\Tasks\Actions\UpdateTask;
use App\Domain\Task\Enums\TaskStatus;
use App\Jobs\SendTaskCompletedNotification;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('queues task completed notification job when task becomes done', function (): void {
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

    Queue::assertPushed(SendTaskCompletedNotification::class, 1);
    Queue::assertPushed(
        SendTaskCompletedNotification::class,
        fn (SendTaskCompletedNotification $job): bool => $job->task->is($task)
            && $job->userId === $user->id
    );
});

it('writes task completed notification JSON line', function (): void {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create([
        'status' => TaskStatus::Done,
    ]);
    $occurredAt = new DateTimeImmutable('2026-06-02T12:34:56+03:00');

    (new SendTaskCompletedNotification($task, $user->id, $occurredAt))->handle();

    $lines = array_values(array_filter(
        explode(PHP_EOL, trim(File::get(storage_path('logs/notifications.log')))),
    ));
    $payload = json_decode((string) end($lines), true, 512, JSON_THROW_ON_ERROR);

    expect($payload['taskId'])->toBe($task->id)
        ->and($payload['userId'])->toBe($user->id)
        ->and($payload['occurredAt'])->toBe('2026-06-02T12:34:56+03:00')
        ->and($payload['channel'])->toBe('email');
});
