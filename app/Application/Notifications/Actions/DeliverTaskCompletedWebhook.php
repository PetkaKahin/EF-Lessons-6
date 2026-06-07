<?php

declare(strict_types=1);

namespace App\Application\Notifications\Actions;

use App\Application\Messages\Actions\MarkMessageProcessed;
use App\Application\Notifications\Contracts\WebhookAttemptRepositoryInterface;
use App\Domain\Task\Enums\TaskStatus;
use DateTimeInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Throwable;

readonly class DeliverTaskCompletedWebhook
{
    private const NOTIFICATION_RATE_LIMIT_PER_SECOND = 5;

    public function __construct(
        private WebhookAttemptRepositoryInterface $webhookAttempts,
        private MarkMessageProcessed $processedMessages,
    ) {}

    public function handle(int $taskId, TaskStatus $status, DateTimeInterface $occurredAt): void
    {
        $idempotencyKey = (string) $taskId;
        $processedKey = "notification:{$taskId}";

        if (! $this->processedMessages->handle($processedKey)) {
            return;
        }

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
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->post($url, $payload);

            $responseStatus = $response->status();

            if ($response->failed()) {
                throw new RuntimeException("Webhook delivery failed with status {$responseStatus}");
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
            $this->processedMessages->forget($processedKey);

            throw $exception;
        } finally {
            $this->webhookAttempts->create([
                'task_id' => $taskId,
                'idempotency_key' => $idempotencyKey,
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
