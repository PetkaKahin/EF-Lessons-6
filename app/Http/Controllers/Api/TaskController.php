<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Tasks\Actions\CreateTask;
use App\Application\Tasks\Actions\UpdateTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\IndexTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Repositories\TaskRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    private const int DEFAULT_PER_PAGE = 15;

    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly CreateTask              $createTask,
        private readonly UpdateTask              $updateTask,
    )
    {
    }

    public function index(IndexTaskRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $perPage = (int)($validated['per_page'] ?? self::DEFAULT_PER_PAGE);

        $tasks = $this->taskRepository->paginate(
            $validated['status'] ?? null,
            $perPage,
            array_key_exists('user_id', $validated) ? (int)$validated['user_id'] : null,
        );

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $this->createTask->handle($request->validated());

        return new TaskResource($task)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(int $taskId): TaskResource
    {
        return new TaskResource($this->taskRepository->findOrFail($taskId));
    }

    public function update(UpdateTaskRequest $request, int $taskId): TaskResource
    {
        $task = $this->taskRepository->findOrFail($taskId);
        $validated = $request->validated();
        $actorId = (int)($validated['completed_by_user_id'] ?? $task->user_id);

        unset($validated['completed_by_user_id']);

        $task = $this->updateTask->handle(
            $task,
            $validated,
            $actorId,
        );

        return new TaskResource($task);
    }

    public function destroy(int $taskId): Response
    {
        $task = $this->taskRepository->findOrFail($taskId);

        $this->taskRepository->delete($task);

        return response()->noContent();
    }
}
