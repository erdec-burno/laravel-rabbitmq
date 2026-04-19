<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProcessedOrderMessageController;
use Illuminate\Support\Facades\Route;

Route::apiResource('orders', OrderController::class)->only(['index', 'show']);
Route::apiResource('processed-order-messages', ProcessedOrderMessageController::class)->only(['index', 'show']);
