<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-11-05
 */

/**
 * 导出安装结果
 */
class ExportInstall extends Export
{
    private $entrypoint = '';
    private $saveAs = 'entrypoint';

    /**
     * ExportInstall constructor.
     * @param Base   $base
     * @param Config $config
     */
    public function __construct(Base $base, Config $config)
    {
        parent::__construct($base, $config);
        $this->entrypoint = '';
    }

    public function run()
    {
        $this->debug("开始导出install脚本");
        // 1. 加入Shell片段
        $this->appendHeader();
        $this->appendVariables();
        $this->appendConsulData();
        // 2. functions
        $this->appendDoStart();
        $this->appendDoStop();
        $this->appendDoRegister();
        $this->appendDoDeregister();
        //        // 3. switch
        $this->appendDebugger();
        $this->appendSwitch();
        // 4. 导出Shell文件
        $target = $this->base->getOption('target-path').'/'.$this->saveAs;
        $this->debug("导出为[{$target}]可执行文件");
        if ($fp = @fopen($target, 'wb+')) {
            fwrite($fp, $this->entrypoint);
            fclose($fp);
            $this->debug("导出成功");
        } else {
            $this->error("导出失败");
        }
    }

    /**
     * 添加内容
     * @param string $line
     * @return $this
     */
    private function append(string $line)
    {
        $line = trim($line);
        $this->entrypoint .= "{$line}\n";
        return $this;
    }

    /**
     * 添加分隔符
     * @return $this
     */
    private function appendSeparator()
    {
        $this->append("\n\n");
        return $this;
    }

    /**
     * 加入Shell头
     * @return $this
     */
    private function appendHeader()
    {
        $this->debug("添加Shell头信息");
        $this->append($this->renderShellHeader());
        return $this;
    }

    /**
     * 加入Shell变量
     * @return $this
     */
    private function appendVariables()
    {
        $this->debug("添加变量信息");
        $this->appendSeparator()->append($this->renderShellArguments())->appendSeparator()->append($this->renderDefaultArguments());
        return $this;
    }

    private function appendConsulData()
    {
        $this->debug("添加Consul信息");
        $this->appendSeparator()->append($this->renderConsulData());
        return $this;
    }

    /**
     * @return $this
     */
    private function appendSwitch()
    {
        $this->appendSeparator()->append($this->renderInstallSwitch());
        return $this;
    }

    private function appendDebugger()
    {
        $this->append("echo \"[本次操作] - 执行[\${userEnvrionment}]环境的[\${userAction}]操作.\"");
        $this->append("echo \"[关于项目] - 部署[\${userServiceName}]项目到[\${userServiceIp}:\${userServicePort}].\"");
        $this->append("echo \"[服务中心] - 注册Consul服务到[\${userConsulIp}:\${userConsulPort}].\"");
        return $this;
    }

    private function appendDoStart()
    {
        $this->appendSeparator()->append($this->renderDoStart());
        return $this;
    }

    private function appendDoStop()
    {
        $this->appendSeparator()->append($this->renderDoStop());
        return $this;
    }

    private function appendDoRegister()
    {
        $this->appendSeparator()->append($this->renderDoRegister());
        return $this;
    }

    private function appendDoDeregister()
    {
        $this->appendSeparator()->append($this->renderDoDeregister());
        return $this;
    }
}
