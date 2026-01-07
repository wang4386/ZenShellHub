# 使用官方 PHP 8.0 Apache 镜像
FROM php:8.0-apache

# 设置工作目录
WORKDIR /var/www/html

# 启用 Apache Rewrite 模块
RUN a2enmod rewrite

# 复制项目文件
COPY index.php .

# 1. 创建 /data 目录（确保它存在）
# 2. 修改权限：确保 www-data 用户可以写入
# 注意：这一步仅对构建时有效，如果是挂载卷，还需要在运行时再次修正
RUN mkdir -p /data && chown -R www-data:www-data /data && chmod 777 /data

# 修改 CMD 启动命令
# 核心修复点：在启动 Apache 之前，强制通过 chown 修正 /data 目录的权限
# 这样即使挂载了宿主机的目录，容器启动时也会尝试将其所有权改为 www-data
CMD ["sh", "-c", "mkdir -p /data && chown -R www-data:www-data /data && apache2-foreground"]

# 暴露端口
EXPOSE 80