<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Display a listing of the products.
     */
    public function index(Request $request): JsonResponse
    {
        $merchant = Auth::user();
        
        $query = Product::forMerchant($merchant->id);

        // Apply search filter
        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        // Apply status filter
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Apply stock filter
        if ($request->has('stock_filter')) {
            switch ($request->stock_filter) {
                case 'in_stock':
                    $query->where('stock', '>', 0);
                    break;
                case 'low_stock':
                    $query->where('stock', '>', 0)->where('stock', '<=', 10);
                    break;
                case 'out_of_stock':
                    $query->where('stock', '<=', 0);
                    break;
            }
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate results
        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Products retrieved successfully',
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ]
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $merchant = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'sku' => 'required|string|max:100|unique:products,sku',
            'stock' => 'required|integer|min:0',
            'image_url' => 'nullable|url',
            'status' => 'required|in:active,inactive,draft',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product = Product::create([
                'merchant_id' => $merchant->id,
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'sku' => $request->sku,
                'stock' => $request->stock,
                'image_url' => $request->image_url,
                'status' => $request->status,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Product created successfully',
                'data' => $product
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified product.
     */
    public function show(string $id): JsonResponse
    {
        $merchant = Auth::user();

        $product = Product::forMerchant($merchant->id)->find($id);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Product retrieved successfully',
            'data' => $product
        ]);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $merchant = Auth::user();

        $product = Product::forMerchant($merchant->id)->find($id);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'sku' => 'sometimes|required|string|max:100|unique:products,sku,' . $product->id,
            'stock' => 'sometimes|required|integer|min:0',
            'image_url' => 'nullable|url',
            'status' => 'sometimes|required|in:active,inactive,draft',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product->update($request->only([
                'name', 'description', 'price', 'sku', 'stock', 'image_url', 'status'
            ]));

            return response()->json([
                'status' => 'success',
                'message' => 'Product updated successfully',
                'data' => $product->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $merchant = Auth::user();

        $product = Product::forMerchant($merchant->id)->find($id);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found'
            ], 404);
        }

        try {
            $product->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Product deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update product stock.
     */
    public function updateStock(Request $request, string $id): JsonResponse
    {
        $merchant = Auth::user();

        $product = Product::forMerchant($merchant->id)->find($id);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'stock' => 'required|integer|min:0',
            'action' => 'sometimes|in:set,increase,decrease'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $action = $request->get('action', 'set');
            $stock = $request->stock;

            switch ($action) {
                case 'increase':
                    $success = $product->increaseStock($stock);
                    break;
                case 'decrease':
                    $success = $product->reduceStock($stock);
                    break;
                default:
                    $success = $product->updateStock($stock);
                    break;
            }

            if (!$success && $action === 'decrease') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient stock for this operation'
                ], 400);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Stock updated successfully',
                'data' => [
                    'product_id' => $product->id,
                    'previous_stock' => $product->getOriginal('stock'),
                    'current_stock' => $product->stock,
                    'action' => $action
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update products status.
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $merchant = Auth::user();

        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array',
            'product_ids.*' => 'integer|exists:products,id',
            'status' => 'required|in:active,inactive,draft'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updated = Product::forMerchant($merchant->id)
                ->whereIn('id', $request->product_ids)
                ->update(['status' => $request->status]);

            return response()->json([
                'status' => 'success',
                'message' => "{$updated} products updated successfully",
                'data' => [
                    'updated_count' => $updated,
                    'status' => $request->status
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
         * Get product statistics for the merchant.
         */
        public function statistics(): JsonResponse
        {
            $merchant = Auth::user();
    
            try {
                $stats = [
                    'total_products' => Product::forMerchant($merchant->id)->count(),
                    'active_products' => Product::forMerchant($merchant->id)->where('status', 'active')->count(),
                    'inactive_products' => Product::forMerchant($merchant->id)->where('status', 'inactive')->count(),
                    'draft_products' => Product::forMerchant($merchant->id)->where('status', 'draft')->count(),
                    'out_of_stock' => Product::forMerchant($merchant->id)->where('stock', '<=', 0)->count(),
                    'low_stock' => Product::forMerchant($merchant->id)->where('stock', '>', 0)->where('stock', '<=', 10)->count(),
                    'total_value' => Product::forMerchant($merchant->id)->where('status', 'active')->sum('price'),
                ];
    
                return response()->json([
                    'status' => 'success',
                    'message' => 'Statistics retrieved successfully',
                    'data' => $stats
                ]);
    
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to retrieve statistics',
                    'error' => $e->getMessage()
                ], 500);
            }
        }
}
