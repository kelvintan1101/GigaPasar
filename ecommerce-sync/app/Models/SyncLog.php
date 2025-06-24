<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'merchant_id',
        'action_type',
        'platform_name',
        'status',
        'message',
        'request_data',
        'response_data',
        'affected_items',
        'duration',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_data' => 'array',
            'response_data' => 'array',
            'affected_items' => 'integer',
            'duration' => 'integer',
        ];
    }

    /**
     * Get the merchant that owns the sync log.
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Scope a query to only include logs for a specific merchant.
     */
    public function scopeForMerchant($query, int $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * Scope a query to only include logs for a specific platform.
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform_name', $platform);
    }

    /**
     * Scope a query to only include successful logs.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope a query to only include failed logs.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only include logs of a specific action type.
     */
    public function scopeActionType($query, string $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    /**
     * Scope a query to only include recent logs (last 24 hours).
     */
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', now()->subDay());
    }

    /**
     * Get formatted duration.
     */
    public function getFormattedDurationAttribute(): string
    {
        if ($this->duration < 1000) {
            return $this->duration . ' ms';
        } else {
            return round($this->duration / 1000, 2) . ' s';
        }
    }

    /**
     * Get action type display name.
     */
    public function getActionDisplayAttribute(): string
    {
        return match($this->action_type) {
            'product_create' => 'Product Creation',
            'product_update' => 'Product Update',
            'product_delete' => 'Product Deletion',
            'product_stock_update' => 'Stock Update',
            'order_sync' => 'Order Sync',
            'inventory_sync' => 'Inventory Sync',
            default => ucfirst(str_replace('_', ' ', $this->action_type))
        };
    }

    /**
     * Get platform display name.
     */
    public function getPlatformDisplayAttribute(): string
    {
        return match($this->platform_name) {
            'lazada' => 'Lazada',
            'shopee' => 'Shopee',
            'tokopedia' => 'Tokopedia',
            default => ucfirst($this->platform_name)
        };
    }

    /**
     * Get status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'success' => 'green',
            'failed' => 'red',
            'error' => 'red',
            'pending' => 'yellow',
            'processing' => 'blue',
            default => 'gray'
        };
    }

    /**
     * Check if sync was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if sync failed.
     */
    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'error']);
    }

    /**
     * Get error message from response data if available.
     */
    public function getErrorMessageAttribute(): ?string
    {
        if ($this->isFailed()) {
            return $this->message ?: 
                   $this->response_data['error'] ?? 
                   $this->response_data['message'] ?? 
                   'Unknown error occurred';
        }
        return null;
    }

    /**
     * Get summary of affected items.
     */
    public function getAffectedSummaryAttribute(): string
    {
        $count = $this->affected_items;
        
        if (str_contains($this->action_type, 'product')) {
            return $count === 1 ? '1 product' : "{$count} products";
        } elseif (str_contains($this->action_type, 'order')) {
            return $count === 1 ? '1 order' : "{$count} orders";
        }
        
        return $count === 1 ? '1 item' : "{$count} items";
    }
}