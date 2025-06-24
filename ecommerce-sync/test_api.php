<?php

// Simple API test script

echo "Testing Ecommerce Sync API...\n\n";

// API base URL
$baseUrl = 'http://localhost:8000/api';

// Test data
$testMerchant = [
    'name' => 'Test Merchant',
    'email' => 'test@example.com',
    'password' => 'Password123!',
    'password_confirmation' => 'Password123!',
    'phone' => '+1234567890',
    'address' => '123 Test Street, Test City'
];

// Function to make HTTP requests
function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $headers[] = 'Content-Type: application/json';
        }
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $headers[] = 'Content-Type: application/json';
        }
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        echo "CURL Error: " . curl_error($ch) . "\n";
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => $response,
        'data' => json_decode($response, true)
    ];
}

// Test 1: Health Check
echo "1. Testing Health Check...\n";
$response = makeRequest($baseUrl . '/health');
if ($response && $response['code'] === 200) {
    echo "✅ Health check passed\n";
    echo "Response: " . $response['body'] . "\n\n";
} else {
    echo "❌ Health check failed\n";
    if ($response) {
        echo "HTTP Code: " . $response['code'] . "\n";
        echo "Response: " . $response['body'] . "\n";
    }
    echo "\n";
}

// Test 2: Register Merchant
echo "2. Testing Merchant Registration...\n";
$response = makeRequest($baseUrl . '/v1/register', 'POST', $testMerchant);
if ($response && $response['code'] === 201) {
    echo "✅ Registration successful\n";
    $accessToken = $response['data']['data']['access_token'] ?? null;
    echo "Access Token: " . substr($accessToken, 0, 20) . "...\n\n";
} else {
    echo "❌ Registration failed\n";
    if ($response) {
        echo "HTTP Code: " . $response['code'] . "\n";
        echo "Response: " . $response['body'] . "\n";
    }
    echo "\n";
    exit;
}

// Test 3: Login
echo "3. Testing Merchant Login...\n";
$loginData = [
    'email' => $testMerchant['email'],
    'password' => $testMerchant['password']
];
$response = makeRequest($baseUrl . '/v1/login', 'POST', $loginData);
if ($response && $response['code'] === 200) {
    echo "✅ Login successful\n";
    $accessToken = $response['data']['data']['access_token'] ?? null;
    echo "New Access Token: " . substr($accessToken, 0, 20) . "...\n\n";
} else {
    echo "❌ Login failed\n";
    if ($response) {
        echo "HTTP Code: " . $response['code'] . "\n";
        echo "Response: " . $response['body'] . "\n";
    }
    echo "\n";
}

// Test 4: Get User Info (Protected Route)
if (isset($accessToken)) {
    echo "4. Testing Protected Route (Get User Info)...\n";
    $headers = ['Authorization: Bearer ' . $accessToken];
    $response = makeRequest($baseUrl . '/v1/me', 'GET', null, $headers);
    if ($response && $response['code'] === 200) {
        echo "✅ Protected route access successful\n";
        echo "User: " . $response['data']['data']['merchant']['name'] . "\n\n";
    } else {
        echo "❌ Protected route access failed\n";
        if ($response) {
            echo "HTTP Code: " . $response['code'] . "\n";
            echo "Response: " . $response['body'] . "\n";
        }
        echo "\n";
    }
    
    // Test 5: Logout
    echo "5. Testing Logout...\n";
    $response = makeRequest($baseUrl . '/v1/logout', 'POST', null, $headers);
    if ($response && $response['code'] === 200) {
        echo "✅ Logout successful\n";
        echo "Response: " . $response['body'] . "\n\n";
    } else {
        echo "❌ Logout failed\n";
        if ($response) {
            echo "HTTP Code: " . $response['code'] . "\n";
            echo "Response: " . $response['body'] . "\n";
        }
        echo "\n";
    }
}

echo "API Testing Complete!\n"; 