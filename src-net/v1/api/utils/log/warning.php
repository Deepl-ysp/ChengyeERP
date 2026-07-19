<?php
// utils/warning.php

/**
 * 通用警告处理类（零耦合）
 * 
 * 用于返回业务性警告，不抛出异常，HTTP 状态码通常为 200 或 4xx
 * 与 Error 类完全独立，互不依赖
 */
class Warning
{
    /**
     * @var bool 是否显示调试信息
     */
    protected $debug = false;

    /**
     * @var callable|null 日志记录回调
     */
    protected $logger = null;

    /**
     * @var int 默认 HTTP 状态码（警告通常为 200，但也可自定义）
     */
    protected $defaultHttpCode = 200;

    /**
     * @var string 默认警告消息
     */
    protected $defaultMessage = '警告';

    /**
     * 构造函数
     *
     * @param bool $debug 是否显示调试信息（如文件、行号等）
     * @param callable|null $logger 日志记录器，接受警告数据数组
     * @param int $defaultHttpCode 默认状态码
     * @param string $defaultMessage 默认消息
     */
    public function __construct(
        bool $debug = false,
        ?callable $logger = null,
        int $defaultHttpCode = 200,
        string $defaultMessage = '警告'
    ) {
        $this->debug = $debug;
        $this->logger = $logger;
        $this->defaultHttpCode = $defaultHttpCode;
        $this->defaultMessage = $defaultMessage;
    }

    /**
     * 设置调试模式
     *
     * @param bool $debug
     * @return $this
     */
    public function setDebug(bool $debug)
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * 设置日志记录器
     *
     * @param callable $logger
     * @return $this
     */
    public function setLogger(callable $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * 返回一个警告响应（JSON）
     *
     * @param string $message 警告消息
     * @param int $httpCode HTTP 状态码（默认 200）
     * @param array $extra 附加数据（如警告级别、建议等）
     * @param string $level 警告级别（如 'warning', 'notice', 'deprecation'）
     * @return string JSON 响应
     */
    public function warn(
        string $message,
        int $httpCode = 200,
        array $extra = [],
        string $level = 'warning'
    ): string {
        $httpCode = $httpCode ?? $this->defaultHttpCode;

        // 构建响应体
        $response = [
            'warning' => true,
            'level'   => $level,
            'message' => $message,
        ];

        // 调试模式附加信息
        if ($this->debug) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = $trace[1] ?? $trace[0] ?? null;
            if ($caller) {
                $response['file'] = $caller['file'] ?? 'unknown';
                $response['line'] = $caller['line'] ?? 0;
            }
        }

        // 合并额外数据（覆盖同名字段？为了避免覆盖，深合并但简单处理）
        if (!empty($extra)) {
            $response = array_merge($response, $extra);
        }

        // 记录日志（如果设置了 logger）
        if ($this->logger) {
            call_user_func($this->logger, $response, $httpCode);
        }

        // 设置状态码和头
        http_response_code($httpCode);
        header('Content-Type: application/json');
        return json_encode($response, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 快速返回警告（静态调用，使用默认配置）
     * 注意：静态方法无法使用实例配置，若需要自定义请实例化后调用 warn()
     *
     * @param string $message
     * @param int $httpCode
     * @param array $extra
     * @param string $level
     * @return string
     */
    public static function send(
        string $message,
        int $httpCode = 200,
        array $extra = [],
        string $level = 'warning'
    ): string {
        // 临时创建实例，使用默认配置（无调试、无日志）
        $instance = new self(false, null, $httpCode, $message);
        return $instance->warn($message, $httpCode, $extra, $level);
    }

    /**
     * 快捷方法：返回资源未找到警告
     *
     * @param string $resource 资源名
     * @param int $httpCode 通常为 404，但警告语义下可为 200
     * @return string
     */
    public function notFound(string $resource, int $httpCode = 404): string
    {
        return $this->warn("{$resource} 未找到", $httpCode, ['resource' => $resource], 'notice');
    }

    /**
     * 快捷方法：参数校验失败警告
     *
     * @param string $field 字段名
     * @param string $reason 原因
     * @param int $httpCode 通常为 400
     * @return string
     */
    public function validationFailed(string $field, string $reason, int $httpCode = 400): string
    {
        return $this->warn("字段 {$field} 校验失败：{$reason}", $httpCode, ['field' => $field], 'validation');
    }
}