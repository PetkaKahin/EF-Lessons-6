### Пинг очереди
```bash
make artisan ping:produce
```

### Запуск outbox сообщений
```bash
make artisan outbox:publish
```

### Rate limiting уведомлений
`SendTaskCompletedNotification` делает `sleep` 200 мс перед отправкой: максимум 5 уведомлений/сек на один воркер.

### Webhook delivery
```bash
NOTIFICATION_WEBHOOK_URL=http://localhost:8080/webhooks/tasks/completed
NOTIFICATION_WEBHOOK_TIMEOUT=5
```
