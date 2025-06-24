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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->onDelete('cascade');
            $table->string('platform_order_id'); // Lazada order ID
            $table->enum('platform_name', ['lazada', 'shopee', 'tokopedia'])->default('lazada');
            $table->string('customer_email');
            $table->string('customer_name');
            $table->text('customer_address')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('shipping_fee', 8, 2)->default(0);
            $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->json('order_items'); // Store order items data
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('platform_created_at')->nullable();
            $table->timestamps();
            
            $table->unique(['platform_order_id', 'platform_name']);
            $table->index(['merchant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
