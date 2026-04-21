<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreOrderRequest',
    type: 'object',
    required: ['customer_email', 'amount', 'currency'],
    properties: [
        new OA\Property(
            property: 'customer_email',
            description: 'Customer email address.',
            type: 'string',
            format: 'email',
            example: 'customer@example.com'
        ),
        new OA\Property(
            property: 'amount',
            description: 'Order total. Must be greater than zero.',
            type: 'number',
            format: 'float',
            minimum: 0.01,
            example: 149.99
        ),
        new OA\Property(
            property: 'currency',
            description: 'Three-letter ISO currency code.',
            type: 'string',
            minLength: 3,
            maxLength: 3,
            example: 'USD'
        ),
    ]
)]
class StoreOrderRequestSchema {}
