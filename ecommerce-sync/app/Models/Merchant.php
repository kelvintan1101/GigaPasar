<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Merchant extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the products for the merchant.
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the orders for the merchant.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the platform connections for the merchant.
     */
    public function platformConnections()
    {
        return $this->hasMany(PlatformConnection::class);
    }

    /**
     * Get the sync logs for the merchant.
     */
    public function syncLogs()
    {
        return $this->hasMany(SyncLog::class);
    }

    /**
     * Check if merchant is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get Lazada connection for this merchant.
     */
    public function getLazadaConnection()
    {
        return $this->platformConnections()
            ->where('platform_name', 'lazada')
            ->where('status', 'connected')
            ->first();
    }
}
