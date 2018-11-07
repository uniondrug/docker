# docker

> 本项目用于在构建镜像时, 安装ENTRYPOINT启动脚本。

1. 脚本 - `ENTRYPOINT ["/usr/local/bin/entrypoint"]`
1. 参数 - `CMD ["start", "-p", "production"]`


### 环境变量

1. `DOCKER_IP` - 宿主机IP
1. `DOCKER_ENVIRONMENT` - 启动环境

1. `CONSUL_IP`  - Consul IP
1. `CONSUL_PORT` - Consul Port

SERVICE_PORT=18101
SERVICE_VERSION=2.0.0
SERVICE_NAME=mbs2.module
SERVICE_IP=


1. `SERVICE_MODE` - 启动模式
1. `SERVICE_VERSION` - 服务版本
1. `SERVICE_NAME` - 服务名称
1. `SERVICE_ADDRESS` - 服务地址
1. `SERVICE_PORT` - 服务端口


### branch

1. `swoole` - 以Swoole启动的项目
1. `fpm` - 以Nginx+FPM启动的项目
1. `nginx` - 以Nginx启动的项目




