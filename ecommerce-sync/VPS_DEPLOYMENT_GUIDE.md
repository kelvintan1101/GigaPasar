# 🚀 VPS Deployment Guide for GigaPasar - Lazada Integration

## 1. Pre-Deployment Changes Made ✅

### Route Configuration Fixed
- ✅ Added public Lazada callback route: `/api/lazada/callback`
- ✅ Updated LAZADA_REDIRECT_URI to match route
- ✅ Removed authentication requirement from callback (Lazada needs public access)

## 2. VPS Environment Setup

### A. Environment Variables to Update
Create/update your VPS `.env` file:

```bash
# Application
APP_NAME="GigaPasar Sync Dashboard"
APP_ENV=production
APP_KEY=base64:njlq3SgREKKLpBHtCtwvuVXnOcLKOU+rDB84RuaE/eQ=
APP_DEBUG=false  # IMPORTANT: Set to false in production
APP_URL=https://techsolution11.online

# Database (Update these for your VPS MySQL)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gigapasar_sync
DB_USERNAME=your_vps_db_user
DB_PASSWORD=your_secure_vps_password

# Lazada Configuration (Already correct!)
LAZADA_APP_KEY=131855
LAZADA_APP_SECRET=EduNyCg6HN3yp1umeAj1u4Fksv4HXPrI
LAZADA_API_URL=https://api.lazada.com/rest
LAZADA_REDIRECT_URI=https://techsolution11.online/api/lazada/callback

# Queue & Cache (Use database for simplicity)
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=error  # Reduce noise in production
```

### B. Web Server Configuration

#### Nginx Configuration:
```nginx
server {
    listen 80;
    listen 443 ssl http2;
    server_name techsolution11.online;
    root /var/www/html/gigapasar/ecommerce-sync/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # SSL configuration (use Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/techsolution11.online/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/techsolution11.online/privkey.pem;
}
```

## 3. Database & Data Setup

### A. Run Migrations
```bash
cd /var/www/html/gigapasar/ecommerce-sync
php artisan migrate --force
```

### B. Data Seeding Strategy

**RECOMMENDATION: You can manually add data** instead of using seeders for testing.

#### Option 1: Manual Data Entry (Recommended for testing)
1. **Create a merchant account** via API:
```bash
curl -X POST https://techsolution11.online/api/v1/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Merchant",
    "email": "merchant@test.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

2. **Login to get token**:
```bash
curl -X POST https://techsolution11.online/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "merchant@test.com",
    "password": "password123"
  }'
```

3. **Add products manually** using the API with the token.

#### Option 2: Enhanced Seeder (Optional)
If you want to seed data, I can create a better seeder for you.

## 4. Testing Lazada Integration

### A. Test API Health
```bash
curl https://techsolution11.online/api/health
```

### B. Test Lazada Auth Flow
1. **Get auth URL** (with bearer token):
```bash
curl -X GET https://techsolution11.online/api/v1/platform/lazada/auth-url \
  -H "Authorization: Bearer YOUR_TOKEN"
```

2. **Visit the returned URL** in browser
3. **Complete Lazada OAuth** - it will redirect to your callback
4. **Check connection status**:
```bash
curl -X GET https://techsolution11.online/api/v1/platform/connections \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### C. Test Product Sync
1. **Create a test product** via API
2. **Sync to Lazada**:
```bash
curl -X POST https://techsolution11.online/api/v1/sync/products/{product_id} \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## 5. Key URLs for Lazada Integration

| Purpose | URL |
|---------|-----|
| API Health | `https://techsolution11.online/api/health` |
| Get Auth URL | `https://techsolution11.online/api/v1/platform/lazada/auth-url` |
| Callback (auto) | `https://techsolution11.online/api/lazada/callback` |
| Test Connection | `https://techsolution11.online/api/v1/platform/connections/{id}/test` |

## 6. Production Optimizations

### A. Run Production Commands
```bash
# Generate app key (if needed)
php artisan key:generate

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### B. Queue Worker (for background sync)
```bash
# Start queue worker
php artisan queue:work --daemon

# Or use supervisor for auto-restart
```

## 7. Monitoring & Logs

### Check Laravel Logs
```bash
tail -f storage/logs/laravel.log
```

### Check Lazada Integration Logs
Look for logs with:
- `Lazada connection established`
- `Lazada token exchange failed`
- `Lazada API request failed`

## 8. Troubleshooting

### Common Issues:
1. **CORS issues**: Make sure your domain is properly configured
2. **SSL required**: Lazada requires HTTPS for callbacks
3. **Token expiry**: The service auto-refreshes tokens
4. **Database connection**: Verify VPS MySQL credentials

### Test Callback Manually:
```bash
curl -X POST https://techsolution11.online/api/lazada/callback \
  -H "Content-Type: application/json" \
  -d '{
    "code": "test_code",
    "state": "BASE64_ENCODED_STATE"
  }'
```

## 9. Ready to Deploy! 🎉

Your project is now ready for VPS deployment with proper Lazada integration setup.

**Next Steps:**
1. Upload code to VPS
2. Update `.env` with production values
3. Run migrations
4. Configure web server
5. Test the integration!