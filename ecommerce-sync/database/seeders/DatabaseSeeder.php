<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create test merchant/user
        $testUser = User::factory()->create([
            'name' => 'Test Merchant',
            'email' => 'merchant@test.com',
            'password' => bcrypt('password123'),
        ]);
    
        // Create some test products for the merchant
        \App\Models\Product::factory()->create([
            'user_id' => $testUser->id,
            'name' => 'Samsung Galaxy Phone',
            'description' => 'Latest Samsung smartphone with advanced features',
            'sku' => 'SAMSUNG-GALAXY-001',
            'price' => 899.99,
            'stock' => 50,
            'category' => 'Electronics',
            'brand' => 'Samsung',
            'status' => 'active',
            'image_url' => 'https://via.placeholder.com/300x300/0066CC/FFFFFF?text=Samsung+Galaxy'
        ]);
    
        \App\Models\Product::factory()->create([
            'user_id' => $testUser->id,
            'name' => 'Wireless Bluetooth Headphones',
            'description' => 'Premium wireless headphones with noise cancellation',
            'sku' => 'HEADPHONES-BT-002',
            'price' => 199.99,
            'stock' => 25,
            'category' => 'Electronics',
            'brand' => 'AudioTech',
            'status' => 'active',
            'image_url' => 'https://via.placeholder.com/300x300/FF6600/FFFFFF?text=Headphones'
        ]);
    
        \App\Models\Product::factory()->create([
            'user_id' => $testUser->id,
            'name' => 'Cotton T-Shirt',
            'description' => 'Comfortable 100% cotton t-shirt in various colors',
            'sku' => 'TSHIRT-COTTON-003',
            'price' => 29.99,
            'stock' => 100,
            'category' => 'Clothing',
            'brand' => 'ComfortWear',
            'status' => 'active',
            'image_url' => 'https://via.placeholder.com/300x300/009900/FFFFFF?text=T-Shirt'
        ]);
    
        $this->command->info('Test data created successfully!');
        $this->command->info('Login credentials:');
        $this->command->info('Email: merchant@test.com');
        $this->command->info('Password: password123');
    }

}
