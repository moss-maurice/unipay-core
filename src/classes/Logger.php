<?php

namespace mmaurice\unipay\core\classes;

class Logger
{
    const NO_MESSAGES = 0;
    const ALL_MESSAGES = 1;
    const IMPORTANT_MESSAGES = 2;
    const ERROR_MESSAGES = 3;

    const LEVEL_NORMAL = 0;
    const LEVEL_IMPORTANT = 1;
    const LEVEL_ERROR = 2;

    protected $level;
    protected $source;

    public function __construct($source = STDOUT, $level = self::ALL_MESSAGES)
    {
        $this->level = intval($level);
        $this->source = $source;
    }

    protected function checkMessageLevel($level)
    {
        switch ($this->level) {
            case self::ALL_MESSAGES:
                return in_array($level, [self::LEVEL_ERROR, self::LEVEL_IMPORTANT, self::LEVEL_NORMAL]);

                break;
            case self::IMPORTANT_MESSAGES:
                return in_array($level, [self::LEVEL_ERROR, self::LEVEL_IMPORTANT]);

                break;
            case self::ERROR_MESSAGES:
                return in_array($level, [self::LEVEL_ERROR]);

                break;
            case self::NO_MESSAGES:
            default:

                break;
        }

        return false;
    }

    public function set($line, $options = [], $level = self::LEVEL_NORMAL)
    {
        if ($this->checkMessageLevel($level)) {
            $rawLine = date('Y-m-d H:i:s') . ' > ' . $line;

            if (is_array($options) and !empty($options)) {
                foreach ($options as $key => $option) {
                    $options[$key] = $key . ': ' . $option;
                }

                $rawLine .= ' (' . implode('; ', array_values($options)) . ')';
            }

            fwrite($this->source, $rawLine . PHP_EOL);
        }
    }
}
