<?php

declare(strict_types=1);

namespace App\Parsers;

use App\Utils\HttpClient;
use App\Utils\UserAgent;

/**
 * 微信视频号解析器
 */
class ShipinhaoParser extends BaseParser
{
    protected static function getHeaders(): array
    {
        return [
            "User-Agent" => UserAgent::mobile(),
            "Referer" => "https://channels.weixin.qq.com/",
        ];
    }

    public static function parse(string $url): array
    {
        // 视频号链接格式: https://weixin.qq.com/cgi-bin/readtemplate?t=...&vid=...
        // 或 https://channels.weixin.qq.com/cgi-bin/readtemplate?t=...&vid=...
        $query = parse_url($url, PHP_URL_QUERY);
        $vid = null;

        if ($query && preg_match('/vid=([^&]+)/', $query, $match)) {
            $vid = $match[1];
        }

        if (!$vid) {
            // 尝试通过重定向获取 vid
            $location = HttpClient::getLocation($url);
            if ($location) {
                $q = parse_url($location, PHP_URL_QUERY);
                if ($q && preg_match('/vid=([^&]+)/', $q, $match)) {
                    $vid = $match[1];
                }
            }
        }

        if (!$vid) {
            throw new \InvalidArgumentException("无法解析视频号 ID");
        }

        // 通过微信视频号 API 获取信息
        $result = self::fetch(
            "https://channels.weixin.qq.com/cgi-bin/mmfinder-bin/feeds/video",
            json_encode([
                "vid" => $vid,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        if (!$result) {
            throw new \RuntimeException("解析视频信息失败");
        }

        $data = self::parseJson($result["data"]);
        $item = $data["video_info"] ?? $data["data"]["video_info"] ?? $data["data"]["feed"] ?? null;

        if (!$item) {
            throw new \RuntimeException("视频数据解析失败");
        }

        $videoUrl = "";
        // 尝试多种字段名
        foreach (["url", "hd_url", "sd_url", "play_url", "video_url", "url_1", "url_0"] as $key) {
            if (!empty($item[$key])) {
                $videoUrl = $item[$key];
                break;
            }
        }

        // 尝试嵌套字段
        $media = $item["media"] ?? $item["video"] ?? [];
        if (!$videoUrl && $media) {
            foreach (["url", "hd_url", "sd_url", "play_url"] as $key) {
                if (!empty($media[$key])) {
                    $videoUrl = $media[$key];
                    break;
                }
            }
        }

        if (!$videoUrl) {
            throw new \RuntimeException("未找到视频 URL");
        }

        $user = $item["author"] ?? $item["user"] ?? $item["publisher"] ?? [];

        return [
            "author" => $user["name"] ?? $user["nickname"] ?? $item["nickname"] ?? "",
            "uid" => $user["id"] ?? $user["uin"] ?? "",
            "avatar" => $user["avatar"] ?? $user["headimg_url"] ?? "",
            "like" => $item["like_count"] ?? $item["statistics"]["like_count"] ?? 0,
            "time" => $item["create_time"] ?? $item["timestamp"] ?? $item["publish_time"] ?? 0,
            "title" => $item["desc"] ?? $item["description"] ?? $item["title"] ?? "",
            "cover" => $item["cover_url"] ?? $item["cover"] ?? $item["thumbnail"] ?? "",
            "url" => self::fixUrl($videoUrl),
        ];
    }
}
