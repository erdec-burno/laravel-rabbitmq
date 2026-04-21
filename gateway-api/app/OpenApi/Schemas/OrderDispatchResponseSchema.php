<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'OrderDispatchResponse',
    type: 'object',
    required: ['data'],
    properties: [
        new OA\Property(
            property: 'data',
            type: 'object',
            required: [
                'status',
                'message_id',
                'queue',
                'event',
                'schema_version',
                'producer',
                'occurred_at',
                'order',
            ],
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'accepted'),
                new OA\Property(property: 'message_id', type: 'string', example: 'message-123'),
                new OA\Property(property: 'queue', type: 'string', example: 'orders'),
                new OA\Property(property: 'event', type: 'string', example: 'orders.created'),
                new OA\Property(property: 'schema_version', type: 'integer', example: 1),
                new OA\Property(property: 'producer', type: 'string', example: 'gateway-api'),
                new OA\Property(property: 'occurred_at', type: 'string', format: 'date-time', example: '2026-04-19T18:00:00+00:00'),
                new OA\Property(property: 'order', ref: '#/components/schemas/Order'),
            ]
        ),
    ]
)]
class OrderDispatchResponseSchema {}
