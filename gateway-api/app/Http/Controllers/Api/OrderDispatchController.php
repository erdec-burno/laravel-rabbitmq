<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderDispatchResource;
use App\Services\RabbitMqOrderPublisher;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class OrderDispatchController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(StoreOrderRequest $request, RabbitMqOrderPublisher $publisher): JsonResponse
    {
        $dispatch = $publisher->publish($request->validated());

        return (new OrderDispatchResource($dispatch))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
