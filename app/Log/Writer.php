<?php

namespace App\Log;

use InvalidArgumentException;

class Writer implements LoggerInterface
{
    /**
     * The Log levels.
     *
     * @var array
     */
    protected $levels = [
        'debug' => LogLevel::DEBUG,
        'info' => LogLevel::INFO,
        'notice' => LogLevel::NOTICE,
        'warning' => LogLevel::WARNING,
        'error' => LogLevel::ERROR,
        'critical' => LogLevel::CRITICAL,
        'alert' => LogLevel::ALERT,
        'emergency' => LogLevel::EMERGENCY,
    ];

    protected $log_path;  //log路径
    protected $log;        //log类型
    protected $logfile;     //log文件

    /**
     * Writer constructor.
     */
    public function __construct($logfile = null)
    {
        if (is_null($logfile)) {
            $this->logfile = config("app.logfile", "larvel.log");
        } else {
            $this->logfile = $logfile;
        }
        $this->log = config("app.log", "single");
        $this->log_path = config("app.log_path");
        if (is_null($this->log_path)) {
            throw new InvalidArgumentException('Invalid logpath');
        }
    }

    /**
     * Log an emergency message to the logs.
     *
     * @param  string $message
     * @param  array $context
     * @return void
     */
    public function emergency($message, array $context = [])
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log an alert message to the logs.
     *
     * @param  string $message
     * @param  array $context
     * @return void
     */
    public function alert($message, array $context = [])
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log a critical message to the logs.
     *
     * @param  string $message
     * @param  array $context
     * @return void
     */
    public function critical($message, array $context = [])
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log an error message to the logs.
     *
     * @param  string $message
     * @param  array $context
     * @return void
     */
    public function error($message, array $context = [])
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log a warning message to the logs.
     *
     * @param  string $message
     * @param  array $context
     * @return void
     */
    public function warning($message, array $context = [])
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log a notice to the logs.
     *
     * @param  string $message
     * @param  array $context
     * @return void
     */
    public function notice($message, array $context = [])
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log an informational message to the logs.
     *
     * @param  string $message
     * @param  array $context
     * @return void
     */
    public function info($message, array $context = [])
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log a debug message to the logs.
     *
     * @param  string $message
     * @param  array $context
     * @return void
     */
    public function debug($message, array $context = [])
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Log a message to the logs.
     *
     * @param  string $level
     * @param  string $message
     * @param  array $context
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        $this->writeLog($level, $message, $context);
    }

    /**
     * Dynamically pass log calls into the writer.
     *
     * @param  string $level
     * @param  string $message
     * @param  array $context
     * @return void
     */
    public function write($level, $message, array $context = [])
    {
        $this->writeLog($level, $message, $context);
    }

    /**
     * Write a message to Monolog.
     *
     * @param  string $level
     * @param  string $message
     * @param  array $context
     * @return void
     */
    protected function writeLog($level, $message, $context)
    {

        $level = strtoupper($this->parseLevel($level));

        $this->fireLogEvent($level, $message = $this->formatMessage($message), $context);
    }


    /**
     * @param string $level
     * @param string $message
     * @param array $context
     */
    protected function fireLogEvent($level, $message, $context)
    {
        // 构建一个花括号包含的键名的替换数组
        $replace = array();
        foreach ($context as $key => $val) {
            $replace['{' . $key . '}'] = $val;
        }
        $message = date('[Y-m-d H:i:s]') . ' local.' . $level . ': ' . strtr($message, $replace) . PHP_EOL;
        switch ($this->log) {
            case 'daily':
                if (!is_dir($this->log_path . date('Ymd'))) {
                    mkdir($this->log_path . date('Ymd'));
                }
                file_put_contents($this->log_path . date('Ymd') ."/". $this->logfile, $message, FILE_APPEND);
                break;
            case 'single':
                file_put_contents($this->log_path . $this->logfile, $message, FILE_APPEND);
                break;
            default:
                throw new InvalidArgumentException('Invalid log style.');
                break;
        }


    }


    /**
     * Format the parameters for the logger.
     *
     * @param  mixed $message
     * @return mixed
     */
    protected function formatMessage($message)
    {
        if (is_array($message)) {
            return var_export($message, true);
        }
        return $message;
    }

    /**
     * Parse the string level into a Monolog constant.
     *
     * @param  string $level
     * @return int
     *
     * @throws \InvalidArgumentException
     */
    protected function parseLevel($level)
    {
        if (isset($this->levels[$level])) {
            return $this->levels[$level];
        }

        throw new InvalidArgumentException('Invalid log level.');
    }


}
