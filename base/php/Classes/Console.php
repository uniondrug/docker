<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-11-05
 */

/**
 * Class Console
 */
abstract class Console extends \stdClass
{
    protected function debug(string $format, ... $args)
    {
        $this->printDebugMessage("DEBUG", $format, ... $args);
    }

    protected function error(string $format, ... $args)
    {
        $this->printDebugMessage("ERROR", $format, ... $args);
    }

    protected function warning(string $format, ... $args)
    {
        $this->printDebugMessage("WARNING", $format, ... $args);
    }

    private function printDebugMessage(string $level, string $format, ... $args)
    {
        echo sprintf("[%s] %s\n", $level, call_user_func_array('sprintf', array_merge([
            $format
        ], $args)));
    }
}
