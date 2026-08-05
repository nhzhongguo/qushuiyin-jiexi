<?php

namespace App\Utils;

/**
 * 日志工具
 */
class Logger
{
    private const LOG_FILE = __DIR__ . '/../../storage/logs/app.log';

    /**
     * 确保日志目录存在
     */
    private static function ensureLogDir(): void
    {
        $logDir = dirname(self::LOG_FILE);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * 写入日志
     *
     * @param string $level 日志级别
     * @param string $message 消息
     * @param array $context 上下文
     */
    private static function write(string $level, string $message, array $context = []): void
    {
        self::ensureLogDir();

        $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = date('Y-m-d H:i:s') . " [{$level}] {$message}{$contextStr}" . PHP_EOL;

        error_log($logMessage, 3, self::LOG_FILE);
    }

    /**
     * 记录错误日志
     *
     * @param string $message 错误消息
     * @param array $context 上下文信息
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    /**
     * 记录异常
     *
     * @param \Throwable $exception 异常对象
     * @param array $context 上下文信息
     */
    public static function exception(\Throwable $exception, array $context = []): void
    {
        $context['exception'] = get_class($exception);
        $context['file'] = $exception->getFile();
        $context['line'] = $exception->getLine();
        $context['trace'] = $exception->getTraceAsString();

        self::write('EXCEPTION', $exception->getMessage(), $context);
    }
}

