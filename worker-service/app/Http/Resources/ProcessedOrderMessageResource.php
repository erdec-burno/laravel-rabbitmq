<?php

namespace App\Http\Resources;

use App\Models\ProcessedOrderMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProcessedOrderMessage
 */
class ProcessedOrderMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message_id' => $this->message_id,
            'message_type' => $this->message_type,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'payload' => $this->payload,
            'order' => $this->whenLoaded('order', fn (): array => [
                'id' => $this->order->id,
                'external_order_id' => $this->order->external_order_id,
                'customer_email' => $this->order->customer_email,
                'amount' => (float) $this->order->amount,
                'currency' => $this->order->currency,
                'status' => $this->order->status,
                'received_at' => $this->order->received_at?->toIso8601String(),
            ]),
        ];
    }
}
