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
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('cascade');
            $table->string('webhook_key')->unique();
            $table->enum('status', ['pending', 'processed'])->default('pending');
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();

            $table->index(['status', 'order_id']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
