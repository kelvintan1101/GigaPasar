<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformConnection extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'merchant_id',
        'platform_name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'connection_data',
        'status',
        'connected_at',
        'last_sync_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'connection_data' => 'array',
            'token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'last_sync_at' => 'datetime',
        ];
    }

    /**
     * Get the merchant that owns the platform connection.
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Scope a query to only include active connections.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include connections for a specific platform.
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform_name', $platform);
    }

    /**
     * Scope a query to only include connections for a specific merchant.
     */
    public function scopeForMerchant($query, int $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * Scope a query to only include expired tokens.
     */
    public function scopeTokenExpired($query)
    {
        return $query->where('token_expires_at', '<', now());
    }

    /**
     * Scope a query to only include tokens expiring soon (within 1 hour).
     */
    public function scopeTokenExpiringSoon($query)
    {
        return $query->where('token_expires_at', '<', now()->addHour())
                    ->where('token_expires_at', '>', now());
    }

    /**
     * Check if the connection is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the token is expired.
     */
    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    /**
     * Check if the token is expiring soon (within 1 hour).
     */
    public function isTokenExpiringSoon(): bool
    {
        return $this->token_expires_at && 
               $this->token_expires_at->isBefore(now()->addHour()) &&
               $this->token_expires_at->isAfter(now());
    }

    /**
     * Get the platform display name.
     */
    public function getPlatformDisplayNameAttribute(): string
    {
        return match($this->platform_name) {
            'lazada' => 'Lazada',
            'shopee' => 'Shopee',
            'tokopedia' => 'Tokopedia',
            default => ucfirst($this->platform_name)
        };
    }

    /**
     * Get the connection status display.
     */
    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            'active' => 'Connected',
            'error' => 'Connection Error',
            'disconnected' => 'Disconnected',
            'expired' => 'Token Expired',
            default => ucfirst($this->status)
        };
    }

    /**
     * Get connection health status.
     */
    public function getHealthStatusAttribute(): array
    {
        $health = [
            'status' => $this->status,
            'is_healthy' => false,
            'issues' => []
        ];

        if ($this->status !== 'active') {
            $health['issues'][] = 'Connection is not active';
            return $health;
        }

        if ($this->isTokenExpired()) {
            $health['issues'][] = 'Access token has expired';
            return $health;
        }

        if ($this->isTokenExpiringSoon()) {
            $health['issues'][] = 'Access token expires soon';
        }

        if ($this->last_sync_at && $this->last_sync_at->diffInHours(now()) > 24) {
            $health['issues'][] = 'No sync activity in the last 24 hours';
        }

        $health['is_healthy'] = empty($health['issues']) || 
                               (count($health['issues']) === 1 && str_contains($health['issues'][0], 'expires soon'));

        return $health;
    }

    /**
     * Get seller information from connection data.
     */
    public function getSellerInfoAttribute(): ?array
    {
        return $this->connection_data['seller_info'] ?? null;
    }

    /**
     * Get country information from connection data.
     */
    public function getCountryInfoAttribute(): ?array
    {
        return $this->connection_data['country_user_info'] ?? null;
    }

    /**
     * Update last sync timestamp.
     */
    public function updateLastSync(): bool
    {
        return $this->update(['last_sync_at' => now()]);
    }

    /**
     * Mark connection as having an error.
     */
    public function markAsError(string $errorMessage = null): bool
    {
        $updateData = ['status' => 'error'];
        
        if ($errorMessage) {
            $connectionData = $this->connection_data ?? [];
            $connectionData['last_error'] = [
                'message' => $errorMessage,
                'timestamp' => now()->toISOString()
            ];
            $updateData['connection_data'] = $connectionData;
        }

        return $this->update($updateData);
    }

    /**
     * Clear error status and mark as active.
     */
    public function clearError(): bool
    {
        $connectionData = $this->connection_data ?? [];
        unset($connectionData['last_error']);
        
        return $this->update([
            'status' => 'active',
            'connection_data' => $connectionData
        ]);
    }
}