# ZenShellHub 🐚

> 极简、高颜值的单文件脚本管理中心。

**ZenShellHub** 是一个单文件（Single-File）的 PHP 应用程序，旨在帮助运维人员、开发者以最优雅的方式管理和分享常用的 Shell 脚本命令。它不需要数据库，不需要复杂的安装过程，上传即用。

设计上采用了 **"Zen-iOS Hybrid"** 视觉语言，拥有极致的物理触感、光学模糊效果和流畅的交互动画。

## ✨ 核心特性

* 💎  **极致 UI 设计** ：全站采用 iOS 级毛玻璃（Backdrop Blur）效果，物理光学边框，深色模式适配。
* 🚀  **零依赖单文件** ：所有逻辑（前端 React + 后端 PHP）集成在唯一的 `index.php` 中。
* 💻  **终端客户端** ：提供原生 Shell 脚本 (`zenshell.sh`)，支持在终端直接预览并执行命令。
* 🔒  **安全隐私** ：
  * 首次访问强制初始化管理员密码。
  * 未登录状态下内容完全模糊遮挡，甚至不加载图片资源。
  * 自动生成 `.htaccess` 防止数据文件被外部下载。
* ☁️  **数据持久化** ：使用 JSON 文件存储，无需 MySQL/Redis，迁移只需复制文件。
* 🔗  **分享机制** ：支持生成特定脚本的专属分享链接，接收者无需登录即可查看指定内容。
* 📱  **完美响应式** ：PC 端与移动端（iOS/Android）完美适配，控件自适应居中。
* ✨  **智能交互** ：
  * 图片悬停全局浮出查看。
  * 代码块智能识别复制。
  * MacOS 风格终端展示。

## 🛠️ 快速部署

### 环境要求

* PHP 7.4 或更高版本
* Web 服务器 (Nginx / Apache / OpenLiteSpeed)

### 安装步骤

1. **下载** ：下载本仓库的 `index.php` 文件。
2. **上传** ：将文件上传至您的服务器网站根目录或任意子目录。
3. **权限** ：**（重要）** 确保该目录拥有写入权限（755 或 777），因为程序需要自动创建 `data.json` 和 `.htaccess` 文件。
   * *Linux/宝塔面板示例* ：`chown -R www:www /www/wwwroot/yoursite/`

### 开始使用

1. 在浏览器访问该页面。
2. 首次加载会弹出 **“系统初始化”** 窗口，请设置您的管理员密码。
3. 登录后即可开始添加、编辑和管理您的脚本卡片。

## 🐳 Docker 部署 (推荐)

如果您熟悉 Docker，这是最快捷的部署方式，无需配置 PHP 环境。

1. **获取文件** ：下载本仓库的 `docker-compose.yml`、`Dockerfile` 和 `index.php`。
2. **启动容器** ：
    在项目目录下运行：

```
docker-compose up -d
```

3. **访问** ：打开浏览器访问`http://localhost:8080`。

* 数据将自动持久化存储在当前目录的 `data/` 文件夹中。
* 默认端口为 `8080`，如需修改请编辑 `docker-compose.yml`。

## 💻 终端客户端 (CLI)

本项目附带一个纯 Shell 编写的终端客户端 `zenshell.sh`，让您无需离开终端即可连接您的 ZenShellHub 节点，快速管理和执行脚本。

### 安装与使用

1. **下载客户端** ：
   将 `zenshell.sh` 下载到您的本地机器（macOS/Linux）。

```
bash <(curl -sL https://qninq.cn/file/sh/zenshell.sh)
bash <(curl -sL https://raw.githubusercontent.com/wang4386/ZenShellHub/refs/heads/main/zenshell.sh)
```

1. **首次运行配置** ：
   
   ```
   ./zenshell.sh
   ```
   
   * 脚本会提示您输入  **对接端点** （即您部署的 ZenShellHub 网址，如 `http://mysite.com`）。
   * 输入您的 **管理员密码** 进行验证。
   * 验证成功后，脚本会自动检测您的 Shell 环境（zsh/bash）并添加快捷指令 `zensh`。
2. **快捷指令** ：
    重启终端或重载配置后，您只需输入 `zensh` 即可随时启动：

```
zensh
```

1. **卸载** ：
    在客户端主菜单中输入 `u` 即可卸载客户端并清理相关配置文件及别名。

## 📸 预览 (Screenshots)

![未登录](https://assets.qninq.cn/qning/WIg3sJqj.webp)
![管理](https://assets.qninq.cn/qning/uk4khhBg.webp)
![新建](https://assets.qninq.cn/qning/H7ldenPv.webp)

## ⚙️ 配置说明

所有数据默认存储在同级目录下的 `data.json` 文件中。

* **备份** ：只需下载 `index.php` 和 `data.json` 即可完成全站备份。
* **重置密码** ：如果忘记密码，请通过 FTP/SSH 删除 `data.json` 中的 `"password_hash"` 字段，或直接删除该文件（数据将丢失）以重新初始化。
* **伪静态规则**：Nginx应当添加如下规则来保护data.json文件。

```
location = /data.json {
    deny all;
    # 或者使用 return 404; 来伪装文件不存在
}
```

## 🤝 贡献 (Contributing)

虽然这是一个单文件项目，但欢迎提交 Issue 或 PR 进行优化。

## 📄 开源协议

https://github.com/wang4386/ZenShellHub

本项目采用 [MIT License](https://mit-license.org/) 开源。

Designed with ❤️ by [青柠·倾城于你](https://qninq.cn/)