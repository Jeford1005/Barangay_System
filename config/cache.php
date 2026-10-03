<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache store that will be used by the
    | framework. This connection is utilized if another isn't explicitly
    | specified when running a cache operation inside the application.
    |
    | "file" needs no worker process or extra tables, which makes it the
    | correct local default. Hosts with the cache tables (or redis) should
    | set CACHE_STORE=database (or redis) explicitly.
    |
    */

    'default' => env('CACHE_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiter Cache Store
    |--------------------------------------------------------------------------
    |
    | Login and route throttles live here instead of in the default store, so
    | `php artisan cache:clear` (and the maintenance Clear-caches button,
    | which calls it) cannot wipe brute-force protection. Only an explicit
    | `cache:clear --store=limiter` touches these keys.
    |
    | The `limiter` store below is a separate file path in production and a
    | separate array instance under tests (CACHE_STORE=array), so flushing
    | the default store never flushes this one in either environment.
    |
    */

    'limiter' => env('CACHE_LIMITER_STORE', 'limiter'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "array", "database", "file", "memcached",
    |                    "redis", "dynamodb", "octane",
    |                    "failover", "null"
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Throttle store (survives cache:clear)
        |--------------------------------------------------------------------------
        |
        | Dedicated file path so the default store's flush never touches
        | rate-limiter keys. When the test suite pins CACHE_STORE=array this
        | resolves to a separate array instance (still isolated per test,
        | still a different store from `default`), so `cache:clear` in tests
        | proves the same survival property. Override the driver with
        | CACHE_LIMITER_DRIVER if a host prefers database/redis instead.
        |
        */

        'limiter' => [
            'driver' => env('CACHE_LIMITER_DRIVER', env('CACHE_STORE', 'database') === 'array' ? 'array' : 'file'),
            'serialize' => false,
            'path' => storage_path('framework/cache/limiter'),
            'lock_path' => storage_path('framework/cache/limiter'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

        'failover' => [
            'driver' => 'failover',
            'stores' => [
                'database',
                'array',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing the APC, database, memcached, Redis, and DynamoDB cache
    | stores, there might be other applications using the same cache. For
    | that reason, you may prefix every cache key to avoid collisions.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'),

];
