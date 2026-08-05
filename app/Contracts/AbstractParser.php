<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Utils\Config;
use App\Utils\HttpClient;

/**
 * 视频解析器抽象基类
 * 实现 ParserInterface 的通用方法，具体解析器只需实现核心逻辑
 */
abstract class AbstractParser implements ParserInterface
{
    /**
     * 获取解析器支持的域名列表
     * 子类可重写或通过配置获取
     *
     * @return array<string> 域名列表
     */
    public static function getSupportedDomains(): array
    {
        $config = static::getConfig();
        return $config['domains'] ?? [];
    }

    /**
     * 获取解析器唯一标识
     * 默认使用类名的 snake_case 形式（去掉 Parser 后缀）
     *
     * @return string 解析器标识
     */
    public static function getParserKey(): string
    {
        $className = (new \ReflectionClass(static::class))->getShortName();
        // 例如 DouyinParser -> douyin
        return strtolower(preg_replace('/Parser$/', '', $className));
    }

    /**
     * 验证 URL 是否属于该解析器支持的平台
     *
     * @param string $url 待验证的 URL
     * @return bool 是否支持
     */
    public static function supports(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }

        $host = strtolower(preg_replace('/^www\./', '', $host));

        foreach (static::getSupportedDomains() as $domain) {
            if ($host === $domain || substr($host, -strlen('.' . $domain)) === '.' . $domain) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取解析器配置
     * 默认从 config/parser.php 读取
     *
     * @return array 配置数组
     */
    public static function getConfig(): array
    {
        $key = static::getParserKey();
        return Config::get("parser.{$key}", []);
    }

    /**
     * 获取 HTTP 请求头
     * 子类必须实现
     *
     * @return array HTTP 请求头数组
     */
    abstract public static function getHeaders(): array;

    /**
     * 解析视频 URL
     * 子类必须实现核心解析逻辑
     *
     * @param string $url 视频 URL
     * @return array 解析结果
     * @throws \InvalidArgumentException 参数错误
     * @throws \RuntimeException 解析失败
     */
    abstract public static function parse(string $url): array;

    /**
     * 执行 HTTP 请求并统一处理响应
     * 有 $data 则为 POST，无 $data 则为 GET
     *
     * @param string $url 请求 URL
     * @param string|array|null $data POST 数据，为 null 则为 GET
     * @return array|false 成功返回响应数据数组，失败返回 false
     */
    public static function fetch(string $url, $data = null): array|false
    {
        $result = HttpClient::request($url, $data, static::getHeaders());
        return ($result['success'] && $result['http_code'] === 200) ? $result : false;
    }

    /**
     * 解析 JSON 数据
     *
     * @param string $json JSON 字符串
     * @return array|null 解析成功返回数组，失败返回 null
     */
    public static function parseJson(string $json): ?array
    {
        if (empty($json)) {
            return null;
        }

        $data = json_decode($json, true);
        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }

    /**
     * 修复 URL，确保协议完整
     *
     * @param string $url 原始 URL
     * @return string 修复后的 URL
     */
    public static function fixUrl(string $url): string
    {
        if (empty($url)) {
            return '';
        }
        return strpos($url, 'http') === 0 ? $url : 'https:' . $url;
    }

    /**
     * 提取视频 ID
     * 子类可重写实现特定的 ID 提取逻辑
     *
     * @param string $url 视频 URL
     * @return string|null 视频 ID
     */
    protected static function extractVideoId(string $url): ?string
    {
        // 默认尝试提取 URL 中的长数字 ID
        if (preg_match('/\b\d{15,25}\b/', $url, $match)) {
            return $match[0];
        }
        return null;
    }

    /**
     * 构建标准结果数组
     * 子类可调用此方法确保返回格式一致
     *
     * @param array $data 原始数据
     * @return array 标准结果
     */
    protected static function buildResult(array $data): array
    {
        return [
            'title' => $data['title'] ?? '',
            'author' => $data['author'] ?? '',
            'avatar' => $data['avatar'] ?? '',
            'url' => $data['url'] ?? '',
            'cover' => $data['cover'] ?? '',
            'like' => $data['like'] ?? 0,
            'time' => $data['time'] ?? 0,
            'uid' => $data['uid'] ?? '',
            'music' => $data['music'] ?? null,
            'images' => $data['images'] ?? [],
            'platform' => static::getParserKey(),
        ];
    }
}
