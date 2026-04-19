<?php

return [
    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'username' => env('RABBITMQ_USERNAME', 'app'),
    'password' => env('RABBITMQ_PASSWORD', 'app'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'queue' => env('RABBITMQ_QUEUE', 'orders'),
];
