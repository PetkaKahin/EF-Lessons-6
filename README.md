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
