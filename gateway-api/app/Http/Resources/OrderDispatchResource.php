<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array{
 *     message_id: string,
 *     queue: string,
 *     type: string,
 *     occurred_at: string,
 *     order: array{
 *         order_id: string,
 *         customer_email: string,
 *         amount: float,
 *         currency: string
 *     }
 * }
 */
class OrderDispatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => 'accepted',
            'message_id' => $this['message_id'],
            'queue' => $this['queue'],
            'event' => $this['type'],
            'occurred_at' => $this['occurred_at'],
            'order' => $this['order'],
        ];
    }
}
