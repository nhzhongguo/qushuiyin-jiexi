<?php

declare(strict_types=1);

namespace App\Parsers;

use App\Contracts\AbstractParser;
use App\Utils\HttpClient;
use App\Utils\UserAgent;

/**
 * 快手视频解析器
 */
class KuaishouParser extends AbstractParser
{
    public static function getHeaders(): array
    {
        return [
            "User-Agent" => UserAgent::mobile(),
            "Referer" => "https://www.kuaishou.com/",
        ];
    }

    public static function parse(string $url): array
    {
        // 解析短链接或完整链接获取 photoId
        $location = HttpClient::getLocation($url);
        $target = $location ?: $url;

        // 快手分享短链接可能是 /photo/xxx 或 /v/xxx 格式，重定向后才有完整 photoId
        $photoId = '';
        if (preg_match('/(?:photo|short-video)\/(\w+)/i', $target, $match)) {
            $photoId = $match[1];
        } elseif (preg_match('#/(?:v|video)/(\w+)#i', $target, $m2)) {
            $photoId = $m2[1];
        }

        if ($photoId === '') {
            throw new \InvalidArgumentException("无法解析快手视频 ID");
        }

        $result = self::fetch("https://www.kuaishou.com/graphql", json_encode([
            "operationName" => "visionPhotoDetail",
            "query" => "query visionPhotoDetail(\$photoId: String) {\n  visionPhotoDetail(photoId: \$photoId) {\n    photo {\n      id\n      caption\n      coverUrl\n      duration\n      likeCount\n      viewCount\n      photoUrl\n      timestamp\n      user {\n        id\n        name\n        avatar\n      }\n      music {\n        id\n        name\n        author\n        coverUrl\n      }\n    }\n  }\n}",
            "variables" => ["photoId" => $photoId],
        ], JSON_UNESCAPED_SLASHES));

        if (!$result) {
            throw new \RuntimeException("解析视频信息失败");
        }

        $data = self::parseJson($result["data"]);
        $photo = $data["data"]["visionPhotoDetail"]["photo"] ?? null;
        if (!$photo) {
            throw new \RuntimeException("视频数据解析失败");
        }

        $videoUrl = $photo["photoUrl"] ?? "";
        if (!$videoUrl) {
            throw new \RuntimeException("未找到视频 URL");
        }

        return [
            "author" => $photo["user"]["name"] ?? "",
            "uid" => $photo["user"]["id"] ?? "",
            "avatar" => $photo["user"]["avatar"] ?? "",
            "like" => $photo["likeCount"] ?? 0,
            "time" => $photo["timestamp"] ?? 0,
            "title" => $photo["caption"] ?? "",
            "cover" => $photo["coverUrl"] ?? "",
            "url" => self::fixUrl($videoUrl),
            "music" => [
                "author" => $photo["music"]["author"] ?? "",
                "avatar" => $photo["music"]["coverUrl"] ?? "",
            ],
        ];
    }
}

