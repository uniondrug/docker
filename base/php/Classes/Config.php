<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-11-05
 */

/**
 * Class Config
 */
class Config extends Console
{
    const DEFAULT_DOCKER_IMAGE = "uniondrug:base";
    const DEFAULT_DOCKER_NAME = "sketch";
    const DEFAULT_SERVICE_MODE = "swoole";
    private $base;
    private $configData = null;
    private $configEnvironment = null;

    public function __construct(Base $base)
    {
        $this->base = $base;
        $this->_scanner();
    }

    /**
     * @param string $key
     * @return null
     */
    public function get(string $key)
    {
        $find = 0;
        $buffer = $this->configData;
        foreach (explode('.', $key) as $k) {
            $find++;
            if (!is_array($buffer)) {
                $find = -1;
                break;
            }
            if (!isset($buffer[$k])) {
                $find = -2;
                break;
            }
            $buffer = $buffer[$k];
        }
        return $find > 0 ? $buffer : null;
    }

    public function getImage()
    {
        $image = $this->get('dockerImage');
        $image || $image = self::DEFAULT_DOCKER_IMAGE;
        return $image;
    }

    public function getMode()
    {
        $mode = $this->get('app.dockerMode');
        $mode || $mode = self::DEFAULT_SERVICE_MODE;
        return $mode;
    }

    public function getName()
    {
        $mode = $this->get('app.appName');
        $mode || $mode = self::DEFAULT_DOCKER_NAME;
        return $mode;
    }

    public function getPort()
    {
        $host = $this->get('server.host');
        if ($host && preg_match("/:(\d+)/", $host, $m)) {
            return $m[1];
        }
        return 0;
    }

    /**
     * 扫描
     */
    private function _scanner()
    {
        $env = $this->base->getEnvironment();
        // 1. exists
        if ($env === $this->configEnvironment && $this->configData !== null) {
            return $this->configData;
        }
        // 2. scanner directory
        $path = $this->base->getConfigPath();
        $this->debug("[CONFIG] 扫描[%s]下的配置文件并提取[%s]片段.", $path, $env);
        if (!is_dir($path)) {
            $this->error("[CONFIG] 路径[%s]不是合法的目录.", $path);
            return false;
        }
        $scan = dir($path);
        $result = [];
        while (false !== ($entry = $scan->read())) {
            // 1. not config file
            if (preg_match("/^(\S+)\.php$/", $entry, $m) === 0) {
                continue;
            }
            // 2. include
            $tmp = include("{$path}/{$entry}");
            if (!is_array($tmp)) {
                $this->warning("[CONFIG] 配置文件[%s]未返回有效的数组.", $path.'/'.$entry);
                continue;
            }
            // 3. defaults
            $data = isset($tmp['default']) && is_array($tmp['default']) ? $tmp['default'] : [];
            $envs = isset($tmp[$env]) && is_array($tmp[$env]) ? $tmp[$env] : [];
            $result[$m[1]] = array_merge($data, $envs);
            $this->debug("[CONFIG] 从[%s/%s]文件中导出[%s]配置片段.", $path, $entry, $m[1]);
        }
        $scan->close();
        $this->configEnvironment = $env;
        $this->configData = $result;
        return true;
    }
}
