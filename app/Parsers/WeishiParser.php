<?php

namespace App\Parsers;

use App\Utils\HttpClient;
use App\Utils\UserAgent;

/**
 * 微视视频解析器
 */
class WeishiParser extends BaseParser
{
    protected static function getHeaders(): array
    {
        return [
            'User-Agent' => UserAgent::desktop(),
            'Referer' => 'https://weishi.qq.com/',
        ];
    }

    public static function parse(string $url): array
    {
        $location = HttpClient::getLocation($url);
        $target = $location ?: $url;

        if (!preg_match('/(?:feed\/|feedid=|id=)([A-Za-z0-9_-]+)/', $target, $match)) {
            throw new \InvalidArgumentException('无法解析视频 ID');
        }

        $feedId = $match[1];
        $result = self::fetch(
            'https://h5.weishi.qq.com/webapp/json/weishi/WSH5GetPlayPage?feedid=' . urlencode($feedId)
        );

        if (!$result) {
            throw new \RuntimeException('解析视频信息失败');
        }

        $data = self::parseJson($result['data']);
        $item = $data['data']['feeds'][0] ?? null;
        if (!$item) {
            throw new \RuntimeException('视频数据解析失败');
        }

        $videoUrl = $item['video_url'] ?? '';
        if (!$videoUrl) {
            throw new \RuntimeException('未找到视频 URL');
        }

        return [
            'author' => $item['poster']['nick'] ?? '',
            'avatar' => isset($item['poster']['avatar']) ? self::fixUrl($item['poster']['avatar']) : '',
            'time' => $item['poster']['createtime'] ?? 0,
            'title' => $item['feed_desc_withat'] ?? '',
            'cover' => isset($item['images'][0]['url']) ? self::fixUrl($item['images'][0]['url']) : '',
            'url' => self::fixUrl($videoUrl),
        ];
    }
}
