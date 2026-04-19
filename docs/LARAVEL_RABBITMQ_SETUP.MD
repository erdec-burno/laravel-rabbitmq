# Laravel RabbitMQ Setup

## Что реализовано

В проекте созданы два Laravel 12 приложения:

- `gateway-api` — HTTP API для приема заказов
- `worker-service` — consumer и read-only API для просмотра обработанных заказов

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

## Статусы заказа

Для заказов введены статусы:

- `received`
- `processed`
- `failed`

Логика статусов:

1. Когда заказ идентифицирован и запись создается впервые, он получает статус `received`.
2. После успешного создания записи в `processed_order_messages` заказ переводится в `processed`.
3. Если в ходе обработки возникает ошибка, а заказ можно идентифицировать по payload, он переводится в `failed`.

Практический смысл:

- `received` — заказ получен и распознан, но обработка еще не завершена
- `processed` — заказ успешно обработан и записан
- `failed` — обработка завершилась ошибкой

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

## API просмотра заказов

В `worker-service` добавлен read-only API:

- `GET /api/orders`
- `GET /api/orders/{external_order_id}`

Файлы:

- `worker-service/routes/api.php`
- `worker-service/app/Http/Controllers/Api/OrderController.php`
- `worker-service/app/Http/Resources/OrderResource.php`

### `GET /api/orders`

Возвращает список заказов с пагинацией.

Для каждой записи возвращаются:

- `id`
- `external_order_id`
- `customer_email`
- `amount`
- `currency`
- `status`
- `received_at`
- `processed_messages_count`

### `GET /api/orders/{external_order_id}`

Возвращает один заказ по внешнему идентификатору.

Дополнительно возвращает список связанных сообщений обработки:

- `message_id`
- `message_type`
- `occurred_at`
- `processed_at`

Пример ответа:

```json
{
  "data": {
    "id": 1,
    "external_order_id": "order-123",
    "customer_email": "customer@example.com",
    "amount": 149.99,
    "currency": "USD",
    "status": "processed",
    "received_at": "2026-04-19T18:00:00+00:00",
    "processed_messages": [
      {
        "message_id": "message-123",
        "message_type": "orders.created",
        "occurred_at": "2026-04-19T18:00:00+00:00",
        "processed_at": "2026-04-19T18:00:02+00:00"
      }
    ]
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
SELECT pm.id, pm.message_id, pm.message_type, o.external_order_id, o.customer_email, o.amount, o.currency, o.status, pm.processed_at
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
- статусов `processed` и `failed`
- API просмотра заказов

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

Пример отправки заказа:

```powershell
curl -Method POST http://localhost:8000/api/orders `
  -Headers @{ "Content-Type" = "application/json" } `
  -Body '{"customer_email":"customer@example.com","amount":149.99,"currency":"usd"}'
```

Пример просмотра списка заказов:

```powershell
curl http://localhost:8000/api/orders
```

Пример просмотра одного заказа:

```powershell
curl http://localhost:8000/api/orders/order-123
```

Ожидаемый результат:

- API возвращает `202 Accepted`
- сообщение попадает в очередь `orders`
- `worker-service` читает и обрабатывает сообщение
- `worker-service` сохраняет заказ в `orders`
- `worker-service` сохраняет запись в `processed_order_messages`
- заказ получает статус `processed`

Если обработка падает, а заказ можно идентифицировать:

- заказ сохраняется или обновляется со статусом `failed`

## Важный нюанс

На локальном Windows PHP отсутствует `ext-sockets`, поэтому пакет устанавливался с обходом локальной platform-проверки. Для реальной работы RabbitMQ нужно использовать Docker runtime, где `sockets` уже включен.
