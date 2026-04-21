<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Gateway API',
    description: 'HTTP API for accepting orders and publishing them to RabbitMQ.'
)]
#[OA\Server(
    url: '/',
    description: 'Current gateway host'
)]
#[OA\Tag(
    name: 'Orders',
    description: 'Order dispatch operations'
)]
class OpenApiSpec {}
