<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Notifications\Actions\LogFailedTaskCompletedNotification;
use App\Application\Notifications\Listeners\QueueTaskCompletedNotification;
use App\Events\TaskCompleted;
use App\Events\TaskCompletedNotificationFailed;
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
        Event::listen(
            TaskCompleted::class,
            QueueTaskCompletedNotification::class,
        );

        Event::listen(
            TaskCompletedNotificationFailed::class,
            LogFailedTaskCompletedNotification::class,
        );
    }
}
