<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\PlatformConnection;
use Carbon\Carbon;

class LazadaApiService
{
    private string $baseUrl;
    private string $appKey;
    private string $appSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->baseUrl = config('services.lazada.api_url', 'https://api.lazada.com/rest');
        $this->appKey = config('services.lazada.app_key');
        $this->appSecret = config('services.lazada.app_secret');
        $this->redirectUri = config('services.lazada.redirect_uri');
    }

    /**
     * Generate Lazada OAuth authorization URL
     */
    public function getAuthorizationUrl(int $merchantId): string
    {
        $params = [
            'response_type' => 'code',
            'force_auth' => 'true',
            'redirect_uri' => $this->redirectUri,
            'client_id' => $this->appKey,
            'state' => base64_encode(json_encode(['merchant_id' => $merchantId]))
        ];

        return 'https://auth.lazada.com/oauth/authorize?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchangeCodeForToken(string $code): array
    {
        try {
            $timestamp = $this->getTimestamp();
            $params = [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri,
                'client_id' => $this->appKey,
                'client_secret' => $this->appSecret,
                'timestamp' => $timestamp,
            ];

            $signature = $this->generateSignature('/auth/token/create', $params, 'POST');
            $params['sign'] = $signature;

            $response = Http::timeout(30)
                ->post('https://auth.lazada.com/rest/auth/token/create', $params);

            if (!$response->successful()) {
                throw new Exception('Failed to exchange code for token: ' . $response->body());
            }

            $data = $response->json();

            if (isset($data['code']) && $data['code'] !== '0') {
                throw new Exception('Lazada API Error: ' . ($data['message'] ?? 'Unknown error'));
            }

            return [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_in' => $data['expires_in'],
                'account_platform' => $data['account_platform'] ?? 'lazada',
                'country_user_info' => $data['country_user_info'] ?? [],
            ];

        } catch (Exception $e) {
            Log::error('Lazada token exchange failed', [
                'error' => $e->getMessage(),
                'code' => $code
            ]);
            throw $e;
        }
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshToken(string $refreshToken): array
    {
        try {
            $timestamp = $this->getTimestamp();
            $params = [
                'grant_type' => 'refresh_token',
                'client_id' => $this->appKey,
                'client_secret' => $this->appSecret,
                'refresh_token' => $refreshToken,
                'timestamp' => $timestamp,
            ];

            $signature = $this->generateSignature('/auth/token/refresh', $params, 'POST');
            $params['sign'] = $signature;

            $response = Http::timeout(30)
                ->post('https://auth.lazada.com/rest/auth/token/refresh', $params);

            if (!$response->successful()) {
                throw new Exception('Failed to refresh token: ' . $response->body());
            }

            $data = $response->json();

            if (isset($data['code']) && $data['code'] !== '0') {
                throw new Exception('Lazada API Error: ' . ($data['message'] ?? 'Unknown error'));
            }

            return [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $refreshToken,
                'expires_in' => $data['expires_in'],
            ];

        } catch (Exception $e) {
            Log::error('Lazada token refresh failed', [
                'error' => $e->getMessage(),
                'refresh_token' => substr($refreshToken, 0, 20) . '...'
            ]);
            throw $e;
        }
    }

    /**
     * Make authenticated API request to Lazada
     */
    public function makeRequest(string $endpoint, array $params = [], string $method = 'GET', ?string $accessToken = null): array
    {
        try {
            $timestamp = $this->getTimestamp();
            
            $systemParams = [
                'app_key' => $this->appKey,
                'timestamp' => $timestamp,
                'sign_method' => 'sha256',
            ];

            if ($accessToken) {
                $systemParams['access_token'] = $accessToken;
            }

            $allParams = array_merge($systemParams, $params);
            $signature = $this->generateSignature($endpoint, $allParams, $method);
            $allParams['sign'] = $signature;

            $url = $this->baseUrl . $endpoint;

            if ($method === 'GET') {
                $response = Http::timeout(30)->get($url, $allParams);
            } else {
                $response = Http::timeout(30)->post($url, $allParams);
            }

            if (!$response->successful()) {
                throw new Exception("API request failed: {$response->status()} - {$response->body()}");
            }

            $data = $response->json();

            if (isset($data['code']) && $data['code'] !== '0') {
                throw new Exception('Lazada API Error: ' . ($data['message'] ?? 'Unknown error'));
            }

            return $data;

        } catch (Exception $e) {
            Log::error('Lazada API request failed', [
                'endpoint' => $endpoint,
                'method' => $method,
                'error' => $e->getMessage(),
                'params' => $params
            ]);
            throw $e;
        }
    }

    /**
     * Get seller information
     */
    public function getSellerInfo(string $accessToken): array
    {
        return $this->makeRequest('/seller/get', [], 'GET', $accessToken);
    }

    /**
     * Create product on Lazada
     */
    public function createProduct(array $productData, string $accessToken): array
    {
        $payload = [
            'payload' => json_encode($productData)
        ];

        return $this->makeRequest('/product/create', $payload, 'POST', $accessToken);
    }

    /**
     * Update product on Lazada
     */
    public function updateProduct(array $productData, string $accessToken): array
    {
        $payload = [
            'payload' => json_encode($productData)
        ];

        return $this->makeRequest('/product/update', $payload, 'POST', $accessToken);
    }

    /**
     * Get product list from Lazada
     */
    public function getProducts(string $accessToken, array $filters = []): array
    {
        $params = array_merge([
            'filter' => 'all',
            'limit' => 50,
            'offset' => 0
        ], $filters);

        return $this->makeRequest('/products/get', $params, 'GET', $accessToken);
    }

    /**
     * Update product stock
     */
    public function updateProductStock(array $stockData, string $accessToken): array
    {
        $payload = [
            'payload' => json_encode($stockData)
        ];

        return $this->makeRequest('/product/price_quantity/update', $payload, 'POST', $accessToken);
    }

    /**
     * Get orders from Lazada
     */
    public function getOrders(string $accessToken, array $filters = []): array
    {
        $params = array_merge([
            'status' => 'pending',
            'limit' => 50,
            'offset' => 0,
            'sort_direction' => 'DESC',
            'sort_by' => 'updated_at'
        ], $filters);

        return $this->makeRequest('/orders/get', $params, 'GET', $accessToken);
    }

    /**
     * Get order details
     */
    public function getOrderDetails(int $orderId, string $accessToken): array
    {
        return $this->makeRequest('/order/get', ['order_id' => $orderId], 'GET', $accessToken);
    }

    /**
     * Check if platform connection is valid and refresh token if needed
     */
    public function validateAndRefreshConnection(PlatformConnection $connection): bool
    {
        try {
            // Check if token is expired or about to expire (within 1 hour)
            if ($connection->token_expires_at && $connection->token_expires_at->subHour()->isPast()) {
                Log::info('Refreshing Lazada token for merchant', ['merchant_id' => $connection->merchant_id]);
                
                $tokenData = $this->refreshToken($connection->refresh_token);
                
                $connection->update([
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'],
                    'token_expires_at' => Carbon::now()->addSeconds($tokenData['expires_in']),
                    'last_sync_at' => Carbon::now(),
                    'status' => 'active'
                ]);
            }

            // Test the connection by getting seller info
            $this->getSellerInfo($connection->access_token);
            
            return true;

        } catch (Exception $e) {
            Log::error('Lazada connection validation failed', [
                'merchant_id' => $connection->merchant_id,
                'error' => $e->getMessage()
            ]);

            $connection->update(['status' => 'error']);
            return false;
        }
    }

    /**
     * Generate API signature
     */
    private function generateSignature(string $endpoint, array $params, string $method): string
    {
        ksort($params);
        
        $stringToBeSigned = $method . $endpoint;
        foreach ($params as $key => $value) {
            $stringToBeSigned .= $key . $value;
        }

        return strtoupper(hash_hmac('sha256', $stringToBeSigned, $this->appSecret));
    }

    /**
     * Get timestamp in milliseconds
     */
    private function getTimestamp(): string
    {
        return (string) (time() * 1000);
    }

    /**
     * Transform product data for Lazada API format
     */
    public function transformProductForLazada(array $productData): array
    {
        return [
            'Request' => [
                'Product' => [
                    'PrimaryCategory' => $productData['category_id'] ?? '1',
                    'SPUId' => $productData['spu_id'] ?? null,
                    'AssociatedSku' => $productData['sku'],
                    'Attributes' => [
                        'name' => $productData['name'],
                        'description' => $productData['description'],
                        'brand' => $productData['brand'] ?? 'No Brand',
                        'model' => $productData['model'] ?? 'N/A',
                        'warranty_type' => $productData['warranty_type'] ?? 'No Warranty',
                        'warranty' => $productData['warranty'] ?? '1 Month',
                    ],
                    'Skus' => [
                        [
                            'SellerSku' => $productData['sku'],
                            'quantity' => $productData['stock'],
                            'price' => $productData['price'],
                            'package_length' => $productData['package_length'] ?? '10',
                            'package_height' => $productData['package_height'] ?? '10',
                            'package_width' => $productData['package_width'] ?? '10',
                            'package_weight' => $productData['package_weight'] ?? '0.5',
                            'Images' => [
                                $productData['image_url'] ?? 'https://via.placeholder.com/300x300'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}