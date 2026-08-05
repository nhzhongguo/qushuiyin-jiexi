<?php

namespace App\Parsers;

use App\Utils\HttpClient;
use App\Utils\UserAgent;

/**
 * 皮皮虾视频解析器
 */
class PipixiaParser extends BaseParser
{
    protected static function getHeaders(): array
    {
        return [
            'User-Agent' => UserAgent::mobile(),
            'Referer' => 'https://www.pipix.com/',
        ];
    }

    public static function parse(string $url): array
    {
        $location = HttpClient::getLocation($url);
        $target = $location ?: $url;

        if (!preg_match('/item\/(\d+)/', $target, $match)) {
            throw new \InvalidArgumentException('无法解析视频 ID');
        }

        $result = self::fetch(
            'https://is.snssdk.com/bds/cell/detail/?cell_type=1&aid=1319&app_name=super&cell_id=' . $match[1]
        );

        if (!$result) {
            throw new \RuntimeException('解析视频信息失败');
        }

        $data = self::parseJson($result['data']);
        $item = $data['data']['data']['item'] ?? null;
        if (!$item) {
            throw new \RuntimeException('视频数据解析失败');
        }

        $videoUrl = $item['origin_video_download']['url_list'][0]['url'] ?? null;
        if (!$videoUrl) {
            $videoUrl = $item['video']['play_addr']['url_list'][0]['url'] ?? null;
        }
        if (!$videoUrl) {
            throw new \RuntimeException('未找到视频 URL');
        }

        return [
            'author' => $item['author']['name'] ?? '',
            'avatar' => isset($item['author']['avatar']['download_list'][0]['url'])
                ? self::fixUrl($item['author']['avatar']['download_list'][0]['url'])
                : '',
            'time' => $data['data']['display_time'] ?? 0,
            'title' => $item['content'] ?? '',
            'cover' => isset($item['cover']['url_list'][0]['url'])
                ? self::fixUrl($item['cover']['url_list'][0]['url'])
                : '',
            'url' => self::fixUrl($videoUrl),
        ];
    }
}
