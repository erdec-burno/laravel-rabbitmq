# Laravel RabbitMQ Setup

## Что реализовано

В репозитории подготовлены два Laravel 12 сервиса:

- `gateway-api` — HTTP API для приёма заказов и публикации сообщений в RabbitMQ
- `worker-service` — consumer RabbitMQ и read-only API для просмотра сохранённых заказов и обработанных сообщений

В проекте используется пакет `vladimir-yuldashev/laravel-queue-rabbitmq`.

Также настроены:

- Pest
- Docker-конфигурация для `gateway-api`
- Docker-конфигурация для `worker-service` в двух ролях: consumer и HTTP API
- RabbitMQ с management UI
- OpenAPI / Swagger-документация

## Архитектура контейнеров

Текущая схема `docker compose`:

- `rabbitmq` — брокер сообщений
- `gateway` — HTTP API на `http://localhost:8000`
- `worker` — фоновый consumer RabbitMQ
- `worker-fpm` — PHP-FPM runtime для `worker-service`
- `worker-nginx` — HTTP API `worker-service` на `http://localhost:8001`

Практический смысл:

- `gateway` принимает входящие запросы и публикует события
- `worker` читает очередь и обрабатывает сообщения
- `worker-nginx` + `worker-fpm` отдают read-only API `worker-service`

## Поток обработки

### 1. Gateway API

Endpoint:

- `POST /api/orders`

Основные файлы:

- `gateway-api/routes/api.php`
- `gateway-api/app/Http/Controllers/Api/OrderDispatchController.php`
- `gateway-api/app/Http/Requests/StoreOrderRequest.php`
- `gateway-api/app/Http/Resources/OrderDispatchResource.php`
- `gateway-api/app/Services/RabbitMqOrderPublisher.php`

Поведение:

1. API принимает JSON заказа.
2. Валидирует `customer_email`, `amount`, `currency`.
3. Формирует сообщение `orders.created`.
4. Публикует сообщение в очередь `orders`.
5. Возвращает `202 Accepted`.

Пример запроса:

```json
{
  "customer_email": "customer@example.com",
  "amount": 149.99,
  "currency": "usd"
}
```

Пример payload сообщения:

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

### 2. Worker Service

Consumer:

- `php artisan rabbitmq:consume-orders`

Основные файлы:

- `worker-service/app/Console/Commands/ConsumeOrdersCommand.php`
- `worker-service/app/Queue/Jobs/IncomingOrderRabbitMQJob.php`
- `worker-service/app/Services/OrderMessageHandler.php`

Поведение:

1. Consumer читает сообщения из очереди `orders`.
2. Сообщение оборачивается в `IncomingOrderRabbitMQJob`.
3. Job передаёт payload в `OrderMessageHandler`.
4. Handler валидирует структуру сообщения.
5. Handler сохраняет заказ и журнал обработки.
6. При успешной обработке сообщение подтверждается.

## Модель данных

Используются две таблицы:

- `orders`
- `processed_order_messages`

### ER-описание

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

- один `order` имеет много `processed_order_messages`
- одна запись `processed_order_messages` принадлежит одному `order`

Смысл:

- `orders` хранит доменную сущность заказа
- `processed_order_messages` хранит журнал обработки входящих RabbitMQ-сообщений

## Статусы заказа

В `worker-service` заказ проходит через статусы:

- `received`
- `processed`
- `failed`

Логика переходов:

1. При первом распознавании заказ создаётся со статусом `received`.
2. После успешной обработки получает статус `processed`.
3. Если обработка завершается ошибкой и заказ идентифицирован, он получает статус `failed`.

## Idempotency

Защита от дублей построена по `message_id`.

Если одно и то же сообщение приходит повторно:

- новый `order` не создаётся
- новая запись в `processed_order_messages` не создаётся
- обработчик логирует пропуск дубля

## Пример записей в БД

Пример строки в `orders`:

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

Пример строки в `processed_order_messages`:

```json
{
  "id": 1,
  "message_id": "message-123",
  "message_type": "orders.created",
  "order_id": 1,
  "occurred_at": "2026-04-19 18:00:00",
  "processed_at": "2026-04-19 18:00:01",
  "payload": {
    "id": "message-123",
    "type": "orders.created"
  }
}
```

## API просмотра заказов

В `worker-service` доступны:

- `GET /api/orders`
- `GET /api/orders/{external_order_id}`
- `GET /api/processed-order-messages`
- `GET /api/processed-order-messages/{message_id}`

Основные файлы:

- `worker-service/routes/api.php`
- `worker-service/app/Http/Controllers/Api/OrderController.php`
- `worker-service/app/Http/Controllers/Api/ProcessedOrderMessageController.php`
- `worker-service/app/Http/Resources/OrderResource.php`
- `worker-service/app/Http/Resources/ProcessedOrderMessageResource.php`
- `worker-service/app/Http/Requests/IndexOrderRequest.php`
- `worker-service/app/Http/Requests/IndexProcessedOrderMessageRequest.php`

### `GET /api/orders`

Возвращает пагинированный список заказов.

Поля ответа:

- `id`
- `external_order_id`
- `customer_email`
- `amount`
- `currency`
- `status`
- `received_at`
- `processed_messages_count`

Поддерживаемые фильтры:

- `status=received|processed|failed`
- `customer_email=partial-value`
- `date_from=YYYY-MM-DD`
- `date_to=YYYY-MM-DD`

Примечания:

- диапазон дат применяется к полю `received_at`
- `date_to` должен быть больше или равен `date_from`

Примеры:

```text
GET /api/orders?status=processed
GET /api/orders?customer_email=customer@example.com
GET /api/orders?status=failed&customer_email=example.com
GET /api/orders?date_from=2026-04-10&date_to=2026-04-19
GET /api/orders?status=processed&date_from=2026-04-01&date_to=2026-04-30
```

### `GET /api/orders/{external_order_id}`

Возвращает один заказ по внешнему идентификатору и связанные записи из `processed_order_messages`.

Пример ответа:

```json
{
  "data": {
    "id": 1,
    "external_order_id": "order-123",
    "customer_email": "customer@example.com",
    "amount": "149.99",
    "currency": "USD",
    "status": "processed",
    "received_at": "2026-04-19T18:00:00.000000Z",
    "processed_messages_count": 1,
    "processed_messages": [
      {
        "message_id": "message-123",
        "message_type": "orders.created",
        "occurred_at": "2026-04-19T18:00:00.000000Z",
        "processed_at": "2026-04-19T18:00:01.000000Z"
      }
    ]
  }
}
```

## API просмотра обработанных сообщений

Отдельно от заказов доступны endpoints для журнала `processed_order_messages`:

- `GET /api/processed-order-messages`
- `GET /api/processed-order-messages/{message_id}`

### `GET /api/processed-order-messages`

Возвращает пагинированный список обработанных сообщений.

Для каждой записи доступны:

- `id`
- `message_id`
- `message_type`
- `occurred_at`
- `processed_at`
- `payload`
- `order`

Поддерживаемые фильтры:

- `message_type=orders.created`
- `message_id=partial-value`
- `external_order_id=partial-value`

### `GET /api/processed-order-messages/{message_id}`

Возвращает одно обработанное сообщение по `message_id`.

В ответе также присутствует краткая информация о связанном заказе:

- `external_order_id`
- `customer_email`
- `amount`
- `currency`
- `status`
- `received_at`

## SQL для проверки

Список заказов:

```sql
SELECT id, external_order_id, customer_email, amount, currency, status, received_at
FROM orders
ORDER BY id DESC;
```

Журнал обработанных сообщений:

```sql
SELECT id, message_id, message_type, order_id, occurred_at, processed_at
FROM processed_order_messages
ORDER BY id DESC;
```

Проверка связки заказа с сообщением:

```sql
SELECT pm.id, pm.message_id, pm.message_type, o.external_order_id,
       o.customer_email, o.amount, o.currency, o.status, pm.processed_at
FROM processed_order_messages pm
JOIN orders o ON o.id = pm.order_id
ORDER BY pm.id DESC;
```

Проверка дублей по `message_id`:

```sql
SELECT message_id, COUNT(*) AS duplicates_count
FROM processed_order_messages
GROUP BY message_id
HAVING COUNT(*) > 1;
```

## Ручная проверка

Запуск:

```powershell
docker compose up --build
```

Отправка заказа в `gateway-api`:

```powershell
Invoke-RestMethod `
  -Method Post `
  -Uri http://localhost:8000/api/orders `
  -Headers @{ "Content-Type" = "application/json" } `
  -Body '{"customer_email":"customer@example.com","amount":149.99,"currency":"usd"}'
```

Просмотр всех заказов в `worker-service`:

```powershell
curl http://localhost:8001/api/orders
```

Фильтр по статусу:

```powershell
curl http://localhost:8001/api/orders?status=processed
```

Фильтр по email:

```powershell
curl http://localhost:8001/api/orders?customer_email=example.com
```

Фильтр по диапазону дат:

```powershell
curl "http://localhost:8001/api/orders?date_from=2026-04-10&date_to=2026-04-19"
```

Комбинированный фильтр:

```powershell
curl "http://localhost:8001/api/orders?status=processed&customer_email=example.com&date_from=2026-04-01&date_to=2026-04-30"
```

Просмотр одного заказа:

```powershell
curl http://localhost:8001/api/orders/order-123
```

Просмотр списка обработанных сообщений:

```powershell
curl http://localhost:8001/api/processed-order-messages
```

Фильтр по типу сообщения:

```powershell
curl "http://localhost:8001/api/processed-order-messages?message_type=orders.created"
```

Фильтр по части `message_id`:

```powershell
curl "http://localhost:8001/api/processed-order-messages?message_id=alpha"
```

Фильтр по части `external_order_id`:

```powershell
curl "http://localhost:8001/api/processed-order-messages?external_order_id=order-123"
```

Просмотр одного обработанного сообщения:

```powershell
curl http://localhost:8001/api/processed-order-messages/message-123
```

## Тесты

Актуальная проверка `worker-service`:

```powershell
php artisan test --compact
```

Покрыто тестами:

- сохранение обработанных сообщений
- idempotency по `message_id`
- статусы `received`, `processed`, `failed`
- API списка заказов
- API просмотра одного заказа
- API списка обработанных сообщений
- API просмотра одного обработанного сообщения
- фильтрация по `status`
- фильтрация по `customer_email`
- фильтрация по диапазону `received_at`
- фильтрация `processed_order_messages` по `message_type`
- фильтрация `processed_order_messages` по `message_id`
- фильтрация `processed_order_messages` по `external_order_id`

## Важный нюанс

На локальном Windows PHP может отсутствовать `ext-sockets`.
Поэтому для реальной работы с RabbitMQ сервисы лучше запускать через Docker, где расширение уже установлено.
