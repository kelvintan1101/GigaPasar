<?php

namespace Tests\Feature;

use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test merchant registration with valid data.
     */
    public function test_merchant_can_register_with_valid_data(): void
    {
        $merchantData = [
            'name' => 'Test Merchant',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+1234567890',
            'address' => '123 Test Street, Test City'
        ];

        $response = $this->postJson('/api/v1/register', $merchantData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'merchant' => [
                        'id',
                        'name',
                        'email',
                        'phone',
                        'address',
                        'status',
                        'created_at',
                        'updated_at'
                    ],
                    'access_token',
                    'token_type'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Merchant registered successfully',
                'data' => [
                    'token_type' => 'Bearer'
                ]
            ]);

        $this->assertDatabaseHas('merchants', [
            'email' => 'test@example.com',
            'name' => 'Test Merchant',
            'status' => 'active'
        ]);
    }

    /**
     * Test merchant registration with invalid data.
     */
    public function test_merchant_registration_fails_with_invalid_data(): void
    {
        $invalidData = [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123',
            'password_confirmation' => '456'
        ];

        $response = $this->postJson('/api/v1/register', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Validation errors'
            ]);
    }

    /**
     * Test merchant login with valid credentials.
     */
    public function test_merchant_can_login_with_valid_credentials(): void
    {
        $merchant = Merchant::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('Password123!'),
            'status' => 'active'
        ]);

        $loginData = [
            'email' => 'test@example.com',
            'password' => 'Password123!'
        ];

        $response = $this->postJson('/api/v1/login', $loginData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'merchant',
                    'access_token',
                    'token_type'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'token_type' => 'Bearer'
                ]
            ]);
    }

    /**
     * Test merchant login with invalid credentials.
     */
    public function test_merchant_login_fails_with_invalid_credentials(): void
    {
        $merchant = Merchant::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('Password123!'),
            'status' => 'active'
        ]);

        $loginData = [
            'email' => 'test@example.com',
            'password' => 'WrongPassword'
        ];

        $response = $this->postJson('/api/v1/login', $loginData);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials'
            ]);
    }

    /**
     * Test inactive merchant cannot login.
     */
    public function test_inactive_merchant_cannot_login(): void
    {
        $merchant = Merchant::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('Password123!'),
            'status' => 'inactive'
        ]);

        $loginData = [
            'email' => 'test@example.com',
            'password' => 'Password123!'
        ];

        $response = $this->postJson('/api/v1/login', $loginData);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Account is inactive'
            ]);
    }

    /**
     * Test authenticated merchant can access protected routes.
     */
    public function test_authenticated_merchant_can_access_protected_routes(): void
    {
        $merchant = Merchant::factory()->create();
        $token = $merchant->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'merchant' => [
                        'id',
                        'name',
                        'email',
                        'phone',
                        'address',
                        'status'
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'merchant' => [
                        'id' => $merchant->id,
                        'email' => $merchant->email
                    ]
                ]
            ]);
    }

    /**
     * Test unauthenticated access to protected routes fails.
     */
    public function test_unauthenticated_access_to_protected_routes_fails(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401);
    }

    /**
     * Test merchant can logout.
     */
    public function test_merchant_can_logout(): void
    {
        $merchant = Merchant::factory()->create();
        $token = $merchant->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);

        // Verify token is revoked
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/me')->assertStatus(401);
    }

    /**
     * Test merchant can update profile.
     */
    public function test_merchant_can_update_profile(): void
    {
        $merchant = Merchant::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com'
        ]);
        $token = $merchant->createToken('test-token')->plainTextToken;

        $updateData = [
            'name' => 'Updated Name',
            'phone' => '+9876543210',
            'address' => 'Updated Address'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/v1/profile', $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'merchant' => [
                        'name' => 'Updated Name',
                        'phone' => '+9876543210',
                        'address' => 'Updated Address'
                    ]
                ]
            ]);

        $this->assertDatabaseHas('merchants', [
            'id' => $merchant->id,
            'name' => 'Updated Name',
            'phone' => '+9876543210',
            'address' => 'Updated Address'
        ]);
    }

    /**
     * Test merchant can change password.
     */
    public function test_merchant_can_change_password(): void
    {
        $merchant = Merchant::factory()->create([
            'password' => Hash::make('OldPassword123!')
        ]);
        $token = $merchant->createToken('test-token')->plainTextToken;

        $passwordData = [
            'current_password' => 'OldPassword123!',
            'new_password' => 'NewPassword123!',
            'new_password_confirmation' => 'NewPassword123!'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/v1/change-password', $passwordData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);

        // Verify password was changed
        $merchant->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $merchant->password));
    }
}
