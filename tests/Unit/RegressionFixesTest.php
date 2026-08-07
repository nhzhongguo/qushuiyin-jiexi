<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RateLimiter;
use App\Utils\Config;
use App\Utils\HttpClient;
use PHPUnit\Framework\TestCase;

final class RegressionFixesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testRateLimiterUsesPsr6ArrayAdapterWhenDisabled(): void
    {
        $source = (string) file_get_contents($this->root . '/app/Services/RateLimiter.php');
        $this->assertStringNotContainsString('CacheInterface', $source);
        $this->assertSame(
            2,
            substr_count($source, 'CacheStorage(new \\Symfony\\Component\\Cache\\Adapter\\ArrayAdapter())')
        );
        $this->assertSame(0, preg_match_all('/(?<!\?)array &$metadata = null/', $source));
    }

    public function testRateLimiterDisabledStorageDoesNotThrow(): void
    {
        putenv('RATE_LIMIT_CACHE_ENABLED=false');
        Config::clearCache();
        $result = RateLimiter::check('127.0.0.1');
        $this->assertTrue($result['allowed']);
        putenv('RATE_LIMIT_CACHE_ENABLED');
    }

    public function testApiResponsesAreNotCached(): void
    {
        $source = (string) file_get_contents($this->root . '/app/Utils/Response.php');
        $this->assertSame(2, substr_count($source, "header('Cache-Control: no-store');"));
    }

    public function testParserFetchFailuresThrowCleanErrors(): void
    {
        foreach (['WeiboParser.php', 'IzuiyouParser.php', 'PipigxParser.php'] as $file) {
            $source = (string) file_get_contents($this->root . '/app/Parsers/' . $file);
            $this->assertStringContainsString("throw new \RuntimeException('视频数据获取失败');", $source);
        }
    }

    public function testParsersUseSafeArrayAccess(): void
    {
        $weibo = (string) file_get_contents($this->root . '/app/Parsers/WeiboParser.php');
        $izuiyou = (string) file_get_contents($this->root . '/app/Parsers/IzuiyouParser.php');
        $pipigx = (string) file_get_contents($this->root . '/app/Parsers/PipigxParser.php');

        $this->assertStringContainsString('is_array($item)', $weibo);
        $this->assertStringContainsString('array_key_first($item[\'videos\'])', $izuiyou);
        $this->assertStringContainsString('is_array($item[\'videos\'] ?? null)', $pipigx);
    }

    public function testStartScriptsAndDockerfileLoadRouter(): void
    {
        $this->assertFileExists($this->root . '/public/router.php');
        $this->assertStringContainsString('public/router.php', (string) file_get_contents($this->root . '/Dockerfile'));
        $this->assertStringContainsString('public\\router.php', (string) file_get_contents($this->root . '/start.bat'));
        $this->assertStringContainsString('public/router.php', (string) file_get_contents($this->root . '/start.sh'));
    }

    public function testRouterBlocksPathTraversal(): void
    {
        $source = (string) file_get_contents($this->root . '/public/router.php');
        $this->assertStringContainsString('realpath(__DIR__ . $uri)', $source);
        $this->assertStringContainsString('str_starts_with($file, $publicDir . DIRECTORY_SEPARATOR)', $source);
        $this->assertStringContainsString('/(^|\\/)\.\.(\\/|$)/', $source);
    }

    public function testRateLimiterFailClosedIsDefault(): void
    {
        $appConfig = (string) file_get_contents($this->root . '/config/app.php');
        $this->assertStringContainsString("'fail_open' => Config::env('RATE_LIMIT_FAIL_OPEN', 'false') === 'true'", $appConfig);

        $limiter = (string) file_get_contents($this->root . '/app/Services/RateLimiter.php');
        $this->assertStringContainsString("'allowed' => false", $limiter);
    }

    public function testRateLimitCachePrefixRemoved(): void
    {
        $source = (string) file_get_contents($this->root . '/config/cache.php');
        $this->assertStringNotContainsString("'prefix' => 'video_spider_ratelimit_'", $source);
    }

    public function testLintUsesRelativePathsWithoutMbstring(): void
    {
        $source = (string) file_get_contents($this->root . '/bin/lint.php');
        $this->assertStringContainsString('chdir($root)', $source);
        $this->assertStringContainsString("ltrim(substr(\$file, strlen(\$root)), '\\\\/')", $source);
        $this->assertStringContainsString('proc_open([', $source);
        $this->assertStringContainsString('PHP_BINARY', $source);
        $this->assertStringNotContainsString('mb_convert_encoding', $source);
        $this->assertStringNotContainsString("getenv('PHP_BINARY')", $source);
    }

    /**
     * @dataProvider redirectProvider
     */
    public function testRelativeRedirectsResolveAgainstBase(string $base, string $location, string|false $expected): void
    {
        $method = new \ReflectionMethod(HttpClient::class, 'resolveLocation');
        $method->setAccessible(true);
        $this->assertSame($expected, $method->invoke(null, $base, $location));
    }

    public static function redirectProvider(): array
    {
        return [
            'absolute' => ['https://example.com/a', 'https://other.example/x', 'https://other.example/x'],
            'protocol-relative' => ['https://example.com/a/b', '//cdn.example.com/video.mp4', 'https://cdn.example.com/video.mp4'],
            'root path' => ['https://example.com/a/b', '/next', 'https://example.com/next'],
            'relative path' => ['https://example.com/a/b/c', 'd?x=1#f', 'https://example.com/a/b/d?x=1#f'],
            'parent path' => ['https://example.com/a/b/c', '../d', 'https://example.com/a/d'],
            'port preserved' => ['https://example.com:8443/a', '/next', 'https://example.com:8443/next'],
            'non-http scheme rejected' => ['https://example.com/a', 'file:///etc/passwd', false],
            'invalid base rejected' => ['not a url', '/next', false],
        ];
    }
}