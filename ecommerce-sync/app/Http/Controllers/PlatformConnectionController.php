<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PlatformConnection;
use App\Services\LazadaApiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Exception;

class PlatformConnectionController extends Controller
{
    private LazadaApiService $lazadaService;

    public function __construct(LazadaApiService $lazadaService)
    {
        $this->middleware('auth:sanctum');
        $this->lazadaService = $lazadaService;
    }

    /**
     * Get all platform connections for the authenticated merchant.
     */
    public function index(): JsonResponse
    {
        try {
            $merchant = Auth::user();
            
            $connections = PlatformConnection::where('merchant_id', $merchant->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Platform connections retrieved successfully',
                'data' => $connections
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve platform connections', [
                'merchant_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve platform connections',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Lazada authorization URL for connecting merchant account.
     */
    public function getLazadaAuthUrl(): JsonResponse
    {
        try {
            $merchant = Auth::user();
            $authUrl = $this->lazadaService->getAuthorizationUrl($merchant->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Authorization URL generated successfully',
                'data' => [
                    'auth_url' => $authUrl,
                    'merchant_id' => $merchant->id
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to generate Lazada auth URL', [
                'merchant_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate authorization URL',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Lazada OAuth callback and store connection.
     */
    public function handleLazadaCallback(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'state' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Decode state to get merchant ID
            $stateData = json_decode(base64_decode($request->state), true);
            
            if (!$stateData || !isset($stateData['merchant_id'])) {
                throw new Exception('Invalid state parameter');
            }
            
            $merchantId = $stateData['merchant_id'];

            // Exchange code for token
            $tokenData = $this->lazadaService->exchangeCodeForToken($request->code);

            // Get seller information
            $sellerInfo = $this->lazadaService->getSellerInfo($tokenData['access_token']);

            // Store or update platform connection
            $connection = PlatformConnection::updateOrCreate(
                [
                    'merchant_id' => $merchantId,
                    'platform_name' => 'lazada'
                ],
                [
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'],
                    'token_expires_at' => Carbon::now()->addSeconds($tokenData['expires_in']),
                    'connection_data' => [
                        'account_platform' => $tokenData['account_platform'],
                        'country_user_info' => $tokenData['country_user_info'],
                        'seller_info' => $sellerInfo['data'] ?? []
                    ],
                    'status' => 'active',
                    'connected_at' => Carbon::now(),
                    'last_sync_at' => Carbon::now()
                ]
            );

            Log::info('Lazada connection established', [
                'merchant_id' => $merchantId,
                'connection_id' => $connection->id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Lazada account connected successfully',
                'data' => [
                    'connection_id' => $connection->id,
                    'platform_name' => $connection->platform_name,
                    'status' => $connection->status,
                    'connected_at' => $connection->connected_at,
                    'seller_info' => $connection->connection_data['seller_info'] ?? []
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Lazada callback handling failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to connect Lazada account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test platform connection status.
     */
    public function testConnection(int $connectionId): JsonResponse
    {
        try {
            $merchant = Auth::user();
            
            $connection = PlatformConnection::where('id', $connectionId)
                ->where('merchant_id', $merchant->id)
                ->firstOrFail();

            if ($connection->platform_name === 'lazada') {
                $isValid = $this->lazadaService->validateAndRefreshConnection($connection);
                
                if ($isValid) {
                    $connection->refresh();
                    
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Connection is active and working',
                        'data' => [
                            'connection_id' => $connection->id,
                            'platform_name' => $connection->platform_name,
                            'status' => $connection->status,
                            'last_sync_at' => $connection->last_sync_at
                        ]
                    ]);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Connection test failed',
                        'data' => [
                            'connection_id' => $connection->id,
                            'status' => $connection->status
                        ]
                    ], 400);
                }
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Unsupported platform',
                'error' => 'Platform not supported'
            ], 400);

        } catch (Exception $e) {
            Log::error('Connection test failed', [
                'connection_id' => $connectionId,
                'merchant_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to test connection',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disconnect platform connection.
     */
    public function disconnect(int $connectionId): JsonResponse
    {
        try {
            $merchant = Auth::user();
            
            $connection = PlatformConnection::where('id', $connectionId)
                ->where('merchant_id', $merchant->id)
                ->firstOrFail();

            $connection->update([
                'status' => 'disconnected',
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null
            ]);

            Log::info('Platform connection disconnected', [
                'connection_id' => $connectionId,
                'merchant_id' => $merchant->id,
                'platform' => $connection->platform_name
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Platform disconnected successfully',
                'data' => [
                    'connection_id' => $connection->id,
                    'platform_name' => $connection->platform_name,
                    'status' => $connection->status
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to disconnect platform', [
                'connection_id' => $connectionId,
                'merchant_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to disconnect platform',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get connection statistics.
     */
    public function getConnectionStats(): JsonResponse
    {
        try {
            $merchant = Auth::user();
            
            $totalConnections = PlatformConnection::where('merchant_id', $merchant->id)->count();
            $activeConnections = PlatformConnection::where('merchant_id', $merchant->id)
                ->where('status', 'active')
                ->count();
            $errorConnections = PlatformConnection::where('merchant_id', $merchant->id)
                ->where('status', 'error')
                ->count();

            $platformStats = PlatformConnection::where('merchant_id', $merchant->id)
                ->selectRaw('platform_name, status, COUNT(*) as count')
                ->groupBy('platform_name', 'status')
                ->get()
                ->groupBy('platform_name');

            return response()->json([
                'status' => 'success',
                'message' => 'Connection statistics retrieved successfully',
                'data' => [
                    'summary' => [
                        'total_connections' => $totalConnections,
                        'active_connections' => $activeConnections,
                        'error_connections' => $errorConnections,
                        'disconnected_connections' => $totalConnections - $activeConnections - $errorConnections
                    ],
                    'by_platform' => $platformStats
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve connection statistics', [
                'merchant_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve connection statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}