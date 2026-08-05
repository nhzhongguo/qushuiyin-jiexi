<?php

namespace App\Utils;

/**
 * Cookie 管理工具
 */
class CookieManager
{
    private static ?array $cookies = null;

    /**
     * 获取指定平台的 Cookie
     *
     * @param string $platform 平台名称
     * @return string Cookie 字符串，不存在时返回空字符串
     */
    public static function get(string $platform): string
    {
        if (self::$cookies === null) {
            self::$cookies = Config::load('cookies');
        }

        if (!isset(self::$cookies[$platform]) || !is_array(self::$cookies[$platform])) {
            return '';
        }

        return self::$cookies[$platform]['cookie'] ?? '';
    }

    /**
     * 检查平台 Cookie 是否存在
     *
     * @param string $platform 平台名称
     * @return bool 是否存在
     */
    public static function has(string $platform): bool
    {
        return !empty(self::get($platform));
    }
}

