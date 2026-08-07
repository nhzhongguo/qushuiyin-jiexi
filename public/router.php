<?php

declare(strict_types=1);

/**
 * PHP 内置服务器路由脚本（composer serve 使用）
 *
 * 与 public/.htaccess 保持一致的转发规则：
 * - 存在的静态文件直接返回
 * - /api/v1/* -> api/v1.php
 * - 其余 -> index.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');

if (str_contains($uri, "\0") || str_contains($uri, '\\') || preg_match('/(^|\/)\.\.(\/|$)/', $uri)) {
    http_response_code(400);
    exit;
}

$file = realpath(__DIR__ . $uri);
$publicDir = realpath(__DIR__);

if ($uri !== '/' && $file !== false && $publicDir !== false
    && str_starts_with($file, $publicDir . DIRECTORY_SEPARATOR)
    && is_file($file)) {
    return false;
}

if (strpos($uri, '/api/v1/') === 0) {
    require __DIR__ . '/api/v1.php';
    return true;
}

require __DIR__ . '/index.php';
return true;
