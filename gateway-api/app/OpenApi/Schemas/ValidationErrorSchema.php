<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ValidationError',
    type: 'object',
    required: ['message', 'errors'],
    properties: [
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'The customer email field must be a valid email address. (and 2 more errors)'
        ),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string')
            ),
            example: [
                'customer_email' => ['Customer email must be a valid email address.'],
                'amount' => ['Order amount must be greater than zero.'],
                'currency' => ['Currency must be a 3-letter ISO code.'],
            ]
        ),
    ]
)]
class ValidationErrorSchema {}
