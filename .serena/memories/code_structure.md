# GigaPasar Code Structure

## Directory Structure
```
ecommerce-sync/
├── app/
│   ├── Http/Controllers/
│   │   ├── Auth/AuthController.php - Merchant authentication
│   │   ├── ProductController.php - Product CRUD operations
│   │   └── Controller.php - Base controller
│   ├── Models/
│   │   ├── Merchant.php - Merchant entity with platform connections
│   │   ├── Product.php - Product with sync capabilities
│   │   └── User.php - Standard Laravel user model
│   └── Providers/ - Service providers
├── database/
│   ├── migrations/ - Database schema definitions
│   └── factories/ - Test data factories
├── routes/
│   ├── api.php - API endpoints
│   └── web.php - Web routes
└── tests/ - PHPUnit tests
```

## Key Models & Relationships
- **Merchant**: hasMany Products, Orders, PlatformConnections, SyncLogs
- **Product**: belongsTo Merchant, includes Lazada sync data
- **PlatformConnection**: OAuth tokens for external platforms
- **SyncLog**: Audit trail for all sync operations

## Database Tables
- merchants: Multi-tenant merchant accounts
- products: Product catalog with sync status
- platform_connections: OAuth integration data
- orders: Unified order management
- sync_logs: Synchronization audit trail
- abandoned_carts: Marketing automation data