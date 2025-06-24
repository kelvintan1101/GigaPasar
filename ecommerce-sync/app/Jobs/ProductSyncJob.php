<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Product;
use App\Models\PlatformConnection;
use App\Models\SyncLog;
use App\Services\LazadaApiService;
use Exception;
use Throwable;
use Illuminate\Support\Facades\Log;

class ProductSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Product $product;
    public string $action;
    public array $syncData;

    /**
     * Create a new job instance.
     */
    public function __construct(Product $product, string $action = 'create', array $syncData = [])
    {
        $this->product = $product;
        $this->action = $action; // 'create', 'update', 'delete', 'stock_update'
        $this->syncData = $syncData;
    }

    /**
     * Execute the job.
     */
    public function handle(LazadaApiService $lazadaService): void
    {
        Log::info('Starting product sync job', [
            'product_id' => $this->product->id,
            'action' => $this->action,
            'merchant_id' => $this->product->merchant_id
        ]);

        try {
            // Get active Lazada connection for this merchant
            $connection = PlatformConnection::where('merchant_id', $this->product->merchant_id)
                ->where('platform_name', 'lazada')
                ->where('status', 'active')
                ->first();

            if (!$connection) {
                throw new Exception('No active Lazada connection found for merchant');
            }

            // Validate and refresh connection if needed
            if (!$lazadaService->validateAndRefreshConnection($connection)) {
                throw new Exception('Failed to validate Lazada connection');
            }

            $result = match($this->action) {
                'create' => $this->createProduct($lazadaService, $connection),
                'update' => $this->updateProduct($lazadaService, $connection),
                'stock_update' => $this->updateStock($lazadaService, $connection),
                'delete' => $this->deleteProduct($lazadaService, $connection),
                default => throw new Exception("Unsupported sync action: {$this->action}")
            };

            // Update product sync data
            $this->updateProductSyncStatus($result, 'success');

            // Log successful sync
            $this->logSyncResult('success', 'Product synced successfully', $result);

            Log::info('Product sync job completed successfully', [
                'product_id' => $this->product->id,
                'action' => $this->action
            ]);

        } catch (Exception $e) {
            // Update product sync status with error
            $this->updateProductSyncStatus([], 'error', $e->getMessage());

            // Log failed sync
            $this->logSyncResult('failed', $e->getMessage(), []);

            Log::error('Product sync job failed', [
                'product_id' => $this->product->id,
                'action' => $this->action,
                'error' => $e->getMessage()
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Create product on Lazada
     */
    private function createProduct(LazadaApiService $lazadaService, PlatformConnection $connection): array
    {
        $productData = $this->prepareProductData();
        $lazadaProductData = $lazadaService->transformProductForLazada($productData);
        
        return $lazadaService->createProduct($lazadaProductData, $connection->access_token);
    }

    /**
     * Update product on Lazada
     */
    private function updateProduct(LazadaApiService $lazadaService, PlatformConnection $connection): array
    {
        $productData = $this->prepareProductData();
        $lazadaProductData = $lazadaService->transformProductForLazada($productData);
        
        // Add existing item ID for update
        if (isset($this->product->lazada_sync_data['item_id'])) {
            $lazadaProductData['Request']['Product']['ItemId'] = $this->product->lazada_sync_data['item_id'];
        }
        
        return $lazadaService->updateProduct($lazadaProductData, $connection->access_token);
    }

    /**
     * Update product stock on Lazada
     */
    private function updateStock(LazadaApiService $lazadaService, PlatformConnection $connection): array
    {
        if (!isset($this->product->lazada_sync_data['seller_sku'])) {
            throw new Exception('Product not synced with Lazada yet');
        }

        $stockData = [
            'Request' => [
                'Product' => [
                    'Skus' => [
                        [
                            'SellerSku' => $this->product->lazada_sync_data['seller_sku'],
                            'quantity' => $this->product->stock,
                            'price' => $this->product->price
                        ]
                    ]
                ]
            ]
        ];
        
        return $lazadaService->updateProductStock($stockData, $connection->access_token);
    }

    /**
     * Delete product from Lazada (deactivate)
     */
    private function deleteProduct(LazadaApiService $lazadaService, PlatformConnection $connection): array
    {
        // Lazada doesn't support direct deletion, so we deactivate the product
        $productData = $this->prepareProductData();
        $productData['status'] = 'inactive';
        
        $lazadaProductData = $lazadaService->transformProductForLazada($productData);
        
        if (isset($this->product->lazada_sync_data['item_id'])) {
            $lazadaProductData['Request']['Product']['ItemId'] = $this->product->lazada_sync_data['item_id'];
        }
        
        return $lazadaService->updateProduct($lazadaProductData, $connection->access_token);
    }

    /**
     * Prepare product data for sync
     */
    private function prepareProductData(): array
    {
        return array_merge([
            'name' => $this->product->name,
            'description' => $this->product->description,
            'price' => $this->product->price,
            'sku' => $this->product->sku,
            'stock' => $this->product->stock,
            'image_url' => $this->product->image_url,
            'status' => $this->product->status,
        ], $this->syncData);
    }

    /**
     * Update product sync status
     */
    private function updateProductSyncStatus(array $result, string $status, string $errorMessage = null): void
    {
        $syncData = $this->product->lazada_sync_data ?? [];
        
        if ($status === 'success' && !empty($result)) {
            // Extract useful data from Lazada response
            if (isset($result['data']['item_id'])) {
                $syncData['item_id'] = $result['data']['item_id'];
            }
            if (isset($result['data']['sku_id'])) {
                $syncData['sku_id'] = $result['data']['sku_id'];
            }
            $syncData['seller_sku'] = $this->product->sku;
            $syncData['last_sync_action'] = $this->action;
            $syncData['last_sync_status'] = 'success';
            unset($syncData['last_error']);
        } else {
            $syncData['last_sync_status'] = 'error';
            $syncData['last_error'] = $errorMessage;
        }
        
        $this->product->update([
            'lazada_sync_data' => $syncData,
            'last_synced_at' => now()
        ]);
    }

    /**
     * Log sync result
     */
    private function logSyncResult(string $status, string $message, array $responseData): void
    {
        SyncLog::create([
            'merchant_id' => $this->product->merchant_id,
            'action_type' => "product_{$this->action}",
            'platform_name' => 'lazada',
            'status' => $status,
            'message' => $message,
            'request_data' => [
                'product_id' => $this->product->id,
                'action' => $this->action,
                'sync_data' => $this->syncData
            ],
            'response_data' => $responseData,
            'affected_items' => 1,
            'duration' => 0 // This would be calculated if we tracked start time
        ]);
    }

    /**
     * Handle job failure
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Product sync job failed permanently', [
            'product_id' => $this->product->id,
            'action' => $this->action,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Mark product sync as failed
        $this->updateProductSyncStatus([], 'failed', $exception->getMessage());
    }
}

