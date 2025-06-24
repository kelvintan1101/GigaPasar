<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProductSyncJob;
use App\Models\Product;
use App\Models\PlatformConnection;
use App\Models\SyncLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class ProductSyncController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Sync a single product to Lazada.
     */
    public function syncProduct(Request $request, int $productId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:create,update,stock_update,delete',
            'sync_data' => 'sometimes|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $merchant = Auth::user();
            
            $product = Product::where('id', $productId)
                ->where('merchant_id', $merchant->id)
                ->firstOrFail();

            // Check if merchant has active Lazada connection
            $connection = PlatformConnection::where('merchant_id', $merchant->id)
                ->where('platform_name', 'lazada')
                ->where('status', 'active')
                ->first();

            if (!$connection) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No active Lazada connection found',
                    'error' => 'Please connect your Lazada account first'
                ], 400);
            }

            // Dispatch sync job
            ProductSyncJob::dispatch(
                $product,
                $request->input('action'),
                $request->input('sync_data', [])
            );

            Log::info('Product sync job dispatched', [
                'product_id' => $productId,
                'action' => $request->input('action'),
                'merchant_id' => $merchant->id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Product sync job has been queued',
                'data' => [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'action' => $request->input('action'),
                    'queued_at' => now()->toISOString()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to queue product sync', [
                'product_id' => $productId,
                'merchant_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to queue product sync',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync multiple products in bulk.
     */
    public function bulkSync(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array|min:1|max:50',
            'product_ids.*' => 'integer|exists:products,id',
            'action' => 'required|in:create,update,stock_update,delete',
            'sync_data' => 'sometimes|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $merchant = Auth::user();
            
            // Check if merchant has active Lazada connection
            $connection = PlatformConnection::where('merchant_id', $merchant->id)
                ->where('platform_name', 'lazada')
                ->where('status', 'active')
                ->first();

            if (!$connection) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No active Lazada connection found',
                    'error' => 'Please connect your Lazada account first'
                ], 400);
            }

            // Get products that belong to this merchant
            $products = Product::whereIn('id', $request->product_ids)
                ->where('merchant_id', $merchant->id)
                ->get();

            if ($products->count() !== count($request->product_ids)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Some products not found or do not belong to you',
                ], 400);
            }

            $queuedJobs = [];
            $action = $request->input('action');
            $syncData = $request->input('sync_data', []);

            // Dispatch sync jobs for each product
            foreach ($products as $product) {
                ProductSyncJob::dispatch($product, $action, $syncData);
                
                $queuedJobs[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku
                ];
            }

            Log::info('Bulk product sync jobs dispatched', [
                'product_count' => $products->count(),
                'action' => $action,
                'merchant_id' => $merchant->id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Successfully queued {$products->count()} product sync jobs",
                'data' => [
                    'action' => $action,
                    'total_products' => $products->count(),
                    'queued_products' => $queuedJobs,
                    'queued_at' => now()->toISOString()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to queue bulk product sync', [
                'merchant_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to queue bulk product sync',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync status for a product.
     */
    public function getSyncStatus(int $productId): JsonResponse
    {
        try {
            $merchant = Auth::user();
            
            $product = Product::where('id', $productId)
                ->where('merchant_id', $merchant->id)
                ->firstOrFail();

            $syncLogs = SyncLog::where('merchant_id', $merchant->id)
                ->where('request_data->product_id', $productId)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Sync status retrieved successfully',
                'data' => [
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'lazada_sync_data' => $product->lazada_sync_data,
                        'last_synced_at' => $product->last_synced_at,
                        'is_synced' => $product->isSyncedWithLazada(),
                    ],
                    'sync_logs' => $syncLogs,
                    'sync_summary' => [
                        'total_syncs' => $syncLogs->count(),
                        'successful_syncs' => $syncLogs->where('status', 'success')->count(),
                        'failed_syncs' => $syncLogs->where('status', 'failed')->count(),
                        'last_sync_status' => $syncLogs->first()?->status,
                        'last_sync_at' => $syncLogs->first()?->created_at,
                    ]
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get sync status', [
                'product_id' => $productId,
                'merchant_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get sync status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync statistics for the merchant.
     */
    public function getSyncStatistics(): JsonResponse
    {
        try {
            $merchant = Auth::user();
            
            $totalProducts = Product::where('merchant_id', $merchant->id)->count();
            $syncedProducts = Product::where('merchant_id', $merchant->id)
                ->whereNotNull('last_synced_at')
                ->whereJsonContains('lazada_sync_data->item_id', '!=', null)
                ->count();

            $recentSyncs = SyncLog::where('merchant_id', $merchant->id)
                ->where('created_at', '>=', now()->subDay())
                ->count();

            $successfulSyncs = SyncLog::where('merchant_id', $merchant->id)
                ->where('created_at', '>=', now()->subDay())
                ->where('status', 'success')
                ->count();

            $failedSyncs = SyncLog::where('merchant_id', $merchant->id)
                ->where('created_at', '>=', now()->subDay())
                ->where('status', 'failed')
                ->count();

            $syncsByAction = SyncLog::where('merchant_id', $merchant->id)
                ->where('created_at', '>=', now()->subWeek())
                ->selectRaw('action_type, COUNT(*) as count')
                ->groupBy('action_type')
                ->get()
                ->pluck('count', 'action_type');

            return response()->json([
                'status' => 'success',
                'message' => 'Sync statistics retrieved successfully',
                'data' => [
                    'products' => [
                        'total' => $totalProducts,
                        'synced' => $syncedProducts,
                        'unsynced' => $totalProducts - $syncedProducts,
                        'sync_percentage' => $totalProducts > 0 ? round(($syncedProducts / $totalProducts) * 100, 2) : 0
                    ],
                    'recent_activity' => [
                        'total_syncs_24h' => $recentSyncs,
                        'successful_syncs_24h' => $successfulSyncs,
                        'failed_syncs_24h' => $failedSyncs,
                        'success_rate_24h' => $recentSyncs > 0 ? round(($successfulSyncs / $recentSyncs) * 100, 2) : 0
                    ],
                    'sync_by_action_7d' => $syncsByAction
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get sync statistics', [
                'merchant_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get sync statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent sync logs.
     */
    public function getSyncLogs(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'platform' => 'sometimes|string|in:lazada,shopee,tokopedia',
            'status' => 'sometimes|string|in:success,failed,error,pending',
            'action_type' => 'sometimes|string',
            'limit' => 'sometimes|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $merchant = Auth::user();
            $limit = $request->input('limit', 20);
            
            $query = SyncLog::where('merchant_id', $merchant->id)
                ->orderBy('created_at', 'desc');

            if ($request->has('platform')) {
                $query->where('platform_name', $request->platform);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('action_type')) {
                $query->where('action_type', $request->action_type);
            }

            $logs = $query->paginate($limit);

            return response()->json([
                'status' => 'success',
                'message' => 'Sync logs retrieved successfully',
                'data' => $logs->items(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get sync logs', [
                'merchant_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get sync logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}