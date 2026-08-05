<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

use App\Services\MediaProxy;
use App\Utils\Config;
use App\Utils\HttpClient;

$classes = [
    App\Parsers\DouyinParser::class,
    App\Parsers\KuaishouParser::class,
    App\Parsers\BilibiliParser::class,
    App\Parsers\PipixiaParser::class,
    App\Parsers\WeiboParser::class,
    App\Parsers\XiguaParser::class,
    App\Parsers\ShipinhaoParser::class,
    App\Parsers\IzuiyouParser::class,
    App\Parsers\PipigxParser::class,
    App\Parsers\WeishiParser::class,
    App\Parsers\XiaohongshuParser::class,
];

$failed = false;
foreach ($classes as $class) {
    try {
        if (!class_exists($class)) {
            throw new RuntimeException('class not found');
        }
        echo "OK   {$class}" . PHP_EOL;
    } catch (Throwable $e) {
        $failed = true;
        echo "FAIL {$class}: {$e->getMessage()}" . PHP_EOL;
    }
}

$headers = new ReflectionMethod(App\Parsers\DouyinParser::class, 'getHeaders');
if (!$headers->isPublic()) {
    $failed = true;
    echo "FAIL App\Parsers\DouyinParser::getHeaders must be public" . PHP_EOL;
}

// 分组配置必须能通过点号路径读取，避免运行时静默回退到默认值。
$configChecks = [
    'cache.parse.ttl',
    'media_proxy.max_file_size',
    'rate_limit.max_requests',
    'curl.timeout',
    'parser.douyin',
];
foreach ($configChecks as $key) {
    $value = Config::get($key);
    if ($value === null) {
        $failed = true;
        echo "FAIL Config::get({$key}) returned null" . PHP_EOL;
    } else {
        echo "OK   config {$key}" . PHP_EOL;
    }
}

// PHP 运行时可能只提供 getenv()，配置读取不能依赖 variables_order=E。
putenv('VIDEO_SPIDER_SMOKE_ENV=ok');
if (Config::env('VIDEO_SPIDER_SMOKE_ENV', '') !== 'ok') {
    $failed = true;
    echo 'FAIL Config::env does not read process environment variables' . PHP_EOL;
} else {
    echo 'OK   process environment variable loading' . PHP_EOL;
}
putenv('VIDEO_SPIDER_SMOKE_ENV');

// TLS 证书校验必须保持开启；CA 文件按环境可选，但不能通过默认配置关闭校验。
$sslOptions = HttpClient::getSslOptions();
if (($sslOptions[CURLOPT_SSL_VERIFYPEER] ?? null) !== true) {
    $failed = true;
    echo 'FAIL CURLOPT_SSL_VERIFYPEER must be true' . PHP_EOL;
} else {
    echo 'OK   TLS peer verification enabled' . PHP_EOL;
}
if (($sslOptions[CURLOPT_SSL_VERIFYHOST] ?? null) !== 2) {
    $failed = true;
    echo 'FAIL CURLOPT_SSL_VERIFYHOST must be 2' . PHP_EOL;
} else {
    echo 'OK   TLS host verification enabled' . PHP_EOL;
}

// 媒体代理必须拒绝本机、私网地址，并正确解析相对重定向。
$isAllowedUrl = new ReflectionMethod(MediaProxy::class, 'isAllowedUrl');
$isAllowedUrl->setAccessible(true);
$proxyUrlChecks = [
    'http://localhost/' => false,
    'http://127.0.0.1/' => false,
    'http://10.0.0.1/' => false,
    'https://1.1.1.1/' => true,
];
foreach ($proxyUrlChecks as $url => $expected) {
    $actual = (bool) $isAllowedUrl->invoke(null, $url);
    if ($actual !== $expected) {
        $failed = true;
        echo "FAIL MediaProxy::isAllowedUrl({$url}) expected " . ($expected ? 'true' : 'false') . PHP_EOL;
    } else {
        echo "OK   media URL policy {$url}" . PHP_EOL;
    }
}

$resolveRedirectUrl = new ReflectionMethod(MediaProxy::class, 'resolveRedirectUrl');
$resolveRedirectUrl->setAccessible(true);
$relativeRedirect = $resolveRedirectUrl->invoke(null, 'https://cdn.example/path/video.mp4', '../next.mp4');
if ($relativeRedirect !== 'https://cdn.example/next.mp4') {
    $failed = true;
    echo 'FAIL relative media redirect resolution' . PHP_EOL;
} else {
    echo 'OK   relative media redirect resolution' . PHP_EOL;
}

$privateRedirect = $resolveRedirectUrl->invoke(null, 'https://cdn.example/video.mp4', 'http://127.0.0.1/admin');
if ($isAllowedUrl->invoke(null, $privateRedirect) === true) {
    $failed = true;
    echo 'FAIL private redirect target was allowed' . PHP_EOL;
} else {
    echo 'OK   private redirect target rejected' . PHP_EOL;
}

exit($failed ? 1 : 0);
