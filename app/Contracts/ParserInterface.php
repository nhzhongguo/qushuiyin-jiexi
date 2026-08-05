<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Utils\HttpClient;

/**
 * 视频解析器接口
 * 定义所有解析器必须实现的契约
 */
interface ParserInterface
{
    /**
     * 获取解析器支持的域名列表
     * 用于 VideoParser 自动路由
     *
     * @return array<string> 域名列表
     */
    public static function getSupportedDomains(): array;

    /**
     * 获取解析器唯一标识
     *
     * @return string 解析器标识（如 douyin, kuaishou 等）
     */
    public static function getParserKey(): string;

    /**
     * 解析视频 URL
     *
     * @param string $url 视频 URL
     * @return array 解析结果，标准字段：title, author, avatar, url, cover, like, time, uid, music 等
     * @throws \InvalidArgumentException 参数错误（URL 格式无效、不支持的平台等）
     * @throws \RuntimeException 解析失败（网络错误、平台接口变更等）
     */
    public static function parse(string $url): array;

    /**
     * 验证 URL 是否属于该解析器支持的平台
     *
     * @param string $url 待验证的 URL
     * @return bool 是否支持
     */
    public static function supports(string $url): bool;

    /**
     * 获取解析器配置
     *
     * @return array 配置数组
     */
    public static function getConfig(): array;

    /**
     * 获取 HTTP 请求头
     *
     * @return array HTTP 请求头数组
     */
    public static function getHeaders(): array;

    /**
     * 执行 HTTP 请求并统一处理响应
     *
     * @param string $url 请求 URL
     * @param string|array|null $data POST 数据，为 null 则为 GET
     * @return array|false 成功返回响应数据数组，失败返回 false
     */
    public static function fetch(string $url, $data = null): array|false;

    /**
     * 解析 JSON 数据
     *
     * @param string $json JSON 字符串
     * @return array|null 解析成功返回数组，失败返回 null
     */
    public static function parseJson(string $json): ?array;

    /**
     * 修复 URL，确保协议完整
     *
     * @param string $url 原始 URL
     * @return string 修复后的 URL
     */
    public static function fixUrl(string $url): string;
}