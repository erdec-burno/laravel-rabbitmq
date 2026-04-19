# Laravel RabbitMQ Setup

## Что реализовано

В проекте созданы два Laravel 12 приложения:

- `gateway-api` — HTTP API для приема заказов
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
3. Job передает payload в `OrderMessageHandler`.
4. Handler проверяет структуру сообщения.
5. Handler сохраняет результат обработки в БД.
6. При успешной обработке сообщение логируется и подтверждается.

## Бизнес-логика обработки заказа

В `worker-service` добавлены модели:

- `worker-service/app/Models/Order.php`
- `worker-service/app/Models/ProcessedOrderMessage.php`

Добавлены:

- миграция `orders`
- миграция `processed_order_messages`
- фабрики для тестов

Handler теперь:

1. Проверяет тип сообщения, структуру payload и обязательные поля заказа.
2. Создает или находит доменную сущность заказа в таблице `orders`.
3. Создает или находит запись в журнале `processed_order_messages`.
4. Связывает обработанное сообщение с заказом.
5. Сохраняет исходный `payload` целиком в JSON.
6. Использует idempotency по `message_id`, чтобы одно и то же сообщение не записывалось повторно.

Из payload извлекаются:

- `message_id`
- `message_type`
- `order_id`
- `customer_email`
- `amount`
- `currency`
- `occurred_at`

## Разделение данных

Теперь хранение разделено на 2 уровня.

## ER-описание схемы

Схема хранения сейчас выглядит так:

```text
orders
  id PK
  external_order_id UNIQUE
  customer_email
  amount
  currency
  status
  received_at
  created_at
  updated_at

processed_order_messages
  id PK
  message_id UNIQUE
  message_type
  order_id FK -> orders.id
  occurred_at
  processed_at
  payload
  created_at
  updated_at
```

Связь:

- один `order` может иметь несколько связанных записей в `processed_order_messages`
- одна запись `processed_order_messages` принадлежит одному `order`

Практический смысл:

- `orders` хранит бизнес-сущность заказа
- `processed_order_messages` хранит журнал получения и обработки RabbitMQ-сообщений

### Таблица `orders`

Это доменная сущность заказа.

Хранит:

- `external_order_id`
- `customer_email`
- `amount`
- `currency`
- `status`
- `received_at`

### Таблица `processed_order_messages`

Это журнал входящих RabbitMQ-сообщений.

Хранит:

- `message_id`
- `message_type`
- `order_id` — внешний ключ на `orders.id`
- `occurred_at`
- `processed_at`
- `payload`
- `created_at`
- `updated_at`

Таким образом:

- `orders` описывает сам бизнес-объект
- `processed_order_messages` описывает факт получения и обработки сообщения

Если сообщение приходит повторно:

- новая запись в `orders` не создается
- новая запись в `processed_order_messages` не создается
- обработчик логирует пропуск дубля

## Пример записей в БД

Пример строки в `orders` после успешной обработки:

```json
{
  "id": 1,
  "external_order_id": "order-123",
  "customer_email": "customer@example.com",
  "amount": "149.99",
  "currency": "USD",
  "status": "processed",
  "received_at": "2026-04-19 18:00:00"
}
```

Пример строки в `processed_order_messages` после успешной обработки:

```json
{
  "message_id": "message-123",
  "message_type": "orders.created",
  "order_id": 1,
  "occurred_at": "2026-04-19 18:00:00",
  "processed_at": "2026-04-19 18:00:02",
  "payload": {
    "id": "message-123",
    "type": "orders.created",
    "occurred_at": "2026-04-19T18:00:00+00:00",
    "order": {
      "order_id": "order-123",
      "customer_email": "customer@example.com",
      "amount": 149.99,
      "currency": "USD"
    }
  }
}
```

## Проверка через SQL

Посмотреть все сохраненные заказы:

```sql
SELECT id, external_order_id, customer_email, amount, currency, status, received_at
FROM orders
ORDER BY id DESC;
```

Посмотреть все обработанные сообщения вместе с заказами:

```sql
SELECT pm.id, pm.message_id, pm.message_type, o.external_order_id, o.customer_email, o.amount, o.currency, pm.processed_at
FROM processed_order_messages pm
JOIN orders o ON o.id = pm.order_id
ORDER BY pm.id DESC;
```

Проверить конкретное сообщение по `message_id`:

```sql
SELECT *
FROM processed_order_messages
WHERE message_id = 'message-123';
```

Проверить, что дубль не был создан:

```sql
SELECT message_id, COUNT(*) AS total
FROM processed_order_messages
GROUP BY message_id
HAVING COUNT(*) > 1;
```

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

В `Dockerfile` обоих Laravel приложений включен `sockets`, потому что RabbitMQ-драйвер требует это расширение.

## Тесты

Покрытие добавлено для:

- API endpoint в `gateway-api`
- обработки сообщения в `worker-service`
- custom RabbitMQ job wrapper в `worker-service`
- сохранения заказа в таблицу `orders`
- сохранения обработанного сообщения в `processed_order_messages`
- защиты от повторной обработки одного и того же сообщения без дублей в обеих таблицах

Команды проверки:

```powershell
cd gateway-api
php artisan test --compact

cd ..\worker-service
php artisan migrate
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
- `worker-service` сохраняет заказ в `orders`
- `worker-service` сохраняет запись в `processed_order_messages`

## Важный нюанс

На локальном Windows PHP отсутствует `ext-sockets`, поэтому пакет устанавливался с обходом локальной platform-проверки. Для реальной работы RabbitMQ нужно использовать Docker runtime, где `sockets` уже включен.
