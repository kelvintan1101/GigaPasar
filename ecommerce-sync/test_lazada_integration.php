<?php
/**
 * Lazada Integration Test Script
 * Run this script to test your Lazada integration on VPS
 * 
 * Usage: php test_lazada_integration.php
 */

require_once 'vendor/autoload.php';

class LazadaIntegrationTester {
    private $baseUrl;
    private $token;
    
    public function __construct($baseUrl = 'https://techsolution11.online') {
        $this->baseUrl = rtrim($baseUrl, '/');
    }
    
    public function runTests() {
        echo "🧪 Lazada Integration Test Suite\n";
        echo "================================\n\n";
        
        // Test 1: API Health Check
        $this->testApiHealth();
        
        // Test 2: User Registration/Login
        $this->testUserAuth();
        
        // Test 3: Lazada Auth URL Generation
        if ($this->token) {
            $this->testLazadaAuthUrl();
            $this->testConnectionsList();
        }
        
        echo "\n✅ Test suite completed!\n";
    }
    
    private function testApiHealth() {
        echo "1. Testing API Health...\n";
        
        $response = $this->makeRequest('GET', '/api/health');
        
        if ($response && isset($response['success']) && $response['success']) {
            echo "   ✅ API is healthy\n";
            echo "   📊 Response: {$response['message']}\n\n";
        } else {
            echo "   ❌ API health check failed\n\n";
        }
    }
    
    private function testUserAuth() {
        echo "2. Testing User Authentication...\n";
        
        // Try to login with test credentials
        $loginData = [
            'email' => 'merchant@test.com',
            'password' => 'password123'
        ];
        
        $response = $this->makeRequest('POST', '/api/v1/login', $loginData);
        
        if ($response && isset($response['data']['token'])) {
            $this->token = $response['data']['token'];
            echo "   ✅ Login successful\n";
            echo "   🔑 Token: " . substr($this->token, 0, 20) . "...\n\n";
        } else {
            echo "   ⚠️  Login failed - you may need to run seeders or register manually\n";
            echo "   📝 Register command: php artisan db:seed\n\n";
        }
    }
    
    private function testLazadaAuthUrl() {
        echo "3. Testing Lazada Auth URL Generation...\n";
        
        $response = $this->makeRequest('GET', '/api/v1/platform/lazada/auth-url', null, [
            'Authorization: Bearer ' . $this->token
        ]);
        
        if ($response && isset($response['data']['auth_url'])) {
            echo "   ✅ Auth URL generated successfully\n";
            echo "   🔗 URL: {$response['data']['auth_url']}\n";
            echo "   📝 Visit this URL to connect your Lazada account\n\n";
        } else {
            echo "   ❌ Failed to generate Lazada auth URL\n\n";
        }
    }
    
    private function testConnectionsList() {
        echo "4. Testing Platform Connections List...\n";
        
        $response = $this->makeRequest('GET', '/api/v1/platform/connections', null, [
            'Authorization: Bearer ' . $this->token
        ]);
        
        if ($response && isset($response['data'])) {
            $connections = $response['data'];
            echo "   ✅ Connections retrieved successfully\n";
            echo "   📊 Total connections: " . count($connections) . "\n";
            
            foreach ($connections as $conn) {
                echo "   📋 Platform: {$conn['platform_name']}, Status: {$conn['status']}\n";
            }
            echo "\n";
        } else {
            echo "   ❌ Failed to retrieve connections\n\n";
        }
    }
    
    private function makeRequest($method, $endpoint, $data = null, $headers = []) {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $defaultHeaders = ['Content-Type: application/json'];
        $allHeaders = array_merge($defaultHeaders, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            echo "   ❌ cURL Error: " . curl_error($ch) . "\n";
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        if ($httpCode >= 400) {
            echo "   ❌ HTTP Error {$httpCode}: {$response}\n";
            return null;
        }
        
        return json_decode($response, true);
    }
}

// Run the tests
$tester = new LazadaIntegrationTester();
$tester->runTests();