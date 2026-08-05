<?php

declare(strict_types=1);

namespace App\Services;

use App\Utils\Config;
use App\Utils\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * 视频解析结果缓存服务
 * 使用 Symfony Cache 组件，支持多种驱动（文件、Redis、数组）
 */
class ParseCache
{
    private static ?\Symfony\Contracts\Cache\CacheInterface $cache = null;

    private static function getCache(): \Symfony\Contracts\Cache\CacheInterface
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $config = Config::get('cache.parse', []);
        $enabled = $config['enabled'] ?? true;
        $driver = $config['driver'] ?? 'file';
        $prefix = $config['prefix'] ?? 'video_spider_parse_';
        $defaultTtl = $config['ttl'] ?? 86400;

        if (!$enabled) {
            self::$cache = new ArrayAdapter();
            return self::$cache;
        }

        switch ($driver) {
            case 'redis':
                $redisConfig = $config['redis'] ?? [];
                $host = $redisConfig['host'] ?? '127.0.0.1';
                $port = $redisConfig['port'] ?? 6379;
                $password = $redisConfig['password'] ?? '';
                $database = $redisConfig['database'] ?? 0;

                $client = \Redis::class;
                if (!extension_loaded('redis')) {
                    Logger::error('Redis 扩展未安装，回退到文件缓存');
                    $driver = 'file';
                    break;
                }

                $redis = new \Redis();
                try {
                    $redis->connect($host, $port);
                    if ($password) {
                        $redis->auth($password);
                    }
                    $redis->select($database);
                    self::$cache = new RedisAdapter($redis, '', $defaultTtl);
                } catch (\Throwable $e) {
                    Logger::error('Redis 连接失败，回退到文件缓存', ['error' => $e->getMessage()]);
                    $driver = 'file';
                }
                break;

            case 'file':
            default:
                $fileConfig = $config['file'] ?? [];
                $directory = $fileConfig['directory'] ?? __DIR__ . '/../../storage/cache/parse/';
                self::$cache = new FilesystemAdapter('', $defaultTtl, $directory);
                break;
        }

        // 如果是回退到文件缓存
        if (self::$cache === null) {
            $fileConfig = $config['file'] ?? [];
            $directory = $fileConfig['directory'] ?? __DIR__ . '/../../storage/cache/parse/';
            self::$cache = new FilesystemAdapter('', $defaultTtl, $directory);
        }

        return self::$cache;
    }

    /**
     * 获取缓存
     */
    public static function get(string $key): ?array
    {
        $cache = self::getCache();
        $prefixedKey = Config::get('cache.parse.prefix', 'video_spider_parse_') . $key;

        $item = $cache->get($prefixedKey, function (ItemInterface $item) {
            $item->expiresAfter(null); // 永不过期，由 TTL 控制
            return null;
        });

        return $item;
    }

    /**
     * 设置缓存
     */
    public static function set(string $key, array $result, ?int $ttl = null): void
    {
        $cache = self::getCache();
        $prefixedKey = Config::get('cache.parse.prefix', 'video_spider_parse_') . $key;
        $defaultTtl = Config::get('cache.parse.ttl', 86400);
        $ttl = $ttl ?? $defaultTtl;

        $cache->save(
            $cache->getItem($prefixedKey)
                ->set($result)
                ->expiresAfter($ttl)
        );
    }

    /**
     * 生成缓存键
     */
    public static function makeKey(string $url): string
    {
        return md5($url);
    }

    /**
     * 清理过期缓存（文件缓存自动处理，Redis 不需要）
     */
    public static function cleanup(): int
    {
        // Symfony Cache 自动处理过期，这里保留接口兼容性
        return 0;
    }

    /**
     * 清除所有缓存
     */
    public static function clear(): bool
    {
        $cache = self::getCache();
        return $cache->clear();
    }

    /**
     * 删除指定键
     */
    public static function delete(string $key): bool
    {
        $cache = self::getCache();
        $prefixedKey = Config::get('cache.parse.prefix', 'video_spider_parse_') . $key;
        return $cache->delete($prefixedKey);
    }
}
