<?php

namespace App\Utils;

use Dotenv\Dotenv;

/**
 * 配置管理工具
 * 统一管理配置加载，避免重复加载 .env 文件
 */
class Config
{
    private static ?array $configCache = null;
    private static array $loadedConfigs = [];
    private static bool $envLoaded = false;

    /**
     * 确保 .env 文件已加载
     */
    private static function ensureEnvLoaded(): void
    {
        if (!self::$envLoaded) {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
            $dotenv->safeLoad();
            self::$envLoaded = true;
        }
    }

    /**
     * 获取环境变量
     *
     * @param string $key 环境变量键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function env(string $key, $default = '')
    {
        self::ensureEnvLoaded();
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }

    /**
     * 加载配置文件
     *
     * @param string $name 配置文件名（不含 .php 后缀）
     * @return array
     */
    public static function load(string $name): array
    {
        $file = __DIR__ . '/../../config/' . $name . '.php';
        if (!file_exists($file)) {
            return [];
        }

        return require $file;
    }

    /**
     * 获取应用配置
     *
     * @param string|null $key 配置键名，支持点号分隔（如 'rate_limit.max_requests'）
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(?string $key = null, $default = null)
    {
        if ($key === null) {
            if (self::$configCache === null) {
                self::$configCache = self::load('app');
            }
            return self::$configCache;
        }

        $keys = explode('.', $key);
        $namespace = strtolower((string) array_shift($keys));
        $value = self::getConfig($namespace);

        foreach ($keys as $k) {
            if (!is_array($value) || !isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * 获取指定配置文件的缓存实例
     */
    private static function getConfig(string $namespace): array
    {
        if ($namespace === 'cache' || $namespace === 'parser' || $namespace === 'cookies') {
            if (!array_key_exists($namespace, self::$loadedConfigs)) {
                self::$loadedConfigs[$namespace] = self::load($namespace);
            }
            return self::$loadedConfigs[$namespace];
        }

        if (self::$configCache === null) {
            self::$configCache = self::load('app');
        }

        // Most application settings live in named sections of config/app.php.
        return self::$configCache[$namespace] ?? [];
    }

    /**
     * 清除配置缓存
     */
    public static function clearCache(): void
    {
        self::$configCache = null;
        self::$loadedConfigs = [];
    }
}
