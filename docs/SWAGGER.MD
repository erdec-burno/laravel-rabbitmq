# Swagger / OpenAPI

Swagger добавлен в `gateway-api` через пакет `darkaonline/l5-swagger`.

## Gateway API

- Swagger UI: `http://localhost:8000/api/documentation`
- OpenAPI JSON: `gateway-api/storage/api-docs/api-docs.json`
- L5 Swagger config: `gateway-api/config/l5-swagger.php`
- Сканируемая директория: `gateway-api/app/OpenApi`

Сгенерировать спецификацию:

```bash
cd gateway-api
php artisan l5-swagger:generate
```

## Структура OpenAPI

OpenAPI-описание вынесено из `Request`, `DTO` и `Resource` классов.

Текущие классы:

- `gateway-api/app/OpenApi/OpenApiSpec.php` - общая информация API, серверы и теги.
- `gateway-api/app/OpenApi/Paths/OrderPath.php` - описание endpoint `POST /api/orders`.
- `gateway-api/app/OpenApi/Schemas/StoreOrderRequestSchema.php` - schema входящего payload.
- `gateway-api/app/OpenApi/Schemas/OrderDispatchResponseSchema.php` - schema успешного ответа.
- `gateway-api/app/OpenApi/Schemas/OrderSchema.php` - schema order-объекта в ответе.
- `gateway-api/app/OpenApi/Schemas/ValidationErrorSchema.php` - schema ошибки валидации Laravel.

Правило для дальнейших изменений: OpenAPI attributes добавляем только в `app/OpenApi`, не в `app/Http/Requests`, `app/Http/Resources`, контроллеры или DTO.

## Проверка

После изменения OpenAPI-классов:

```bash
cd gateway-api
php artisan l5-swagger:generate
vendor/bin/pint --dirty --format agent
php artisan test --compact
```

Если Composer запускается на локальном PHP без `ext-sockets`, команды установки зависимостей для этого проекта могут требовать:

```bash
composer install --ignore-platform-req=ext-sockets
```
