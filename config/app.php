<?php

use App\Utils\Config;

// 确保环境变量已加载
Config::env('APP_NAME');

/**
 * 应用配置文件
 */
return [
    // 应用基础配置
    'app' => [
        'name' => Config::env('APP_NAME', 'Short Video No-Watermark Downloader'),
        'debug' => Config::env('APP_DEBUG', 'false') === 'true',
        'cors_allow_origin' => Config::env('APP_CORS_ALLOW_ORIGIN', '*'),
        'env' => Config::env('APP_ENV', 'production'), // production, staging, local
    ],

    // API 速率限制配置
    'rate_limit' => [
        'enabled' => Config::env('RATE_LIMIT_ENABLED', 'true') !== 'false',
        'max_requests' => (int)Config::env('RATE_LIMIT_MAX_REQUESTS', 60),
        'time_window' => (int)Config::env('RATE_LIMIT_TIME_WINDOW', 60),
        'trust_proxy_headers' => Config::env('RATE_LIMIT_TRUST_PROXY_HEADERS', 'false') === 'true',
        'fail_open' => Config::env('RATE_LIMIT_FAIL_OPEN', 'false') === 'true',
    ],

    // API Key 鉴权（可选开启）
    'api' => [
        'key_enabled' => Config::env('API_KEY_ENABLED', 'false') === 'true',
        'key' => Config::env('API_KEY', ''),
    ],

    // HTTP 客户端配置
    'curl' => [
        'connect_timeout' => (int)Config::env('CURL_CONNECT_TIMEOUT', 5),
        'timeout' => (int)Config::env('CURL_TIMEOUT', 10),
        'max_retries' => (int)Config::env('CURL_MAX_RETRIES', 3),
        'cafile' => Config::env('CURL_CA_BUNDLE', __DIR__ . '/../storage/certs/cacert.pem'),
    ],

    // 媒体代理配置
    'media_proxy' => [
        'enabled' => Config::env('MEDIA_PROXY_ENABLED', 'true') !== 'false',
        'max_file_size' => (int)Config::env('MEDIA_PROXY_MAX_SIZE', '524288000'), // 500MB
        'allowed_domains' => array_filter(array_map('trim', explode(',', Config::env('MEDIA_PROXY_ALLOWED_DOMAINS', '')))),
    ],

    // 日志配置
    'logging' => [
        'level' => Config::env('LOG_LEVEL', 'error'), // debug, info, warning, error
        'file' => __DIR__ . '/../../storage/logs/app.log',
        'max_files' => (int)Config::env('LOG_MAX_FILES', '30'),
    ],
];

