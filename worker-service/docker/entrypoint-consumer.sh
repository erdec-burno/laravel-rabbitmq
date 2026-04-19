#!/bin/sh
set -eu

QUEUE_NAME="${RABBITMQ_QUEUE:-orders}"

echo "Waiting for RabbitMQ and declaring queue: ${QUEUE_NAME}"

attempt=0
until php artisan rabbitmq:queue-declare "${QUEUE_NAME}" rabbitmq >/dev/null 2>&1
do
    attempt=$((attempt + 1))
    echo "RabbitMQ not ready or queue declare failed, retry ${attempt}..."
    sleep 2
done

echo "Queue is ready: ${QUEUE_NAME}"

exec php artisan rabbitmq:consume-orders --tries=3 --sleep=1
