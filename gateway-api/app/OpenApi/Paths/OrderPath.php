<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

#[OA\Post(
    path: '/api/orders',
    operationId: 'storeOrder',
    summary: 'Accept an order for asynchronous processing',
    tags: ['Orders'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/StoreOrderRequest')
    ),
    responses: [
        new OA\Response(
            response: 202,
            description: 'Order accepted and published to the queue.',
            content: new OA\JsonContent(ref: '#/components/schemas/OrderDispatchResponse')
        ),
        new OA\Response(
            response: 422,
            description: 'The submitted order payload is invalid.',
            content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
        ),
    ]
)]
class OrderPath {}
