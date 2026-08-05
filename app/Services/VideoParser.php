<?php

namespace App\Services;

/**
 * 视频解析服务
 */
class VideoParser
{
    private const PLATFORMS = [
        "douyin" => [
            "class" => \App\Parsers\DouyinParser::class,
            "domains" => ["douyin.com"],
        ],
        "kuaishou" => [
            "class" => \App\Parsers\KuaishouParser::class,
            "domains" => ["kuaishou.com", "kuaishou.cn"],
        ],
        "bilibili" => [
            "class" => \App\Parsers\BilibiliParser::class,
            "domains" => ["bilibili.com", "b23.tv"],
        ],
        "pipixia" => [
            "class" => \App\Parsers\PipixiaParser::class,
            "domains" => ["pipix.com"],
        ],
        "weibo" => [
            "class" => \App\Parsers\WeiboParser::class,
            "domains" => ["weibo.com"],
        ],
        "ixigua" => [
            "class" => \App\Parsers\XiguaParser::class,
            "domains" => ["ixigua.com"],
        ],
        "shipinhao" => [
            "class" => \App\Parsers\ShipinhaoParser::class,
            "domains" => ["channels.weixin.qq.com"],
        ],
        "izuiyou" => [
            "class" => \App\Parsers\IzuiyouParser::class,
            "domains" => ["izuiyou.com"],
        ],
        "pipigx" => [
            "class" => \App\Parsers\PipigxParser::class,
            "domains" => ["ippzone.com", "pipigx.com"],
        ],
        "weishi" => [
            "class" => \App\Parsers\WeishiParser::class,
            "domains" => ["weishi.qq.com"],
        ],
        "xiaohongshu" => [
            "class" => \App\Parsers\XiaohongshuParser::class,
            "domains" => ["xiaohongshu.com", "xhslink.com"],
        ],
    ];

    /**
     * 解析视频 URL（带缓存）
     */
    public function parse(string $url): array
    {
        // Check cache first
        $cacheKey = ParseCache::makeKey($url);
        $cached = ParseCache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $platform = $this->getPlatform($url);
        if (!$platform) {
            throw new \InvalidArgumentException("不支持的视频平台");
        }

        $result = self::PLATFORMS[$platform]["class"]::parse($url);

        // Cache the result
        ParseCache::set($cacheKey, $result);

        return $result;
    }

    private function getPlatform(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return null;
        }

        $host = strtolower(preg_replace("/^www\./", "", $host));

        foreach (self::PLATFORMS as $platform => $config) {
            foreach ($config["domains"] as $domain) {
                if ($host === $domain || substr($host, -strlen("." . $domain)) === "." . $domain) {
                    return $platform;
                }
            }
        }

        return null;
    }

    public function getSupportedPlatforms(): array
    {
        return array_keys(self::PLATFORMS);
    }
}
