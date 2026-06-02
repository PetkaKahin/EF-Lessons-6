<?php

declare(strict_types=1);

namespace App\Domain\Task;

use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Exceptions\InvalidTaskStatusTransition;
use App\Domain\Task\Exceptions\InvalidTaskTitle;
use DateTimeImmutable;

final class Task
{
    private const int MAX_TITLE_LENGTH = 255;

    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public private(set) string $title,
        public private(set) ?string $description,
        public private(set) TaskStatus $status,
        public readonly DateTimeImmutable $createdAt,
        public private(set) ?DateTimeImmutable $updatedAt = null,
    ) {
        self::validateTitle($title);
    }

    public static function create(
        int $userId,
        string $title,
        DateTimeImmutable $createdAt,
        ?string $description = null,
        TaskStatus $status = TaskStatus::New,
    ): self {
        $task = new self(
            id: null,
            userId: $userId,
            title: $title,
            description: $description,
            status: TaskStatus::New,
            createdAt: $createdAt,
        );

        if ($status !== TaskStatus::New) {
            $task->changeStatus($status);
        }

        return $task;
    }

    public function rename(string $title): void
    {
        self::validateTitle($title);

        $this->title = $title;
    }

    public function changeDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function changeStatus(TaskStatus $status): void
    {
        if ($this->status === $status) {
            return;
        }

        if ($this->status->next() !== $status) {
            throw new InvalidTaskStatusTransition($this->status, $status);
        }

        $this->status = $status;
    }

    /**
     * @return array{
     *     id: int|null,
     *     user_id: int,
     *     title: string,
     *     description: string|null,
     *     status: string,
     *     created_at: string,
     *     updated_at: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'created_at' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'updated_at' => $this->updatedAt?->format(DateTimeImmutable::ATOM),
        ];
    }

    /**
     * @return array{user_id: int, title: string, description: string|null, status: string}
     */
    public function toPersistenceArray(): array
    {
        return [
            'user_id' => $this->userId,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
        ];
    }

    public function startProgress(): void
    {
        $this->changeStatus(TaskStatus::InProgress);
    }

    public function complete(): void
    {
        $this->changeStatus(TaskStatus::Done);
    }

    private static function validateTitle(string $title): void
    {
        if (trim($title) === '') {
            throw new InvalidTaskTitle('Title is required.');
        }

        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            throw new InvalidTaskTitle('Title must not be greater than 255 characters.');
        }
    }
}
