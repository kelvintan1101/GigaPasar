# GigaPasar Development Commands

## Laravel Commands
```bash
# Start development server
php artisan serve

# Run database migrations
php artisan migrate

# Run queue worker
php artisan queue:work

# Run tests
php artisan test

# Clear cache
php artisan cache:clear
php artisan config:clear

# Generate API documentation
php artisan route:list

# Database seeding
php artisan db:seed
```

## Development Workflow
```bash
# Run all development services (from composer.json)
composer run dev

# Run tests
composer run test

# Lint code
./vendor/bin/pint
```

## Queue Management
```bash
# Process queue jobs
php artisan queue:work --tries=3

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

## Database Commands
```bash
# Fresh migration with seeding
php artisan migrate:fresh --seed

# Rollback migrations
php artisan migrate:rollback

# Check migration status
php artisan migrate:status
```