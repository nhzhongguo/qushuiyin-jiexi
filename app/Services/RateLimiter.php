<?php

declare(strict_types=1);

namespace App\Services;

use App\Utils\Config;
use App\Utils\Logger;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * API 速率限制服务
 *
 * 基于 IP 地址实现速率限制
 * 使用 Symfony RateLimiter 组件，支持多种存储后端（内存、Redis、文件缓存）
 */
class RateLimiter
{
    private static ?RateLimiterFactory $factory = null;

    private static function getFactory(): RateLimiterFactory
    {
        if (self::$factory !== null) {
            return self::$factory;
        }

        $config = Config::get('cache.rate_limit', []);
        $enabled = $config['enabled'] ?? true;
        $driver = $config['driver'] ?? 'file';
        $prefix = $config['prefix'] ?? 'video_spider_ratelimit_';

        // 创建存储
        $storage = null;

        if (!$enabled) {
            // 禁用时使用无限制的存储
            $storage = new CacheStorage(new class implements CacheInterface {
                public function get(string $key, callable $callback, ?float $beta = null, array &$metadata = null): mixed { return $callback(null); }
                public function set(string $key, mixed $value, ?float $ttl = null): bool { return true; }
                public function delete(string $key): bool { return true; }
                public function clear(string $prefix = ''): bool { return true; }
            }, $prefix);
        } else {
            switch ($driver) {
                case 'redis':
                    if (!extension_loaded('redis')) {
                        Logger::error('Redis 扩展未安装，回退到文件缓存');
                        $driver = 'file';
                        break;
                    }
                    $redisConfig = $config['redis'] ?? [];
                    $host = $redisConfig['host'] ?? '127.0.0.1';
                    $port = $redisConfig['port'] ?? 6379;
                    $password = $redisConfig['password'] ?? '';
                    $database = $redisConfig['database'] ?? 0;

                    try {
                        $redis = new \Redis();
                        $redis->connect($host, $port);
                        if ($password) {
                            $redis->auth($password);
                        }
                        $redis->select($database);
                        $storage = new CacheStorage(
                            new \Symfony\Component\Cache\Adapter\RedisAdapter($redis, '', 0),
                            $prefix
                        );
                    } catch (\Throwable $e) {
                        Logger::error('Redis 连接失败，回退到文件缓存', ['error' => $e->getMessage()]);
                        $driver = 'file';
                    }
                    break;

                case 'file':
                default:
                    $fileConfig = $config['file'] ?? [];
                    $directory = $fileConfig['directory'] ?? __DIR__ . '/../../storage/cache/rate_limit/';
                    $storage = new CacheStorage(
                        new \Symfony\Component\Cache\Adapter\FilesystemAdapter('', 0, $directory),
                        $prefix
                    );
                    break;
            }

            if ($storage === null) {
                $fileConfig = $config['file'] ?? [];
                $directory = $fileConfig['directory'] ?? __DIR__ . '/../../storage/cache/rate_limit/';
                $storage = new CacheStorage(
                    new \Symfony\Component\Cache\Adapter\FilesystemAdapter('', 0, $directory),
                    $prefix
                );
            }
        }

        $limit = Config::get('rate_limit.max_requests', 60);
        $timeWindow = Config::get('rate_limit.time_window', 60);

        self::$factory = new RateLimiterFactory([
            'id' => 'api_limiter',
            'policy' => 'fixed_window',
            'limit' => $limit,
            'interval' => $timeWindow . ' seconds',
        ], $storage);

        return self::$factory;
    }

    /**
     * 检查是否超过速率限制
     *
     * @param string $ip 客户端 IP 地址
     * @param int|null $limit 每时间窗口最大请求数，null 则使用配置值
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int]
     */
    public static function check(string $ip, ?int $limit = null): array
    {
        try {
            $factory = self::getFactory();
            
            // 如果传入了自定义 limit，需要创建临时工厂
            if ($limit !== null && $limit !== Config::get('rate_limit.max_requests', 60)) {
                $timeWindow = Config::get('rate_limit.time_window', 60);
                $config = Config::get('cache.rate_limit', []);
                $enabled = $config['enabled'] ?? true;
                $driver = $config['driver'] ?? 'file';
                $prefix = $config['prefix'] ?? 'video_spider_ratelimit_';
                
                // 复用存储创建逻辑...
                $storage = self::createStorage($enabled, $driver, $prefix, $config);
                $factory = new RateLimiterFactory([
                    'id' => 'api_limiter_custom',
                    'policy' => 'fixed_window',
                    'limit' => $limit,
                    'interval' => $timeWindow . ' seconds',
                ], $storage);
            }

            $limiter = $factory->create($ip);
            $limitResult = $limiter->consume(1);

            return [
                'allowed' => $limitResult->isAccepted(),
                'remaining' => $limitResult->getRemainingTokens(),
                'reset_time' => time() + $limitResult->getRetryAfter()->getTimestamp() - time(),
            ];
        } catch (\Throwable $e) {
            Logger::error('速率限制检查失败，允许请求通过', ['ip' => $ip, 'error' => $e->getMessage()]);
            $configuredLimit = $limit ?? Config::get('rate_limit.max_requests', 60);
            return [
                'allowed' => true,
                'remaining' => $configuredLimit,
                'reset_time' => time() + Config::get('rate_limit.time_window', 60),
            ];
        }
    }

    /**
     * 创建存储实例
     */
    private static function createStorage(bool $enabled, string $driver, string $prefix, array $config): CacheStorage
    {
        if (!$enabled) {
            return new CacheStorage(new class implements CacheInterface {
                public function get(string $key, callable $callback, ?float $beta = null, array &$metadata = null): mixed { return $callback(null); }
                public function set(string $key, mixed $value, ?float $ttl = null): bool { return true; }
                public function delete(string $key): bool { return true; }
                public function clear(string $prefix = ''): bool { return true; }
            }, $prefix);
        }

        switch ($driver) {
            case 'redis':
                if (!extension_loaded('redis')) {
                    $driver = 'file';
                    break;
                }
                $redisConfig = $config['redis'] ?? [];
                try {
                    $redis = new \Redis();
                    $redis->connect($redisConfig['host'] ?? '127.0.0.1', $redisConfig['port'] ?? 6379);
                    if ($redisConfig['password'] ?? '') {
                        $redis->auth($redisConfig['password']);
                    }
                    $redis->select($redisConfig['database'] ?? 0);
                    return new CacheStorage(
                        new \Symfony\Component\Cache\Adapter\RedisAdapter($redis, '', 0),
                        $prefix
                    );
                } catch (\Throwable $e) {
                    Logger::error('Redis 连接失败，回退到文件缓存', ['error' => $e->getMessage()]);
                    $driver = 'file';
                }
                // fallthrough
            case 'file':
            default:
                $fileConfig = $config['file'] ?? [];
                $directory = $fileConfig['directory'] ?? __DIR__ . '/../../storage/cache/rate_limit/';
                return new CacheStorage(
                    new \Symfony\Component\Cache\Adapter\FilesystemAdapter('', 0, $directory),
                    $prefix
                );
        }
    }

    /**
     * 获取客户端 IP 地址
     *
     * @return string IP 地址
     */
    public static function getClientIp(): string
    {
        if (Config::get('rate_limit.trust_proxy_headers', false)) {
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $forwardedIp = trim($ips[0]);
                if (filter_var($forwardedIp, FILTER_VALIDATE_IP)) {
                    return $forwardedIp;
                }
            }

            if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                $realIp = trim((string) $_SERVER['HTTP_X_REAL_IP']);
                if (filter_var($realIp, FILTER_VALIDATE_IP)) {
                    return $realIp;
                }
            }
        }

        $remoteIp = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        return filter_var($remoteIp, FILTER_VALIDATE_IP) ? $remoteIp : '0.0.0.0';
    }

    /**
     * 清理过期的限制记录
     *
     * @return int 清理的文件数量
     */
    public static function cleanup(): int
    {
        // Symfony Cache 自动处理过期，这里保留接口兼容性
        return 0;
    }
}

