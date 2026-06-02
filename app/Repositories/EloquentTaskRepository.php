<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentTaskRepository implements TaskRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, Task>
     */
    public function paginate(?string $status, int $perPage, ?int $userId = null): LengthAwarePaginator
    {
        return Task::query()
            ->when(
                $userId,
                fn (Builder $query, int $userId): Builder => $query->where('user_id', $userId)
            )
            ->when(
                $status,
                fn (Builder $query, string $status): Builder => $query->where('status', $status)
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Task
    {
        return Task::query()->create($data);
    }

    public function findOrFail(int $id): Task
    {
        return Task::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Task $task, array $data): Task
    {
        $task->update($data);

        return $task;
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }
}
