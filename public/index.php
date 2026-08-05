<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\VideoParser;
use App\Services\MediaProxy;
use App\Services\RateLimiter;
use App\Utils\Response;
use App\Utils\Config;
use App\Utils\Logger;


// API v1 routing
$requestUri = $_SERVER["REQUEST_URI"] ?? "/";
$path = parse_url($requestUri, PHP_URL_PATH);

if (strpos($path, "/api/v1/") === 0) {
    require __DIR__ . "/api/v1.php";
    exit;
}
// CORS 支持
$corsOrigin = Config::get('app.cors_allow_origin', '*');
header('Access-Control-Allow-Origin: ' . $corsOrigin);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');
header('Vary: Origin, Accept-Encoding');
// API 响应默认禁止客户端缓存（解析结果可能变化）
header('Cache-Control: no-cache, private');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!in_array($method, ['GET', 'POST'], true)) {
    Response::error('不支持的请求方法', 405);
}

// 媒体代理路由：为浏览器提供可播放/可下载的服务端转发
if ($method === 'GET' && ($_GET['action'] ?? '') === 'media') {
    $mediaUrl = trim($_GET['url'] ?? '');
    if ($mediaUrl === '' || !filter_var($mediaUrl, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $mediaUrl)) {
        Response::validationError(['url' => '无效的媒体链接']);
    }

    $download = ($_GET['download'] ?? '') === '1';
    $filename = trim($_GET['filename'] ?? '') ?: 'video.mp4';
    MediaProxy::stream($mediaUrl, $download, $filename);
    exit;
}

// 浏览器直接访问首页时展示网页版界面
$acceptsHtml = $method === 'GET'
    && !isset($_GET['url'])
    && isset($_SERVER['HTTP_ACCEPT'])
    && stripos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false;

if ($acceptsHtml) {
    // Cache the HTML page for 5 minutes on the client
    header('Cache-Control: public, max-age=300, must-revalidate');
    header('Vary: Accept-Encoding, Cookie');
    require __DIR__ . '/app.php';
    exit;
}

// 速率限制检查
$ip = RateLimiter::getClientIp();
if (Config::get('rate_limit.enabled', true)) {
    $rateLimitResult = RateLimiter::check($ip);

    if (!$rateLimitResult['allowed']) {
        Response::error('请求过于频繁，请稍后再试', 429);
    }
}

// 获取并验证 URL 参数
$url = trim($_GET['url'] ?? $_POST['url'] ?? '');
if ($url === '') {
    Response::validationError(['url' => 'URL 参数不能为空']);
}

if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) {
    Response::validationError(['url' => '无效的 URL 格式']);
}

// 解析视频
try {
    $parser = new VideoParser();
    $data = $parser->parse($url);
    Response::success($data, '解析成功');

} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);

} catch (\RuntimeException $e) {
    Logger::error('解析失败', ['url' => $url, 'ip' => $ip ?? 'unknown', 'error' => $e->getMessage()]);
    Response::error($e->getMessage(), 500);

} catch (\Throwable $e) {
    Logger::exception($e, ['url' => $url, 'ip' => $ip ?? 'unknown']);
    Response::error('服务器内部错误，请稍后再试', 500);
}
