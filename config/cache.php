<?php

use App\Utils\Config;

// 确保环境变量已加载
Config::env('APP_NAME');

/**
 * 缓存配置文件
 */
return [
    // 解析结果缓存
    'parse' => [
        'enabled' => Config::env('PARSE_CACHE_ENABLED', 'true') !== 'false',
        'ttl' => (int)Config::env('PARSE_CACHE_TTL', '86400'), // 24小时
        'driver' => Config::env('PARSE_CACHE_DRIVER', 'file'), // file, redis, array
        'prefix' => 'video_spider_parse_',
        // 文件缓存配置
        'file' => [
            'directory' => __DIR__ . '/../../storage/cache/parse/',
        ],
        // Redis 缓存配置（可选）
        'redis' => [
            'host' => Config::env('REDIS_HOST', '127.0.0.1'),
            'port' => (int)Config::env('REDIS_PORT', '6379'),
            'password' => Config::env('REDIS_PASSWORD', ''),
            'database' => (int)Config::env('REDIS_DATABASE', '0'),
        ],
    ],

    // 速率限制缓存
    'rate_limit' => [
        'enabled' => Config::env('RATE_LIMIT_CACHE_ENABLED', 'true') !== 'false',
        'driver' => Config::env('RATE_LIMIT_CACHE_DRIVER', 'file'),
        'file' => [
            'directory' => __DIR__ . '/../../storage/cache/rate_limit/',
        ],
        'redis' => [
            'host' => Config::env('REDIS_HOST', '127.0.0.1'),
            'port' => (int)Config::env('REDIS_PORT', '6379'),
            'password' => Config::env('REDIS_PASSWORD', ''),
            'database' => (int)Config::env('REDIS_DATABASE', '0'),
        ],
    ],
];
