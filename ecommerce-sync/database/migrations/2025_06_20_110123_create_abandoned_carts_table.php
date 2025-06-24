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
        Schema::create('abandoned_carts', function (Blueprint $table) {
            $table->id();
            $table->string('customer_email');
            $table->string('customer_name')->nullable();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2); // Price at the time of cart abandonment
            $table->string('session_id')->nullable();
            $table->timestamp('abandoned_at');
            $table->timestamp('reminder_sent_at')->nullable();
            $table->enum('reminder_status', ['pending', 'sent', 'clicked', 'purchased'])->default('pending');
            $table->timestamp('expires_at')->nullable(); // When the cart data should be cleaned up
            $table->json('additional_data')->nullable(); // Store any additional tracking data
            $table->timestamps();
            
            $table->index(['customer_email', 'abandoned_at']);
            $table->index(['reminder_status', 'abandoned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abandoned_carts');
    }
};
