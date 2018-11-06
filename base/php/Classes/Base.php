<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-11-05
 */

/**
 * Class Base
 */
class Base extends Console
{
    private $basePath = "/uniondrug/app";
    private $environment = "development";
    private $targetPath = "/usr/local/bin";
    private $options = [];

    /**
     * Base constructor.
     */
    public function __construct()
    {
        // 1. read options
        $k = null;
        $e = "/^[\-]+([^=]+)[=]*(.*)$/";
        $a = isset($_SERVER['argv']) && is_array($_SERVER['argv']) ? $_SERVER['argv'] : [];
        foreach ($a as $v) {
            $v = trim($v);
            // 2. empty
            if ($v === '') {
                continue;
            }
            // 3. value
            if (preg_match($e, $v, $m) === 0) {
                if ($k !== null) {
                    $this->options[$k] = $v;
                    $k = null;
                }
                continue;
            }
            // 4. parse
            $m[1] = trim($m[1]);
            $m[2] = trim($m[2]);
            // 5. for next
            if ($m[2] === '') {
                $k = $m[1];
                $this->options[$k] = false;
                continue;
            }
            // 6. for key/value
            $this->options[$m[1]] = $m[2];
        }
        // 7. base path
        $basePath = $this->getOption('base-path');
        $basePath && $this->basePath = $basePath;
        $this->options['base-path'] = $this->basePath;
        // 8. target path
        $targetPath = $this->getOption('target-path');
        $targetPath && $this->targetPath = $targetPath;
        $this->options['target-path'] = $this->targetPath;
        // 9. environment
        $environment = $this->getOption('e');
        $environment || $environment = $this->getOption('env');
        $environment || $this->environment = $environment;
        $this->options['env'] = $this->environment;
    }

    /**
     * 应用根目录
     * @return string
     */
    public function getBasePath()
    {
        return $this->basePath;
    }

    /**
     * 配置文件所在目录
     * @return string
     */
    public function getConfigPath()
    {
        return $this->basePath."/config";
    }

    /**
     * Consul配置数据
     */
    public function getConsulData()
    {
        $file = $this->basePath."/consul.json";
        if (!file_exists($file)){
            return [];
        }
        $json = file_get_contents($file);
        $data = json_decode($json, JSON_UNESCAPED_UNICODE);
        if (!is_array($data)){
            return [];
        }
        return $data;
    }

    /**
     * 读取环境名称
     * @return string
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * 读取选项值
     * @param string $key
     * @return bool|string|null
     */
    public function getOption(string $key)
    {
        if (isset($this->options[$key])) {
            return $this->options[$key];
        }
        return null;
    }

    /**
     * 读取全部选项
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @return string
     */
    public function getOwner()
    {
        return 'uniondrug:uniondrug';
    }

    public function getTargetPath(){
        return $this->targetPath;
    }
}
