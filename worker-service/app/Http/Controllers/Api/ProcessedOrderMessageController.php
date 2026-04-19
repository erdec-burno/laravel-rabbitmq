<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexProcessedOrderMessageRequest;
use App\Http\Resources\ProcessedOrderMessageResource;
use App\Models\ProcessedOrderMessage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProcessedOrderMessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexProcessedOrderMessageRequest $request): AnonymousResourceCollection
    {
        $messages = ProcessedOrderMessage::query()
            ->with('order')
            ->when(
                $request->validated('message_type'),
                fn ($query, string $messageType) => $query->where('message_type', $messageType)
            )
            ->when(
                $request->validated('message_id'),
                fn ($query, string $messageId) => $query->where('message_id', 'like', '%'.$messageId.'%')
            )
            ->when(
                $request->validated('external_order_id'),
                fn ($query, string $externalOrderId) => $query->whereHas(
                    'order',
                    fn ($orderQuery) => $orderQuery->where('external_order_id', 'like', '%'.$externalOrderId.'%')
                )
            )
            ->latest()
            ->paginate(15);

        return ProcessedOrderMessageResource::collection($messages);
    }

    /**
     * Display the specified resource.
     */
    public function show(ProcessedOrderMessage $processedOrderMessage): ProcessedOrderMessageResource
    {
        $processedOrderMessage->load('order');

        return new ProcessedOrderMessageResource($processedOrderMessage);
    }
}
