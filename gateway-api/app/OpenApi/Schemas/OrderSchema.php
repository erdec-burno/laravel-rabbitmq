<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Order',
    type: 'object',
    required: ['order_id', 'customer_email', 'amount', 'currency'],
    properties: [
        new OA\Property(property: 'order_id', type: 'string', example: 'order-123'),
        new OA\Property(property: 'customer_email', type: 'string', format: 'email', example: 'customer@example.com'),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 149.99),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
    ]
)]
class OrderSchema {}
