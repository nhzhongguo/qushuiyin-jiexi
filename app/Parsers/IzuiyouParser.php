<?php

namespace App\Parsers;

use App\Utils\UserAgent;

/**
 * 最右视频解析器
 */
class IzuiyouParser extends BaseParser
{
    protected static function getHeaders(): array
    {
        return [
            'User-Agent' => UserAgent::desktop(),
            'Referer' => 'https://share.izuiyou.com/'
        ];
    }

    public static function parse(string $url): array
    {
        if (!preg_match('/\?pid=(\d+)\b/', $url, $match)) {
            throw new \InvalidArgumentException('无法解析视频 ID');
        }

        $postData = '{"h_av":"5.2.13.011", "pid":' . $match[1] . '}';

        $result = self::fetch('https://share.xiaochuankeji.cn/planck/share/post/detail_h5', $postData);

        $data = self::parseJson($result['data']);
        if (!$data) {
            throw new \RuntimeException('视频数据解析失败');
        }


        $item = $data['data']['post'];
        $videoKey = array_keys($item['videos'] ?? [])[0] ?? null;
        $videoUrl = $item['videos'][$videoKey]['url'];

        if (!$videoUrl) {
            throw new \RuntimeException('未找到视频 URL');
        }

        return [
            'title' => $item['content'] ?? '',
            'author' => $item['member']['name'] ?? '',
            'avatar' => isset($item['member']['avatar_urls']['origin']['urls'][0]) 
                ? self::fixUrl($item['member']['avatar_urls']['origin']['urls'][0]) 
                : '',
            'cover' => isset($item['imgs'][0]['url']) 
                ? self::fixUrl($item['imgs'][0]['url']) 
                : '',
            'url' => self::fixUrl($videoUrl)
        ];
    }
}