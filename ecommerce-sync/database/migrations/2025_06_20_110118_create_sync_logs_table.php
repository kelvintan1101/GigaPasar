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
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->onDelete('cascade');
            $table->enum('action_type', ['product_sync', 'order_sync', 'inventory_sync', 'auth_refresh']);
            $table->enum('platform_name', ['lazada', 'shopee', 'tokopedia']);
            $table->enum('status', ['success', 'failed', 'pending', 'partial']);
            $table->text('message')->nullable();
            $table->json('request_data')->nullable(); // Store request payload
            $table->json('response_data')->nullable(); // Store API response
            $table->integer('affected_items')->default(0); // Number of items processed
            $table->decimal('duration', 8, 3)->nullable(); // Execution time in seconds
            $table->timestamps();
            
            $table->index(['merchant_id', 'status']);
            $table->index(['platform_name', 'action_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
