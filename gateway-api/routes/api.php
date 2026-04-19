<?php

use App\Http\Controllers\Api\OrderDispatchController;
use Illuminate\Support\Facades\Route;

Route::post('/orders', OrderDispatchController::class)->name('orders.store');
