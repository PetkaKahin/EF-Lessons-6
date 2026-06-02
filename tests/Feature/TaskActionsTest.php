<?php

declare(strict_types=1);

use App\Application\Tasks\Actions\UpdateTask;
use App\Domain\Task\Enums\TaskStatus;
use App\Events\TaskCompleted;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('dispatches task completed event from update action', function (): void {
    Event::fake([
        TaskCompleted::class,
    ]);

    $owner = User::factory()->create();
    $task = Task::factory()->for($owner)->create([
        'status' => TaskStatus::InProgress,
    ]);

    app(UpdateTask::class)->handle(
        $task,
        ['status' => TaskStatus::Done->value],
        $owner->id,
    );

    Event::assertDispatched(
        TaskCompleted::class,
        fn (TaskCompleted $event): bool => $event->task->is($task)
            && $event->completedByUserId === $owner->id
            && $event->previousStatus === TaskStatus::InProgress
    );
});
