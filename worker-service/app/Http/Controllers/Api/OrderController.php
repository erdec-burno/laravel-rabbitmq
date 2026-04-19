<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexOrderRequest $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->withCount('processedMessages')
            ->when(
                $request->validated('status'),
                fn ($query, string $status) => $query->where('status', $status)
            )
            ->when(
                $request->validated('customer_email'),
                fn ($query, string $customerEmail) => $query->where('customer_email', 'like', '%'.$customerEmail.'%')
            )
            ->when(
                $request->validated('date_from'),
                fn ($query, string $dateFrom) => $query->whereDate('received_at', '>=', $dateFrom)
            )
            ->when(
                $request->validated('date_to'),
                fn ($query, string $dateTo) => $query->whereDate('received_at', '<=', $dateTo)
            )
            ->latest()
            ->paginate(15);

        return OrderResource::collection($orders);
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order): OrderResource
    {
        $order->load('processedMessages');

        return new OrderResource($order);
    }
}
