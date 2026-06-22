<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description');
            $table->integer('price');
            $table->integer('compare_price')->nullable();
            $table->string('image')->nullable();
            $table->json('images')->nullable();
            $table->integer('stock')->default(0);
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->boolean('featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->decimal('rating', 3, 1)->default(0);
            $table->integer('review_count')->default(0);
            $table->timestamps();

            $table->index('category_id');
            $table->index('slug');
            $table->index('featured');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
