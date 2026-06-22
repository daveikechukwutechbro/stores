<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->integer('total_amount');
            $table->string('shipping_address')->nullable();
            $table->string('phone')->nullable();
            $table->text('note')->nullable();
            $table->string('transaction_id')->nullable()->unique();
            $table->string('telegram_charge_id')->nullable();
            $table->string('payment_method')->default('telegram_stars');
            $table->timestamp('estimated_delivery')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('order_number');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
