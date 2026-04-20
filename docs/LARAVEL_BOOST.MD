# Laravel Boost

`laravel/boost` подключен для обоих Laravel-приложений в этом репозитории:

- `gateway-api`
- `worker-service`

## Что было сделано

- В `gateway-api/composer.json` добавлен dev-пакет `laravel/boost`
- В `worker-service/composer.json` добавлен dev-пакет `laravel/boost`
- В обоих приложениях выполнена установка ресурсов Boost через `php artisan boost:install --guidelines --skills --mcp --no-interaction`
- В корне репозитория создан файл `.codex/config.toml` с двумя MCP-серверами

## MCP-конфигурация

Корневой файл `.codex/config.toml` запускает два отдельных сервера:

- `laravel-boost-gateway` с `cwd = "D:\\samples\\laravel-rabbitmq\\gateway-api"`
- `laravel-boost-worker` с `cwd = "D:\\samples\\laravel-rabbitmq\\worker-service"`

Оба сервера запускаются одной и той же командой:

```bash
php artisan boost:mcp
```

## Что появилось после установки

В каждом приложении Boost сгенерировал служебные файлы для агентов и MCP, включая:

- `.codex/`
- `.agents/`
- `.cursor/`
- `.junie/`
- `AGENTS.md`
- `boost.json`

## Как проверить

Проверка внутри каждого приложения:

```bash
php artisan boost:mcp --help
php artisan boost:install --help
```

Если обе команды существуют, Boost установлен корректно.

## Важное ограничение

Локальный PHP CLI в текущем окружении работает без расширения `ext-sockets`.

Из-за этого обычный `composer require` падал на зависимостях RabbitMQ, поэтому установка выполнялась с флагом:

```bash
composer require laravel/boost --dev --ignore-platform-req=ext-sockets --no-interaction
```

Это не ломает сам факт установки `laravel/boost`, но означает, что для полностью корректного локального PHP-окружения желательно включить `ext-sockets`.

## Что делать после изменений

- Перезапустить текущую сессию Codex
- Убедиться, что при старте поднимаются `laravel-boost-gateway` и `laravel-boost-worker`
- Если один из серверов снова упадет, проверять соответствующее приложение отдельно через `php artisan boost:mcp`
