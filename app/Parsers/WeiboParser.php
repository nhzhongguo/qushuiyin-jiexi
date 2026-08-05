<?php

namespace App\Parsers;

use App\Utils\CookieManager;
use App\Utils\UserAgent;

/**
 * 微博视频解析器
 */
class WeiboParser extends BaseParser
{
    protected static function getHeaders(): array
    {
        if (!CookieManager::has('weibo')) {
            throw new \RuntimeException('请先设置微博 cookie');
        }

        return [
            'User-Agent' => UserAgent::desktop(),
            'Referer' => 'https://weibo.com/',
            'Cookie' => CookieManager::get('weibo')
        ];
    }

    public static function parse(string $url): array
    {
        self::getHeaders();

        $pattern = strpos($url, 'show?fid=') !== false ? '/fid=(.*)/' : '/\d+\:\d+/';
        if (!preg_match($pattern, $url, $match)) {
            throw new \InvalidArgumentException('无法解析视频 ID');
        }

        $cid = $match[1] ?? $match[0];
        $postData = 'data=' . json_encode(['Component_Play_Playinfo' => ['oid' => $cid]]);
        
        $result = self::fetch("https://weibo.com/tv/api/component?page=/tv/show/{$cid}", $postData);

        $data = self::parseJson($result['data']);
        $item = $data['data']['Component_Play_Playinfo'] ?? null;
        if (!$item || empty($item['urls'])) {
            throw new \RuntimeException('视频数据解析失败');
        }

        $videoUrl = is_array($item['urls']) ? $item['urls'][key($item['urls'])] : $item['urls'];
        if (!$videoUrl) {
            throw new \RuntimeException('未找到视频 URL');
        }
        return [
            'title' => $item['title'] ?? '',
            'author' => $item['author'] ?? '',
            'avatar' => isset($item['avatar']) ? self::fixUrl($item['avatar']) : '',
            'time' => $item['real_date'] ?? '',
            'cover' => isset($item['cover_image']) ? self::fixUrl($item['cover_image']) : '',
            'url' => self::fixUrl($videoUrl)
        ];
    }
}
