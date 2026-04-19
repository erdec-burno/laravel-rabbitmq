<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('processed_order_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('message_id')->unique();
            $table->string('message_type');
            $table->uuid('order_id');
            $table->string('customer_email');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->timestamp('occurred_at');
            $table->timestamp('processed_at');
            $table->json('payload');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed_order_messages');
    }
};
