<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
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
            'external_order_id' => $this->external_order_id,
            'customer_email' => $this->customer_email,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'received_at' => $this->received_at?->toIso8601String(),
            'processed_messages_count' => $this->whenCounted('processedMessages'),
            'processed_messages' => $this->whenLoaded('processedMessages', function (): array {
                return $this->processedMessages
                    ->map(fn ($message): array => [
                        'message_id' => $message->message_id,
                        'message_type' => $message->message_type,
                        'occurred_at' => $message->occurred_at?->toIso8601String(),
                        'processed_at' => $message->processed_at?->toIso8601String(),
                    ])
                    ->all();
            }),
        ];
    }
}
