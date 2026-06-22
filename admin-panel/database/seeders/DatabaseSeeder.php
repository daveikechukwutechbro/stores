<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $managerRole = Role::create(['name' => 'manager', 'guard_name' => 'web']);
        $supportRole = Role::create(['name' => 'support', 'guard_name' => 'web']);

        $permissions = [
            'view-orders', 'create-orders', 'edit-orders', 'delete-orders',
            'view-products', 'create-products', 'edit-products', 'delete-products',
            'view-categories', 'create-categories', 'edit-categories', 'delete-categories',
            'view-users', 'create-users', 'edit-users', 'delete-users',
        ];

        foreach ($permissions as $perm) {
            Permission::create(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole->givePermissionTo(Permission::all());
        $managerRole->givePermissionTo([
            'view-orders', 'edit-orders',
            'view-products', 'create-products', 'edit-products',
            'view-categories', 'create-categories', 'edit-categories',
        ]);
        $supportRole->givePermissionTo(['view-orders', 'edit-orders']);

        $user = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('admin');

        $categories = [
            ['name' => 'Electronics', 'description' => 'Gadgets, devices, and tech accessories', 'icon' => '🖥️', 'sort_order' => 1],
            ['name' => 'Clothing', 'description' => 'Apparel, fashion, and accessories', 'icon' => '👕', 'sort_order' => 2],
            ['name' => 'Books', 'description' => 'Physical and digital books', 'icon' => '📚', 'sort_order' => 3],
            ['name' => 'Home & Garden', 'description' => 'Home decor, furniture, and garden supplies', 'icon' => '🏠', 'sort_order' => 4],
            ['name' => 'Sports', 'description' => 'Sports equipment and activewear', 'icon' => '⚽', 'sort_order' => 5],
            ['name' => 'Toys & Games', 'description' => 'Toys, board games, and puzzles', 'icon' => '🎮', 'sort_order' => 6],
            ['name' => 'Food & Drinks', 'description' => 'Groceries, snacks, and beverages', 'icon' => '🍕', 'sort_order' => 7],
            ['name' => 'Beauty', 'description' => 'Skincare, makeup, and personal care', 'icon' => '💄', 'sort_order' => 8],
        ];

        foreach ($categories as $data) {
            Category::factory()->create($data);
        }

        $categoryIds = Category::pluck('id')->toArray();

        $products = [
            ['Wireless Bluetooth Headphones', 'Premium noise-cancelling headphones with 30h battery life', 25, 40, true],
            ['Smartphone Stand', 'Adjustable aluminum stand for phones and tablets', 8, null, false],
            ['USB-C Hub 7-in-1', 'Multi-port adapter with HDMI, USB, SD card reader', 15, null, true],
            ['Cotton T-Shirt', 'Comfortable 100% organic cotton t-shirt', 5, 10, false],
            ['Denim Jacket', 'Classic denim jacket with modern fit', 30, 50, true],
            ['Running Shoes', 'Lightweight mesh running shoes with cushioned sole', 20, null, true],
            ['JavaScript: The Good Parts', 'Classic programming book by Douglas Crockford', 3, null, false],
            ['Clean Code', 'Robert C. Martin\'s guide to writing better code', 4, 7, false],
            ['Scented Candle Set', 'Set of 3 soy wax candles, lavender, vanilla, rose', 6, null, false],
            ['Indoor Plant Pot', 'Ceramic planter with drainage, 8-inch', 4, null, false],
            ['Yoga Mat', 'Non-slip exercise mat, 6mm thick', 10, 15, false],
            ['Resistance Bands Set', 'Set of 5 bands for home workouts', 3, null, false],
            ['Board Game: Strategy', 'Award-winning strategy board game for 2-4 players', 7, null, false],
            ['Building Blocks 500pc', 'Creative building blocks set for ages 6+', 12, 18, true],
            ['Artisan Coffee Beans', 'Single origin Ethiopian coffee, 250g', 5, null, false],
            ['Green Tea Collection', 'Assorted premium green teas, 20 bags', 2, null, false],
            ['Face Moisturizer', 'Hydrating facial cream with SPF 30', 4, null, false],
            ['Lip Balm Set', 'Set of 6 natural lip balms', 1, null, false],
            ['Guitar Picks Pack', 'Assorted gauge guitar picks, 12-pack', 1, null, false],
            ['Phone Case - Silicone', 'Shockproof silicone case, various colors', 3, null, false],
        ];

        foreach ($products as [$name, $description, $price, $comparePrice, $featured]) {
            Product::factory()->create([
                'name' => $name,
                'slug' => Str::slug($name) . '-' . Str::random(6),
                'description' => $description,
                'price' => $price,
                'compare_price' => $comparePrice,
                'category_id' => $categoryIds[array_rand($categoryIds)],
                'featured' => $featured,
                'stock' => rand(5, 50),
                'rating' => fake()->randomFloat(1, 3.5, 5.0),
                'review_count' => rand(0, 200),
            ]);
        }
    }
}
