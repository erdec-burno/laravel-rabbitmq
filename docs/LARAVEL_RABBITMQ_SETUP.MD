# Laravel RabbitMQ Setup

## Что реализовано

В проекте созданы два Laravel 12 приложения:

- `gateway-api` — HTTP API для приёма заказов
- `worker-service` — consumer для обработки сообщений из RabbitMQ

В обоих приложениях установлен пакет `vladimir-yuldashev/laravel-queue-rabbitmq`.

Также настроены:

- Pest в обоих приложениях
- Dockerfile для обоих сервисов
- `ext-sockets` внутри Docker runtime
- базовая конфигурация RabbitMQ в `config/queue.php` и `config/rabbitmq.php`

## Реализованный сценарий

### Gateway API

Добавлен endpoint:

- `POST /api/orders`

Файлы:

- `gateway-api/routes/api.php`
- `gateway-api/app/Http/Controllers/Api/OrderDispatchController.php`
- `gateway-api/app/Http/Requests/StoreOrderRequest.php`
- `gateway-api/app/Http/Resources/OrderDispatchResource.php`
- `gateway-api/app/Services/RabbitMqOrderPublisher.php`

Поведение:

1. API принимает JSON заказа.
2. Выполняется валидация:
   - `customer_email`
   - `amount`
   - `currency`
3. После успешной валидации сообщение публикуется в очередь `orders`.
4. API возвращает `202 Accepted` с метаданными отправленного сообщения.

Пример запроса:

```json
{
  "customer_email": "customer@example.com",
  "amount": 149.99,
  "currency": "usd"
}
```

Пример структуры сообщения:

```json
{
  "id": "uuid-message-id",
  "type": "orders.created",
  "occurred_at": "2026-04-19T18:00:00+00:00",
  "order": {
    "order_id": "uuid-order-id",
    "customer_email": "customer@example.com",
    "amount": 149.99,
    "currency": "USD"
  }
}
```

### Worker Service

Добавлен consumer:

- команда `php artisan rabbitmq:consume-orders`

Файлы:

- `worker-service/app/Console/Commands/ConsumeOrdersCommand.php`
- `worker-service/app/Queue/Jobs/IncomingOrderRabbitMQJob.php`
- `worker-service/app/Services/OrderMessageHandler.php`

Поведение:

1. Consumer читает сообщения из очереди `orders`.
2. Сообщение оборачивается в custom job `IncomingOrderRabbitMQJob`.
3. Job передаёт payload в `OrderMessageHandler`.
4. Handler проверяет структуру сообщения.
5. При успешной обработке сообщение логируется и подтверждается.

## Конфигурация RabbitMQ

Используются переменные окружения:

- `RABBITMQ_HOST`
- `RABBITMQ_PORT`
- `RABBITMQ_USERNAME`
- `RABBITMQ_PASSWORD`
- `RABBITMQ_VHOST`
- `RABBITMQ_QUEUE`
- `RABBITMQ_CONNECTION_TIMEOUT`
- `RABBITMQ_READ_TIMEOUT`
- `RABBITMQ_WRITE_TIMEOUT`
- `RABBITMQ_HEARTBEAT`

Текущая очередь:

- `orders`

## Docker

`compose.yaml` поднимает:

- `rabbitmq`
- `gateway`
- `worker`

В `Dockerfile` обоих Laravel приложений включён `sockets`, потому что RabbitMQ-драйвер требует это расширение.

## Тесты

Покрытие добавлено для:

- API endpoint в `gateway-api`
- обработки сообщения в `worker-service`
- custom RabbitMQ job wrapper в `worker-service`

Команды проверки:

```powershell
cd gateway-api
php artisan test --compact

cd ..\worker-service
php artisan test --compact
```

## Запуск проекта

Сборка и запуск:

```powershell
docker compose up --build
```

После запуска:

- RabbitMQ UI: `http://localhost:15672`
- логин: `app`
- пароль: `app`

## Ручная проверка API

Пример через `curl`:

```powershell
curl -Method POST http://localhost:8000/api/orders `
  -Headers @{ "Content-Type" = "application/json" } `
  -Body '{"customer_email":"customer@example.com","amount":149.99,"currency":"usd"}'
```

Ожидаемый результат:

- API возвращает `202 Accepted`
- сообщение попадает в очередь `orders`
- `worker-service` читает и обрабатывает сообщение

## Важный нюанс

На локальном Windows PHP отсутствует `ext-sockets`, поэтому пакет устанавливался с обходом локальной platform-проверки. Для реальной работы RabbitMQ нужно использовать Docker runtime, где `sockets` уже включён.
