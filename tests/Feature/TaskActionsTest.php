<?php

declare(strict_types=1);

use App\Application\Tasks\Actions\UpdateTask;
use App\Domain\Outbox\Enums\OutboxMessageStatus;
use App\Domain\Outbox\Enums\OutboxMessageType;
use App\Domain\Task\Enums\TaskStatus;
use App\Models\OutboxMessage;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates task completed outbox message from update action', function (): void {
    $owner = User::factory()->create();
    $task = Task::factory()->for($owner)->create([
        'status' => TaskStatus::InProgress,
    ]);

    app(UpdateTask::class)->handle(
        $task,
        ['status' => TaskStatus::Done->value],
        $owner->id,
    );

    $outboxMessage = OutboxMessage::query()->firstOrFail();

    expect($outboxMessage->type)->toBe(OutboxMessageType::TaskCompleted)
        ->and($outboxMessage->status)->toBe(OutboxMessageStatus::New)
        ->and($outboxMessage->payload['task_id'])->toBe($task->id)
        ->and($outboxMessage->payload['completed_by_user_id'])->toBe($owner->id)
        ->and($outboxMessage->payload['status'])->toBe(TaskStatus::Done->value);

    expect(array_key_exists('previous_status', $outboxMessage->payload))->toBeFalse();
});
