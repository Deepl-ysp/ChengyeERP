<?php
// utils/debug.php

/**
 * 通用调试工具类（零耦合）
 *
 * 功能：
 * - 打印变量（支持多种格式）
 * - 性能计时（微秒级）
 * - 调试日志写入文件
 * - 调用栈追踪
 * - 支持开关控制（生产环境可关闭）
 */
class Debug
{
    /**
     * @var bool 是否启用调试模式
     */
    protected $enabled = true;

    /**
     * @var string 日志文件路径
     */
    protected $logFile = null;

    /**
     * @var array 计时器存储
     */
    protected $timers = [];

    /**
     * @var string 输出格式：'print_r', 'var_dump', 'json'
     */
    protected $outputFormat = 'print_r';

    /**
     * @var bool 是否在输出时终止脚本执行
     */
    protected $haltOnDump = false;

    /**
     * 构造函数
     *
     * @param bool $enabled 是否启用调试
     * @param string|null $logFile 日志文件路径（可选）
     */
    public function __construct(bool $enabled = true, ?string $logFile = null)
    {
        $this->enabled = $enabled;
        $this->logFile = $logFile;
    }

    /**
     * 设置是否启用调试
     *
     * @param bool $enabled
     * @return $this
     */
    public function setEnabled(bool $enabled)
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * 设置输出格式
     *
     * @param string $format 'print_r', 'var_dump', 'json'
     * @return $this
     */
    public function setOutputFormat(string $format)
    {
        if (in_array($format, ['print_r', 'var_dump', 'json'])) {
            $this->outputFormat = $format;
        }
        return $this;
    }

    /**
     * 设置是否在 dump 后终止脚本
     *
     * @param bool $halt
     * @return $this
     */
    public function setHaltOnDump(bool $halt)
    {
        $this->haltOnDump = $halt;
        return $this;
    }

    /**
     * 设置日志文件路径
     *
     * @param string $logFile
     * @return $this
     */
    public function setLogFile(string $logFile)
    {
        $this->logFile = $logFile;
        return $this;
    }

    /**
     * 打印变量（输出到浏览器）
     *
     * @param mixed $var 要输出的变量
     * @param string $label 标签（可选）
     * @param bool $return 是否返回字符串而不是直接输出
     * @return string|null
     */
    public function dump($var, string $label = '', bool $return = false)
    {
        if (!$this->enabled) {
            return $return ? '' : null;
        }

        $output = '';

        if ($label) {
            $output .= "========== [{$label}] ==========\n";
        }

        switch ($this->outputFormat) {
            case 'var_dump':
                ob_start();
                var_dump($var);
                $output .= ob_get_clean();
                break;
            case 'json':
                $output .= json_encode($var, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;
            case 'print_r':
            default:
                $output .= print_r($var, true);
                break;
        }

        $output .= "\n";

        // 非 CLI 模式下用 <pre> 包裹（不再自我赋值）
        if (PHP_SAPI !== 'cli') {
            $output = "<pre style='background:#f5f5f5;padding:10px;border:1px solid #ddd;margin:10px 0;'>"
                . htmlspecialchars($output)
                . "</pre>";
        }

        if ($return) {
            return $output;
        }

        echo $output;

        if ($this->haltOnDump) {
            exit;
        }

        return null;
    }

    /**
     * 打印变量并终止脚本（快捷方法）
     *
     * @param mixed $var
     * @param string $label
     */
    public function dd($var, string $label = '')
    {
        $this->setHaltOnDump(true)->dump($var, $label);
    }

    /**
     * 写入调试日志（文件）
     *
     * @param mixed $data 要记录的数据
     * @param string $label 标签
     * @param string $level 日志级别：'info', 'warning', 'error'
     * @return bool 写入是否成功
     */
    public function log($data, string $label = '', string $level = 'info'): bool
    {
        if (!$this->enabled || !$this->logFile) {
            return false;
        }

        // 格式化数据
        if (is_string($data)) {
            $content = $data;
        } else {
            $content = print_r($data, true);
        }

        // 构建日志条目
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf(
            "[%s] [%s] %s%s%s\n",
            $timestamp,
            strtoupper($level),
            $label ? "[{$label}] " : '',
            $content,
            str_repeat('-', 60)
        );

        // 写入文件
        return file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX) !== false;
    }

    /**
     * 开始计时
     *
     * @param string $name 计时器名称
     * @return $this
     */
    public function timerStart(string $name)
    {
        $this->timers[$name] = [
            'start' => microtime(true),
            'end' => null,
            'elapsed' => null,
        ];
        return $this;
    }

    /**
     * 结束计时
     *
     * @param string $name 计时器名称
     * @return float|null 耗时（秒），如果计时器不存在返回 null
     */
    public function timerEnd(string $name): ?float
    {
        if (!isset($this->timers[$name])) {
            return null;
        }

        $this->timers[$name]['end'] = microtime(true);
        $this->timers[$name]['elapsed'] = $this->timers[$name]['end'] - $this->timers[$name]['start'];
        return $this->timers[$name]['elapsed'];
    }

    /**
     * 获取计时器结果
     *
     * @param string $name 计时器名称
     * @return array|null
     */
    public function timerGet(string $name): ?array
    {
        return $this->timers[$name] ?? null;
    }

    /**
     * 获取所有计时器
     *
     * @return array
     */
    public function timerGetAll(): array
    {
        return $this->timers;
    }

    /**
     * 打印计时器结果
     *
     * @param string $name
     * @param string $label
     * @return string|null
     */
    public function timerDump(string $name, string $label = '')
    {
        if (!$this->enabled) {
            return null;
        }

        $timer = $this->timerGet($name);
        if (!$timer) {
            return $this->dump("计时器 '{$name}' 不存在", $label);
        }

        $elapsed = $timer['elapsed'] ?? '未结束';
        if (is_float($elapsed)) {
            $elapsed = number_format($elapsed, 6) . ' 秒';
        }

        return $this->dump("{$name}: {$elapsed}", $label);
    }

    /**
     * 获取当前调用栈（用于追踪）
     *
     * @param int $limit 返回的栈帧数量，0 表示全部
     * @return array
     */
    public function getTrace(int $limit = 0): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        // 移除 Debug 类自身的方法调用
        array_shift($trace);

        if ($limit > 0) {
            $trace = array_slice($trace, 0, $limit);
        }

        return $trace;
    }

    /**
     * 打印调用栈
     *
     * @param int $limit
     * @param string $label
     * @return string|null
     */
    public function dumpTrace(int $limit = 5, string $label = '调用栈')
    {
        if (!$this->enabled) {
            return null;
        }

        $trace = $this->getTrace($limit);
        $output = '';
        foreach ($trace as $index => $frame) {
            $file = $frame['file'] ?? 'unknown';
            $line = $frame['line'] ?? '?';
            $function = $frame['function'] ?? '?';
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $output .= sprintf(
                "#%d %s(%d): %s%s%s()\n",
                $index,
                basename($file),
                $line,
                $class,
                $type,
                $function
            );
        }

        return $this->dump($output, $label);
    }

    /**
     * 内存使用情况（MB）
     *
     * @return float
     */
    public function memoryUsage(): float
    {
        return memory_get_usage(true) / 1024 / 1024;
    }

    /**
     * 峰值内存使用情况（MB）
     *
     * @return float
     */
    public function memoryPeak(): float
    {
        return memory_get_peak_usage(true) / 1024 / 1024;
    }

    /**
     * 打印内存使用情况
     *
     * @param string $label
     * @return string|null
     */
    public function dumpMemory(string $label = '内存使用')
    {
        if (!$this->enabled) {
            return null;
        }

        $current = number_format($this->memoryUsage(), 2);
        $peak = number_format($this->memoryPeak(), 2);
        $output = "当前内存: {$current} MB\n峰值内存: {$peak} MB";

        return $this->dump($output, $label);
    }

    /**
     * 记录 SQL 查询（辅助方法，用于调试数据库操作）
     *
     * @param string $sql
     * @param array $params
     * @param string $label
     * @return bool
     */
    public function logSql(string $sql, array $params = [], string $label = 'SQL'): bool
    {
        if (!$this->enabled) {
            return false;
        }

        // 替换占位符（简单模拟，仅用于调试查看）
        $sqlDebug = $sql;
        foreach ($params as $key => $value) {
            if (is_string($value)) {
                $value = "'" . addslashes($value) . "'";
            } elseif ($value === null) {
                $value = 'NULL';
            }
            $sqlDebug = str_replace(":{$key}", $value, $sqlDebug);
        }

        return $this->log($sqlDebug, $label);
    }
}