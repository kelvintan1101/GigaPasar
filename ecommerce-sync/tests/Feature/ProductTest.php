<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private Merchant $merchant;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->merchant = Merchant::factory()->create();
        $this->token = $this->merchant->createToken('test-token')->plainTextToken;
    }

    /**
     * Test merchant can create a product with valid data.
     */
    public function test_merchant_can_create_product_with_valid_data(): void
    {
        $productData = [
            'name' => 'Test Product',
            'description' => 'This is a test product',
            'price' => 99.99,
            'sku' => 'TEST-SKU-001',
            'stock' => 50,
            'image_url' => 'https://example.com/image.jpg',
            'status' => 'active'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/products', $productData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'merchant_id',
                    'name',
                    'description',
                    'price',
                    'sku',
                    'stock',
                    'image_url',
                    'status',
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJson([
                'status' => 'success',
                'message' => 'Product created successfully',
                'data' => [
                    'merchant_id' => $this->merchant->id,
                    'name' => 'Test Product',
                    'price' => '99.99',
                    'sku' => 'TEST-SKU-001',
                    'stock' => 50,
                    'status' => 'active'
                ]
            ]);

        $this->assertDatabaseHas('products', [
            'merchant_id' => $this->merchant->id,
            'name' => 'Test Product',
            'sku' => 'TEST-SKU-001',
            'price' => 99.99,
            'stock' => 50
        ]);
    }

    /**
     * Test product creation fails with invalid data.
     */
    public function test_product_creation_fails_with_invalid_data(): void
    {
        $invalidData = [
            'name' => '', // Required field empty
            'price' => -10, // Negative price
            'sku' => '', // Required field empty
            'stock' => -5, // Negative stock
            'status' => 'invalid_status' // Invalid status
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/products', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'status',
                'message',
                'errors'
            ])
            ->assertJson([
                'status' => 'error',
                'message' => 'Validation failed'
            ]);
    }

    /**
     * Test product creation fails with duplicate SKU.
     */
    public function test_product_creation_fails_with_duplicate_sku(): void
    {
        // Create first product
        Product::factory()->create([
            'merchant_id' => $this->merchant->id,
            'sku' => 'DUPLICATE-SKU'
        ]);

        $productData = [
            'name' => 'Second Product',
            'price' => 50.00,
            'sku' => 'DUPLICATE-SKU', // Same SKU
            'stock' => 10,
            'status' => 'active'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/products', $productData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'status',
                'message',
                'errors' => [
                    'sku'
                ]
            ]);
    }

    /**
     * Test merchant can list their products.
     */
    public function test_merchant_can_list_their_products(): void
    {
        // Create some products for this merchant
        $products = Product::factory()->count(3)->create([
            'merchant_id' => $this->merchant->id
        ]);

        // Create a product for another merchant (should not be returned)
        $otherMerchant = Merchant::factory()->create();
        Product::factory()->create([
            'merchant_id' => $otherMerchant->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'merchant_id',
                        'name',
                        'description',
                        'price',
                        'sku',
                        'stock',
                        'status'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ])
            ->assertJson([
                'status' => 'success',
                'message' => 'Products retrieved successfully'
            ]);

        // Verify only this merchant's products are returned
        $responseData = $response->json('data');
        $this->assertCount(3, $responseData);
        
        foreach ($responseData as $product) {
            $this->assertEquals($this->merchant->id, $product['merchant_id']);
        }
    }

    /**
     * Test product list with search functionality.
     */
    public function test_product_list_with_search(): void
    {
        Product::factory()->create([
            'merchant_id' => $this->merchant->id,
            'name' => 'iPhone 13',
            'sku' => 'IPHONE-13'
        ]);

        Product::factory()->create([
            'merchant_id' => $this->merchant->id,
            'name' => 'Samsung Galaxy',
            'sku' => 'SAMSUNG-GALAXY'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/products?search=iPhone');

        $response->assertStatus(200);
        
        $products = $response->json('data');
        $this->assertCount(1, $products);
        $this->assertEquals('iPhone 13', $products[0]['name']);
    }

    /**
     * Test product list with status filter.
     */
    public function test_product_list_with_status_filter(): void
    {
        Product::factory()->create([
            'merchant_id' => $this->merchant->id,
            'status' => 'active'
        ]);

        Product::factory()->create([
            'merchant_id' => $this->merchant->id,
            'status' => 'inactive'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/products?status=active');

        $response->assertStatus(200);
        
        $products = $response->json('data');
        $this->assertCount(1, $products);
        $this->assertEquals('active', $products[0]['status']);
    }

    /**
     * Test merchant can view a specific product.
     */
    public function test_merchant_can_view_specific_product(): void
    {
        $product = Product::factory()->create([
            'merchant_id' => $this->merchant->id,
            'name' => 'Specific Product'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'merchant_id',
                    'name',
                    'description',
                    'price',
                    'sku',
                    'stock',
                    'status'
                ]
            ])
            ->assertJson([
                'status' => 'success',
                'message' => 'Product retrieved successfully',
                'data' => [
                    'id' => $product->id,
                    'name' => 'Specific Product',
                    'merchant_id' => $this->merchant->id
                ]
            ]);
    }

    /**
     * Test merchant cannot view another merchant's product.
     */
    public function test_merchant_cannot_view_another_merchants_product(): void
    {
        $otherMerchant = Merchant::factory()->create();
        $product = Product::factory()->create([
            'merchant_id' => $otherMerchant->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Product not found'
            ]);
    }

    /**
     * Test merchant can update their product.
     */
    public function test_merchant_can_update_their_product(): void
    {
        $product = Product::factory()->create([
            'merchant_id' => $this->merchant->id,
            'name' => 'Original Name',
            'price' => 50.00
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'price' => 75.00,
            'stock' => 100
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/v1/products/{$product->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Product updated successfully',
                'data' => [
                    'id' => $product->id,
                    'name' => 'Updated Name',
                    'price' => '75.00',
                    'stock' => 100
                ]
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
            'price' => 75.00,
            'stock' => 100
        ]);
    }

    /**
     * Test merchant cannot update another merchant's product.
     */
    public function test_merchant_cannot_update_another_merchants_product(): void
    {
        $otherMerchant = Merchant::factory()->create();
        $product = Product::factory()->create([
            'merchant_id' => $otherMerchant->id
        ]);

        $updateData = [
            'name' => 'Hacked Product'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/v1/products/{$product->id}", $updateData);

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Product not found'
            ]);
    }

    /**
     * Test merchant can delete their product.
     */
    public function test_merchant_can_delete_their_product(): void
    {
        $product = Product::factory()->create([
            'merchant_id' => $this->merchant->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Product deleted successfully'
            ]);

        $this->assertSoftDeleted('products', [
            'id' => $product->id
        ]);
    }

    /**
     * Test merchant cannot delete another merchant's product.
     */
    public function test_merchant_cannot_delete_another_merchants_product(): void
    {
        $otherMerchant = Merchant::factory()->create();
        $product = Product::factory()->create([
            'merchant_id' => $otherMerchant->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Product not found'
            ]);

        // Verify product still exists
        $this->assertDatabaseHas('products', [
            'id' => $product->id
        ]);
    }

    /**
     * Test merchant can update product stock.
     */
    public function test_merchant_can_update_product_stock(): void
    {
        $product = Product::factory()->create([
            'merchant_id' => $this->merchant->id,
            'stock' => 50
        ]);

        $stockData = [
            'stock' => 100,
            'action' => 'set'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/v1/products/{$product->id}/stock", $stockData);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Stock updated successfully',
                'data' => [
                    'product_id' => $product->id,
                    'previous_stock' => 50,
                    'current_stock' => 100,
                    'action' => 'set'
                ]
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 100
        ]);
    }

    /**
     * Test merchant can increase product stock.
     */
    public function test_merchant_can_increase_product_stock(): void
    {
        $product = Product::factory()->create([
            'merchant_id' => $this->merchant->id,
            'stock' => 50
        ]);

        $stockData = [
            'stock' => 25,
            'action' => 'increase'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/v1/products/{$product->id}/stock", $stockData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 75 // 50 + 25
        ]);
    }

    /**
     * Test merchant can decrease product stock.
     */
    public function test_merchant_can_decrease_product_stock(): void
    {
        $product = Product::factory()->create([
            'merchant_id' => $this->merchant->id,
            'stock' => 50
        ]);

        $stockData = [
            'stock' => 20,
            'action' => 'decrease'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/v1/products/{$product->id}/stock", $stockData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 30 // 50 - 20
        ]);
    }

    /**
     * Test stock decrease fails when insufficient stock.
     */
    public function test_stock_decrease_fails_when_insufficient_stock(): void
    {
        $product = Product::factory()->create([
            'merchant_id' => $this->merchant->id,
            'stock' => 10
        ]);

        $stockData = [
            'stock' => 20, // More than available
            'action' => 'decrease'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/v1/products/{$product->id}/stock", $stockData);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Insufficient stock for this operation'
            ]);

        // Verify stock unchanged
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 10
        ]);
    }

    /**
     * Test merchant can bulk update product status.
     */
    public function test_merchant_can_bulk_update_product_status(): void
    {
        $products = Product::factory()->count(3)->create([
            'merchant_id' => $this->merchant->id,
            'status' => 'active'
        ]);

        $bulkData = [
            'product_ids' => $products->pluck('id')->toArray(),
            'status' => 'inactive'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->patchJson('/api/v1/products/bulk-status', $bulkData);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => '3 products updated successfully',
                'data' => [
                    'updated_count' => 3,
                    'status' => 'inactive'
                ]
            ]);

        foreach ($products as $product) {
            $this->assertDatabaseHas('products', [
                'id' => $product->id,
                'status' => 'inactive'
            ]);
        }
    }

    /**
     * Test merchant can get product statistics.
     */
    public function test_merchant_can_get_product_statistics(): void
    {
        // Create products with different statuses
        Product::factory()->count(5)->create([
            'merchant_id' => $this->merchant->id,
            'status' => 'active',
            'stock' => 100,
            'price' => 50.00
        ]);

        Product::factory()->count(2)->create([
            'merchant_id' => $this->merchant->id,
            'status' => 'inactive',
            'stock' => 0
        ]);

        Product::factory()->create([
            'merchant_id' => $this->merchant->id,
            'status' => 'draft',
            'stock' => 5
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/products-statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'total_products',
                    'active_products',
                    'inactive_products',
                    'draft_products',
                    'out_of_stock',
                    'low_stock',
                    'total_value'
                ]
            ])
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'total_products' => 8,
                    'active_products' => 5,
                    'inactive_products' => 2,
                    'draft_products' => 1,
                    'out_of_stock' => 2,
                    'low_stock' => 1
                ]
            ]);
    }

    /**
     * Test unauthenticated access to product endpoints fails.
     */
    public function test_unauthenticated_access_to_product_endpoints_fails(): void
    {
        $responses = [
            $this->getJson('/api/v1/products'),
            $this->postJson('/api/v1/products', []),
            $this->getJson('/api/v1/products/1'),
            $this->putJson('/api/v1/products/1', []),
            $this->deleteJson('/api/v1/products/1'),
            $this->getJson('/api/v1/products-statistics')
        ];

        foreach ($responses as $response) {
            $response->assertStatus(401);
        }
    }
}