<?php

namespace App\Parsers;

use App\Utils\UserAgent;

/**
 * 皮皮搞笑视频解析器
 */
class PipigxParser extends BaseParser
{
    protected static function getHeaders(): array
    {
        return [
            'User-Agent' => UserAgent::desktop(),
            'Referer' => 'https://share.ippzone.com/ppapi/share/fetch_content',
            'Content-Type' => 'application/json'
        ];
    }

    public static function parse(string $url): array
    {
        if (!preg_match('/post\/(\d+)/', $url, $match)) {
            throw new \InvalidArgumentException('无法解析视频 ID');
        }

        $postData = '{"pid":' . $match[1] . ', "type":"post", "mid":null}';

        $result = self::fetch('https://share.ippzone.com/ppapi/share/fetch_content', $postData);

        if (!$result || empty($result['data'])) {
            throw new \RuntimeException('视频数据获取失败');
        }

        $data = self::parseJson($result['data']);

        if (empty($data) || ($data['ret'] ?? 0) != 1) {
            throw new \RuntimeException('视频数据解析失败');
        }

        $item = is_array($data) ? ($data['data']['post'] ?? null) : null;
        $imgId = is_array($item) ? ($item['imgs'][0]['id'] ?? null) : null;
        if (!is_array($item) || !is_array($item['videos'] ?? null)) {
            throw new \RuntimeException('视频数据解析失败');
        }
        $videoUrl = $imgId !== null && isset($item['videos'][$imgId]['url'])
            ? $item['videos'][$imgId]['url']
            : '';
        if (!$videoUrl) {
            throw new \RuntimeException('未找到视频 URL');
        }
        return [
            'title' => $item['content'] ?? '',
            'cover' => isset($imgId) ? self::fixUrl('https://file.ippzone.com/img/view/id/' . $imgId) : '',
            'url' => self::fixUrl($videoUrl)
        ];
    }
}
