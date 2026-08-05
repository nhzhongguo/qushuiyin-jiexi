<?php

declare(strict_types=1);

namespace App\Parsers;

use App\Utils\HttpClient;
use App\Utils\UserAgent;

/**
 * 小红书视频/笔记解析器
 */
class XiaohongshuParser extends BaseParser
{
    protected static function getHeaders(): array
    {
        return [
            "User-Agent" => UserAgent::mobile(),
            "Referer" => "https://www.xiaohongshu.com/",
        ];
    }

    public static function parse(string $url): array
    {
        $location = HttpClient::getLocation($url);
        $target = $location ?: $url;

        // 提取 note_id
        if (!preg_match('/explore\/([a-f0-9]+)|discovery\/item\/([a-f0-9]+)/i', $target, $match)) {
            // 尝试匹配短链接格式
            if (preg_match('/(?:discovery\/item\/|explore\/)([a-f0-9]+)/i', $target, $m2)) {
                $noteId = $m2[1];
            } else {
                throw new \InvalidArgumentException("无法解析小红书笔记 ID");
            }
        } else {
            $noteId = $match[1] ?: $match[2];
        }

        // 小红书没有公开的无 token API，通过页面 SSR 数据提取
        $result = self::fetch("https://www.xiaohongshu.com/explore/{$noteId}");

        if (!$result) {
            throw new \RuntimeException("解析笔记信息失败");
        }

        $html = $result["data"];

        // 从 window.__INITIAL_STATE__ 提取数据
        if (!preg_match('/window\.__INITIAL_STATE__\s*=\s*({.*?});\s*</s', $html, $matches)) {
            throw new \RuntimeException("提取页面数据失败");
        }

        $data = self::parseJson(trim($matches[1]));
        $note = $data["note"]["noteDetailMap"] ?? null;

        // 尝试不同的数据路径
        if (!$note) {
            foreach ($data["note"] ?? [] as $key => $val) {
                if (is_array($val) && isset($val["note"])) {
                    $note = $val;
                    break;
                }
            }
        }

        $noteData = $note["note"] ?? $note;
        if (!$noteData) {
            throw new \RuntimeException("笔记数据解析失败");
        }

        $videoUrl = "";
        // 视频链接可能在不同位置
        $video = $noteData["video"] ?? [];
        if ($video) {
            $media = $video["media"] ?? [];
            $stream = $media["stream"] ?? [];
            if ($stream) {
                $masterUrl = $stream["master_url"] ?? "";
                if ($masterUrl) {
                    $videoUrl = $masterUrl;
                }
            }
            // 备用路径
            if (!$videoUrl) {
                $consumerList = $video["consumer"] ?? [];
                if ($consumerList && isset($consumerList["origin_url_key"])) {
                    $videoUrl = $video[$consumerList["origin_url_key"]] ?? "";
                }
                if (!$videoUrl) {
                    $videoUrl = $video["url"] ?? "";
                }
            }
        }

        if (!$videoUrl) {
            throw new \RuntimeException("未找到视频 URL（该笔记可能为图文）");
        }

        $user = $noteData["user"] ?? [];
        return [
            "author" => $user["nickname"] ?? "",
            "uid" => $user["userId"] ?? "",
            "avatar" => $user["avatar"] ?? "",
            "like" => $noteData["likedCount"] ?? $noteData["interactInfo"]["likedCount"] ?? 0,
            "time" => $noteData["time"] ?? $noteData["createTime"] ?? 0,
            "title" => $noteData["title"] ?? $noteData["desc"] ?? "",
            "cover" => ($noteData["imageList"][0]["urlDefault"] ?? $noteData["cover"]["urlDefault"] ?? ""),
            "url" => self::fixUrl($videoUrl),
        ];
    }
}
