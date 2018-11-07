# docker

> 本项目用于在构建镜像时, 安装ENTRYPOINT启动脚本。

1. 脚本 - `ENTRYPOINT ["/usr/local/bin/entrypoint"]`
1. 参数 - `CMD ["start"]`


### 环境变量

1. Docker定义
    1. `DOCKER_IP` - 宿主机IP
    1. `DOCKER_ENVIRONMENT` - 启动环境
1. Consul定义
    1. `CONSUL_IP`  - Consul IP
    1. `CONSUL_PORT` - Consul Port
1. Service定义
    1. `SERVICE_MODE` - 启动模式
    1. `SERVICE_NAME` - 服务名称
    1. `SERVICE_VERSION` - 服务版本
    1. `SERVICE_IP` - 服务地址
    1. `SERVICE_PORT` - 服务端口


### 启动镜像

```bash
docker run \
    --name sketch \
    -e DOCKER_ENVIRONMENT=development \
    -e CONSUL_IP=192.168.10.117 \
    -e SERVICE_IP=mbs2.module.test.dovecot.cn \
    -e SERVICE_PORT=8101 \
    -p 8101:8080 \
    -d mbs2.module:2.0.0
```
