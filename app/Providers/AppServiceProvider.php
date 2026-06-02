<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\TaskCompleted;
use App\Jobs\SendTaskCompletedNotification;
use App\Repositories\EloquentTaskRepository;
use App\Repositories\TaskRepositoryInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            TaskRepositoryInterface::class,
            EloquentTaskRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(TaskCompleted::class, function (TaskCompleted $event): void {
            SendTaskCompletedNotification::dispatch(
                $event->task,
                $event->completedByUserId ?? (int) $event->task->user_id,
                $event->occurredAt,
            );
        });
    }
}
