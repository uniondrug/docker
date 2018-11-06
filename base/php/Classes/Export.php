<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-11-05
 */

/**
 * Class ExportInstall
 */
class Export extends Console
{
    protected $base;
    protected $config;

    public function __construct(Base $base, Config $config)
    {
        $this->base = $base;
        $this->config = $config;
    }

    /**
     * Shell头信息
     * @return mixed
     */
    protected function renderShellHeader()
    {
        $tpl = <<<'TMP'
#!/bin/sh

# 本文件由脚本生成, 请不要修改
# 时间: {{TIME}}
TMP;
        return preg_replace([
            "/\{\{TIME\}\}/"
        ], [
            date('Y-m-d H:i O')
        ], $tpl);
    }

    /**
     * Shell入参解析
     * @return string
     */
    protected function renderShellArguments()
    {
        $tpl = <<<'TMP'
# 第1优先: 命令行参数解析
#         --consul-ip
#         --consul-port
#         --service-ip
#         --service-port
#         --env
baseArguments="$@"
baseAction='$e = "/^([a-z][\w]*)/i"; $s = "'$@'"; echo preg_match($e, $s, $m) > 0 ? $m[1] : "";'
baseConsulIp='$e = "/[\-]+(consul\-ip)[\s|=]+(\S+)/i"; $s = "'$@'"; echo preg_match($e, $s, $m) > 0 ? $m[2] : "";'
baseConsulPort='$e = "/[\-]+(consul\-port)[\s|=]+(\S+)/i"; $s = "'$@'"; echo preg_match($e, $s, $m) > 0 ? $m[2] : "";'
baseServiceIp='$e = "/[\-]+(service\-ip)[\s|=]+(\S+)/i"; $s = "'$@'"; echo preg_match($e, $s, $m) > 0 ? $m[2] : "";'
baseServicePort='$e = "/[\-]+(service\-port)[\s|=]+(\S+)/i"; $s = "'$@'"; echo preg_match($e, $s, $m) > 0 ? $m[2] : "";'
bashEnvrionment='$e = "/[\-]+(e|env)[\s|=]+(\S+)/i"; $s = "'$@'"; echo preg_match($e, $s, $m) > 0 ? $m[2] : "";'
userAction=$(php -r "${baseAction}")
userConsulIp=$(php -r "${baseConsulIp}")
userConsulPort=$(php -r "${baseConsulPort}")
userServiceIp=$(php -r "${baseServiceIp}")
userServicePort=$(php -r "${baseServicePort}")
userServiceName="{{SERVICE_NAME}}"
userServiceMode="{{SERVICE_MODE}}"
userEnvrionment=$(php -r "${bashEnvrionment}")
basePath="{{BASE_PATH}}"
scriptPath="{{SCRIPT_PATH}}"
TMP;
        return preg_replace([
            "/\{\{SERVICE_MODE\}\}/",
            "/\{\{SERVICE_NAME\}\}/",
            "/\{\{BASE_PATH\}\}/",
            "/\{\{SCRIPT_PATH\}\}/",
        ], [
            $this->config->getMode(),
            $this->config->getName(),
            $this->base->getBasePath(),
            $this->base->getTargetPath(),
        ], $tpl);
    }

    /**
     * 加入Consul数据中心配置
     */
    protected function renderConsulData()
    {
        $consulData = [];
        $consulFile = $this->base->getBasePath().'/consul.json';
        if (file_exists($consulFile)) {
            $consulJson = file_get_contents($consulFile);
            $consulTemp = json_decode($consulJson, JSON_UNESCAPED_UNICODE);
            $consulTemp['Name'] = $this->config->getName();
            $consulTemp['Address'] = isset($consulTemp['Address']) && $consulTemp['Address'] !== '' ? $consulTemp['Address'] : '{{CONSUL_ADDRESS}}';
            $consulTemp['Port'] = isset($consulTemp['Port']) && is_numeric($consulTemp['Port']) && $consulTemp['Port'] > 0 ? (int) $consulTemp['Port'] : 0;
            $consulData = $consulTemp;
        }
        $tpl = <<<'TMP'
# Consul配置
userConsulData="{{CONSUL_DATA}}"
userConsulData=${userConsulData/\"Port\":0/\"Port\":${userServicePort}}
userConsulData=${userConsulData/\{\{CONSUL_ADDRESS\}\}/${userConsulIp}}
TMP;
        return preg_replace([
            "/\{\{CONSUL_DATA\}\}/"
        ], [
            addslashes(json_encode($consulData, true))
        ], $tpl);
    }

    /**
     * 安装时终级环境变量
     * @return string
     */
    protected function renderInstallVariables()
    {
        $tpl = <<<'TMP'
# environment
if [ -z "${userEnvrionment}" ] ; then
    userEnvrionment="${DOCKER_ENVIRONMENT}"
    if [ -z "${userEnvrionment}" ] ; then
        userEnvrionment="development"
    fi
fi
# consul:IP
if [ -z "${userConsulIp}" ] ; then
    userConsulIp="${CONSUL_IP}"
fi
# consul:Port
if [ -z "${userConsulPort}" ] ; then
    userConsulPort="${CONSUL_PORT}"
fi
# service:IP
if [ -z "${userServiceIp}" ] ; then
    userServiceIp=$(ip -o -4 addr list eth0 | head -n1 | awk '{print $4}' | cut -d/ -f1)
    if [ -z "${userServiceIp}" ] ; then
        userServiceIp="127.0.0.1"
    fi
fi
# service:Port
if [ -z "${userServicePort}" ] ; then
    userServicePort="{{SERVICE_PORT}}"
    if [ -z "${userServicePort}" ] ; then
        userServicePort="80"
    fi
fi
TMP;
        return preg_replace([
            "/\{\{SERVICE_PORT\}\}/",
        ], [
            $this->config->getPort(),
        ], $tpl);
    }

    /**
     * 动作开关
     * @return string
     */
    protected function renderInstallSwitch()
    {
        $tpl = <<<'TMP'
# 按动作名称选择操作方式
if [ "start" = "${userAction}" ] ; then
    doStart
    _s=$?
    echo "[启动应用] - 返回[$_s]号状态码"
    if [ 0 -eq ${_s} ] ; then
        doRegister
        _r=$?
        echo "[注册服务] - 返回[$_r]号状态码"
    fi
elif [ "stop" = "${userAction}" ] ; then
    doStop
    _s=$?
    echo "[停止应用] - 返回[$_s]号状态码"
    if [ 0 -eq ${_s} ] ; then
        doDeregister
        _r=$?
        echo "[取消服务] - 返回[$_r]号状态码"
    fi
else 
    echo "[出现错误] - 未知的操作类型"
    exit 1
fi
TMP;
        return $tpl;
    }

    /**
     * 启动服务
     * @return string
     */
    protected function renderDoStart()
    {
        return $this->renderDoStartSwoole();
    }

    /**
     * 启动Swoole服务
     * @return string
     */
    protected function renderDoStartSwoole()
    {
        $tpl = <<<'TMP'
# 启动应用服务
doStart(){
    # 1. started or not
    pids=$(ps aux | grep ${userServiceName} | grep -v grep | awk '{print $1}')
    if [ -n "${pids}" ]; then
        echo "[启动错误] - 服务已启动, 请先执行stop停止服务."
        return 1
    fi
    # 2. start item
    startCommand="${basePath}/vendor/uniondrug/server/server"
    cd ${basePath}
    if [ ! -e "${startCommand}" ]; then
        echo "[启动错误] - Composer未导入依赖uniondrug/server."
        return 2
    fi
    # 3. 启动服务
    su-exec {{OWNER}} php server start -e ${userEnvrionment} -d > /dev/null
    # 4. 读取进程
    num=$(ps aux | grep ${userServiceName} | grep -v grep | awk '{print $1}' | wc -l)
    if [ ${num} -lt 1 ]; then
        echo "[启动错误] - 未找到已启动的守护进程."
        return 3
    fi
    # 5. 完成启动
    echo "[启动成功] - 成功启动[${userServiceName}]项目."
    return 0
}
TMP;
        return preg_replace([
            "/\{\{OWNER\}\}/"
        ], [
            $this->base->getOwner()
        ], $tpl);
    }

    /**
     * 停止服务
     * @return string
     */
    protected function renderDoStop()
    {
        return $this->renderDoStopSwoole();
    }

    /**
     * 停止Swoole服务
     * @return string
     */
    protected function renderDoStopSwoole()
    {
        $tpl = <<<'TMP'
# 停止应用服务
doStop(){
    # 1. has process or not
    has=$(ps aux | grep ${userServiceName} | grep -v grep | wc -l)
    if [ ${has} -lt 1 ]; then
        echo "[已经停止] - 未找到服务进程或服务进程已经停止."
        return 0
    fi
    # 2. stop
    echo "[停止进程] - 强制退出[${has}]个进程."
    kill -9 $(ps aux | grep ${userServiceName} | grep -v grep | awk '{print $1}') > /dev/null
    # 3. failure or not
    has=$(ps aux | grep ${userServiceName} | grep -v grep | wc -l)
    if [ ${has} -lt 0 ]; then
         echo "[停止失败] - 仍有未停止的进程."
        return 1
    else
        return 0
    fi
}
TMP;
        return $tpl;
    }

    /**
     * 注册Consul服务
     * @return string
     */
    protected function renderDoRegister()
    {
        $tpl = <<<'TMP'
# 添加注册服务
doRegister(){
    # 1. required consul variables
    if [ -z "${userConsulIp}" -o -z "${userConsulPort}" ] ; then
        echo "[忽略服务] - Consul服务中心的IP或端口未定义"
        return 1
    fi
    # 2. consul data
    echo "[发起注册] - 请求[http://${userConsulIp}:${userConsulPort}/v1/agent/service/register]发起注册"
    echo "[服务参数] - ${userConsulData}"
    curl --request PUT --data ${userConsulData} --url "http://${userConsulIp}:${userConsulPort}/v1/agent/service/register" > /dev/null
    echo "[完成注册]"
    return 0
}
TMP;
        return $tpl;
    }

    /**
     * 取消Consul服务
     * @return string
     */
    protected function renderDoDeregister()
    {
        $tpl = <<<'TMP'
# 取消服务注册
doDeregister(){
    # 2. consul data
    echo "[取消注册] - 请求[http://${userConsulIp}:${userConsulPort}/v1/agent/service/deregister]取消注册"
    curlResponse=$(curl -IsS --request PUT --url "http://${userConsulIp}:${userConsulPort}/v1/agent/service/deregister/${userServiceName}")
    # 2.1 http code
    bashHttpCode='$e = "/HTTP\/\d+\.\d+\s+(\d+)/i"; $s = "'$curlResponse'"; echo preg_match($e, $s, $m) > 0 ? $m[1] : "";'
    userHttpCode=$(php -r "${bashHttpCode}")
    # 2.2 http text
    bashHttpText='$e = "/(HTTP\/\d+\.\d+\s+\d+[^\n]+)/i"; $s = "'$curlResponse'"; echo preg_match($e, $s, $m) > 0 ? $m[1] : "";'
    userHttpText=$(php -r "${bashHttpText}")
    # 3. 取消失败
    if [ -z "${userHttpCode}" ]; then
        echo "[取消失败] - ${curlResponse}"
        return 2
    fi
    # 4. 取消成功
    if [ "200" = "${userHttpCode}" ] ; then
        echo "[取消成功] - ${userHttpText}"
        return 0
    fi
    # 5. 取消失败
    echo "[取消失败] - ${userHttpText}"
    return 3
}
TMP;
        return $tpl;
    }
}
