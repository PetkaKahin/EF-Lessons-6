<?php

declare(strict_types=1);

namespace App\Application\Notifications\Actions;

use App\Application\Notifications\Contracts\WebhookAttemptRepositoryInterface;
use App\Domain\Task\Enums\TaskStatus;
use DateTimeInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Throwable;

readonly class SendTaskCompletedWebhook
{
    private const NOTIFICATION_RATE_LIMIT_PER_SECOND = 5;

    public function __construct(
        private WebhookAttemptRepositoryInterface $webhookAttempts,
    ) {}

    public function send(int $taskId, TaskStatus $status, DateTimeInterface $occurredAt): void
    {
        $webhookIdempotencyKey = (string) $taskId;

        Sleep::usleep((int) ceil(1_000_000 / self::NOTIFICATION_RATE_LIMIT_PER_SECOND));

        $payload = [
            'taskId' => $taskId,
            'status' => $status->value,
            'occurredAt' => $occurredAt->format(DATE_ATOM),
        ];
        $url = (string) config('services.notifications.webhook_url');
        $responseStatus = null;
        $error = null;

        try {
            if ($url === '') {
                throw new RuntimeException('Notification webhook URL is not configured');
            }

            if ($taskId % 5 === 0) {
                throw new RuntimeException("Simulated notification failure for task {$taskId}");
            }

            $response = Http::timeout($this->webhookTimeout())
                ->withHeaders(['Idempotency-Key' => $webhookIdempotencyKey])
                ->post($url, $payload);

            $responseStatus = $response->status();

            if ($response->failed()) {
                throw new RuntimeException("Webhook delivery failed with status {$responseStatus}");
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();

            throw $exception;
        } finally {
            $this->webhookAttempts->create([
                'task_id' => $taskId,
                'idempotency_key' => $webhookIdempotencyKey,
                'url' => $url,
                'payload' => $payload,
                'response_status' => $responseStatus,
                'error' => $error,
                'attempted_at' => now(),
            ]);
        }
    }

    private function webhookTimeout(): int
    {
        return max(1, (int) config('services.notifications.webhook_timeout', 5));
    }
}
