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
        // 1. 读Consul配置
        $consulData = [];
        $consulFile = $this->base->getBasePath().'/consul.json';
        if (file_exists($consulFile)) {
            $consulJson = file_get_contents($consulFile);
            $consulTemp = json_decode($consulJson, JSON_UNESCAPED_UNICODE);
            if (is_array($consulTemp)) {
                $consulData = $consulTemp;
            }
        }
        // 2. 重Consul参数
        $consulData['Name'] = $this->config->getName();
        $consulData['Address'] = isset($consulData['Address']) && $consulData['Address'] !== '' ? $consulData['Address'] : '{{CONSUL_ADDRESS}}';
        $consulData['Port'] = isset($consulData['Port']) && is_numeric($consulData['Port']) && $consulData['Port'] > 0 ? (int) $consulData['Port'] : 0;
        // 3. TAGS数据
        $consulData['Tags'] = isset($consulData['Tags']) && is_array($consulData['Tags']) ? $consulData['Tags'] : [];
        // 3.1 镜像名
        $image = '{{ENVIRONMENT}}:'.$this->config->getImage();
        in_array($image, $consulData['Tags']) || $consulData['Tags'][] = $image;
        // 3.2 模式
        $mode = $this->config->getMode();
        in_array($mode, $consulData['Tags']) || $consulData['Tags'][] = $mode;
        // 4. 导出模板
        $tpl = <<<'TMP'
# Consul配置
userConsulData="{{CONSUL_DATA}}"
userConsulData=${userConsulData/\"Port\":0/\"Port\":${userServicePort}}
userConsulData=${userConsulData/\{\{CONSUL_ADDRESS\}\}/${userConsulIp}}
userConsulData=${userConsulData/\{\{ENVIRONMENT\}\}/${userEnvrionment}}
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
    protected function renderDefaultArguments()
    {
        $tpl = <<<'TMP'
# 默认操作类型/优先级
# 1. SHELL入参
# 2. start
if [ -z "${userAction}" ] ; then
    userAction="start"
fi
# 默认环境变量/优先级
# 1. SHELL入参
# 2. DOCKER_ENVIRONMENT环境变量值
# 3. testing
if [ -z "${userEnvrionment}" ] ; then
    userEnvrionment="${DOCKER_ENVIRONMENT}"
    if [ -z "${userEnvrionment}" ] ; then
        userEnvrionment="testing"
    fi
fi
# Consul地址/优先级
# 1. SHELL入参
# 2. CONSUL_IP环境变量值
if [ -z "${userConsulIp}" ] ; then
    userConsulIp="${CONSUL_IP}"
fi
# Consul端口/优先级
# 1. SHELL入参
# 2. CONSUL_PORT环境变量值
# 3. 8500
if [ -z "${userConsulPort}" ] ; then
    userConsulPort="${CONSUL_PORT}"
    if [ -z "${userConsulPort}" ] ; then
        userConsulPort="8500"
    fi
fi
# 服务地址/优先级
# 1. SHELL入参
# 2. SERVICE_IP环境变量值
# 3. 网卡IP
# 4. 127.0.0.1
if [ -z "${userServiceIp}" ] ; then
    userServiceIp="${SERVICE_IP}"
    if [ -z "${userServiceIp}" ] ; then
        userServiceIp=$(ip -o -4 addr list eth0 | head -n1 | awk '{print $4}' | cut -d/ -f1)
        if [ -z "${userServiceIp}" ] ; then
            userServiceIp="127.0.0.1"
        fi
    fi
fi
# 服务端口/优先级
# 1. SHELL入参
# 2. SERVICE_PORT环境变量值
# 3. 项目配置/config.server.host
# 4. 8080
if [ -z "${userServicePort}" ] ; then
    userServicePort="${SERVICE_PORT}"
    if [ -z "${userServicePort}" ] ; then
        userServicePort="{{SERVICE_PORT}}"
        if [ -z "${userServicePort}" -o "0" = "${userServicePort}" ] ; then
            userServicePort="8080"
        fi
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
    doRegister
    doStart
    doDeregister
elif [ "stop" = "${userAction}" ] ; then
    doStop
    doDeregister
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
    has=$(ps aux | grep ${userServiceName} | grep -v grep | wc -l)
    if [ "${has}" -lt 0 ]; then
        echo "[启动错误] - 服务已启动, 请先执行stop停止服务."
        return 1
    fi
    # 2. start item
    startCommand="${basePath}/vendor/uniondrug/server/server"
    cd ${basePath} && chown -R {{OWNER}} .
    if [ ! -e "${startCommand}" ]; then
        echo "[启动错误] - Composer未导入依赖uniondrug/server."
        return 2
    fi
    # 3. 启动服务
    su-exec {{OWNER}} php ${startCommand} start --port 8080 -e ${userEnvrionment}
    return 3
}
TMP;
        return preg_replace([
            "/\{\{OWNER\}\}/",
        ], [
            $this->base->getOwner(),
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
    # 2. 写入执行语句
    shellFile="{{BASE_PATH}}/consul.sh"
    echo "#!/bin/sh" > ${shellFile}
    echo "curl -isS --request PUT \\" >> ${shellFile}
    echo "     --data '${userConsulData}' \\" >> ${shellFile}
    echo "     http://${userConsulIp}:${userConsulPort}/v1/agent/service/register" >> ${shellFile}
    echo "[发起注册] - 请求[http://${userConsulIp}:${userConsulPort}/v1/agent/service/register]发起注册"
    echo "[服务参数] - ${userConsulData}"
    echo "[注册脚本] - ${shellFile}"
    curlResponse=$(bash ${shellFile})
    # 2.1 http code
    bashHttpCode='$e = "/HTTP\/\d+\.\d+\s+(\d+)/i"; $s = "'$curlResponse'"; echo preg_match($e, $s, $m) > 0 ? $m[1] : "";'
    userHttpCode=$(php -r "${bashHttpCode}")
    # 2.2 http text
    bashHttpText='$e = "/(HTTP\/\d+\.\d+\s+\d+[^\n]+)/i"; $s = "'$curlResponse'"; echo preg_match($e, $s, $m) > 0 ? $m[1] : "";'
    userHttpText=$(php -r "${bashHttpText}")
    # 3. 注册失败
    if [ -z "${userHttpCode}" ]; then
        echo "[注册失败] - ${curlResponse}"
        return 2
    fi
    # 4. 注册成功
    if [ "200" = "${userHttpCode}" ] ; then
        echo "[注册成功] - ${userHttpText}"
        return 0
    fi
    # 5. 注册失败
    echo "[注册失败] - ${userHttpText}"
    return 3
}
TMP;
        return preg_replace([
            "/\{\{OWNER\}\}/",
            "/\{\{BASE_PATH\}\}/"
        ], [
            $this->base->getOwner(),
            $this->base->getBasePath()
        ], $tpl);
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
    # 1. required consul variables
    if [ -z "${userConsulIp}" -o -z "${userConsulPort}" ] ; then
        echo "[忽略服务] - Consul服务中心的IP或端口未定义"
        return 0
    fi
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
        return 1
    fi
    # 4. 取消成功
    if [ "200" = "${userHttpCode}" ] ; then
        echo "[取消成功] - ${userHttpText}"
        return 0
    fi
    # 5. 取消失败
    echo "[取消失败] - ${userHttpText}"
    return 2
}
TMP;
        return $tpl;
    }
}
