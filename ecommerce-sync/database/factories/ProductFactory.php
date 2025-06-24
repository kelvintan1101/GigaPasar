<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'price' => $this->faker->randomFloat(2, 1, 1000),
            'sku' => strtoupper($this->faker->unique()->lexify('???-???-###')),
            'stock' => $this->faker->numberBetween(0, 500),
            'image_url' => $this->faker->imageUrl(400, 400, 'products'),
            'status' => $this->faker->randomElement(['active', 'inactive', 'draft']),
            'lazada_sync_data' => null,
            'last_synced_at' => null,
        ];
    }

    /**
     * Indicate that the product is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Indicate that the product is in draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    /**
     * Indicate that the product is out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }

    /**
     * Indicate that the product has low stock.
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => $this->faker->numberBetween(1, 10),
        ]);
    }

    /**
     * Indicate that the product is in stock.
     */
    public function inStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => $this->faker->numberBetween(50, 500),
        ]);
    }

    /**
     * Indicate that the product is synced with Lazada.
     */
    public function syncedWithLazada(): static
    {
        return $this->state(fn (array $attributes) => [
            'lazada_sync_data' => [
                'item_id' => $this->faker->randomNumber(8),
                'seller_sku' => $attributes['sku'],
                'status' => 'active',
                'synced_at' => now()->toISOString(),
            ],
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Indicate that the product has a specific merchant.
     */
    public function forMerchant(Merchant $merchant): static
    {
        return $this->state(fn (array $attributes) => [
            'merchant_id' => $merchant->id,
        ]);
    }

    /**
     * Configure the model factory for electronics products.
     */
    public function electronics(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->randomElement([
                'iPhone 15 Pro Max',
                'Samsung Galaxy S24',
                'MacBook Pro M3',
                'Dell XPS 13',
                'Sony WH-1000XM5',
                'iPad Air',
                'Nintendo Switch',
                'AirPods Pro',
                'Apple Watch Series 9',
                'Surface Pro 9'
            ]),
            'description' => 'High-quality electronics product with advanced features and excellent performance.',
            'price' => $this->faker->randomFloat(2, 100, 3000),
            'sku' => 'ELEC-' . strtoupper($this->faker->unique()->lexify('???-###')),
        ]);
    }

    /**
     * Configure the model factory for clothing products.
     */
    public function clothing(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->randomElement([
                'Cotton T-Shirt',
                'Denim Jeans',
                'Hoodie Sweatshirt',
                'Running Shoes',
                'Leather Jacket',
                'Summer Dress',
                'Sports Shorts',
                'Winter Coat',
                'Sneakers',
                'Business Shirt'
            ]),
            'description' => 'Comfortable and stylish clothing item made from premium materials.',
            'price' => $this->faker->randomFloat(2, 20, 300),
            'sku' => 'CLOTH-' . strtoupper($this->faker->unique()->lexify('???-###')),
        ]);
    }

    /**
     * Configure the model factory for home products.
     */
    public function home(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->randomElement([
                'Coffee Maker',
                'Air Purifier',
                'Table Lamp',
                'Throw Pillow',
                'Wall Clock',
                'Plant Pot',
                'Kitchen Scale',
                'Storage Box',
                'Desk Organizer',
                'Candle Set'
            ]),
            'description' => 'Essential home item to enhance your living space and daily comfort.',
            'price' => $this->faker->randomFloat(2, 15, 500),
            'sku' => 'HOME-' . strtoupper($this->faker->unique()->lexify('???-###')),
        ]);
    }
}