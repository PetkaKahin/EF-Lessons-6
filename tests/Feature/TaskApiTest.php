<?php

declare(strict_types=1);

use App\Domain\Outbox\Enums\OutboxMessageStatus;
use App\Domain\Outbox\Enums\OutboxMessageType;
use App\Domain\Task\Enums\TaskStatus;
use App\Models\OutboxMessage;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a task', function (): void {
    $user = User::factory()->create();

    $response = $this->postJson('/api/tasks', [
        'user_id' => $user->id,
        'title' => 'Prepare events lesson',
        'description' => null,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.user_id', $user->id)
        ->assertJsonPath('data.title', 'Prepare events lesson')
        ->assertJsonPath('data.description', null)
        ->assertJsonPath('data.status', TaskStatus::New->value)
        ->assertJsonStructure([
            'data' => [
                'id',
                'user_id',
                'title',
                'description',
                'status',
                'created_at',
                'updated_at',
            ],
        ]);

    $this->assertDatabaseHas('tasks', [
        'user_id' => $user->id,
        'title' => 'Prepare events lesson',
        'status' => TaskStatus::New->value,
    ]);
});

it('can list tasks with user and status filters', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    Task::factory()->for($owner)->create([
        'title' => 'Owner done task',
        'status' => TaskStatus::Done,
    ]);

    Task::factory()->for($owner)->create([
        'title' => 'Owner new task',
        'status' => TaskStatus::New,
    ]);

    Task::factory()->for($otherUser)->create([
        'title' => 'Other done task',
        'status' => TaskStatus::Done,
    ]);

    $this->getJson("/api/tasks?user_id={$owner->id}&status=done&per_page=10")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user_id', $owner->id)
        ->assertJsonPath('data.0.title', 'Owner done task')
        ->assertJsonPath('data.0.status', TaskStatus::Done->value)
        ->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'user_id',
                    'title',
                    'description',
                    'status',
                    'created_at',
                    'updated_at',
                ],
            ],
            'links',
            'meta',
        ]);
});

it('can show update and delete a task', function (): void {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create([
        'title' => 'Original title',
        'status' => TaskStatus::New,
    ]);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $task->id)
        ->assertJsonPath('data.title', 'Original title');

    $this->patchJson("/api/tasks/{$task->id}", [
        'title' => 'Updated title',
        'status' => TaskStatus::InProgress->value,
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated title')
        ->assertJsonPath('data.status', TaskStatus::InProgress->value);

    $this->deleteJson("/api/tasks/{$task->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('tasks', [
        'id' => $task->id,
    ]);
});

it('validates create update and list requests', function (): void {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create();

    $this->postJson('/api/tasks', [
        'status' => 'invalid',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id', 'title', 'status']);

    $this->patchJson("/api/tasks/{$task->id}", [
        'title' => '',
        'status' => 'invalid',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'status']);

    $this->getJson('/api/tasks?user_id=999999&per_page=101&page=0')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id', 'per_page', 'page']);
});

it('creates task completed outbox message when task status becomes done', function (): void {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create([
        'status' => TaskStatus::InProgress,
    ]);

    $this->patchJson("/api/tasks/{$task->id}", [
        'status' => TaskStatus::Done->value,
        'completed_by_user_id' => $user->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.status', TaskStatus::Done->value);

    $outboxMessage = OutboxMessage::query()->firstOrFail();

    expect($outboxMessage->type)->toBe(OutboxMessageType::TaskCompleted)
        ->and($outboxMessage->status)->toBe(OutboxMessageStatus::New)
        ->and($outboxMessage->payload['task_id'])->toBe($task->id)
        ->and($outboxMessage->payload['completed_by_user_id'])->toBe($user->id)
        ->and($outboxMessage->payload['status'])->toBe(TaskStatus::Done->value);

    expect(array_key_exists('previous_status', $outboxMessage->payload))->toBeFalse();
});

it('returns validation error for invalid task status transition', function (): void {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create([
        'status' => TaskStatus::New,
    ]);

    $this->patchJson("/api/tasks/{$task->id}", [
        'status' => TaskStatus::Done->value,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'status' => TaskStatus::New->value,
    ]);
});
