<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Task\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserWithTasksSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        );

        $tasks = [
            [
                'title' => 'Create Tasks API',
                'description' => 'Check create, list, show, update and delete endpoints.',
                'status' => TaskStatus::New,
            ],
            [
                'title' => 'Configure queue worker',
                'description' => 'Redis queue should process background jobs.',
                'status' => TaskStatus::InProgress,
            ],
            [
                'title' => 'Write task completed notification',
                'description' => 'Task completion should write email notification payload.',
                'status' => TaskStatus::Done,
            ],
        ];

        foreach ($tasks as $task) {
            Task::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'title' => $task['title'],
                ],
                [
                    'description' => $task['description'],
                    'status' => $task['status'],
                ],
            );
        }
    }
}
