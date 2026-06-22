<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(rand(2, 4), true);

        return [
            'slug' => Str::slug($name) . '-' . Str::random(6),
            'name' => ucfirst($name),
            'description' => fake()->paragraphs(rand(1, 3), true),
            'price' => fake()->numberBetween(1, 100),
            'compare_price' => fake()->optional(0.3)->numberBetween(50, 200),
            'image' => null,
            'images' => null,
            'stock' => fake()->numberBetween(0, 100),
            'color' => fake()->optional(0.7)->randomElement(['Red', 'Blue', 'Green', 'Black', 'White', 'Gray', 'Yellow', 'Purple', 'Orange', 'Pink', 'Brown', 'Navy', 'Teal', 'Maroon']),
            'category_id' => Category::factory(),
            'featured' => fake()->boolean(20),
            'is_active' => true,
            'rating' => fake()->randomFloat(1, 1, 5),
            'review_count' => fake()->numberBetween(0, 500),
        ];
    }
}
