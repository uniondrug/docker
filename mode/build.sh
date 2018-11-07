#!/bin/sh

# 构建Docker镜像

info arguments: $@

container=$(docker run --name builder -d uniondrug:base php)

echo "container = ${container}"


