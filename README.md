# PrivateCloud 私人网盘

轻量、私密的个人网盘系统，支持上传下载、一键分享、在线预览。  
采用 PHP 8.0 + MySQL 5.7，毛玻璃圆润 UI，内置 SQL 注入与 CC 攻击防护。  

> 由 DeepSeek V4 Pro 生成辅助

---

## 主要功能

- 仅管理员登录，不开放注册
- 多文件上传，单文件最大 5GB
- 分享支持直链或引导页（临时链接 10 分钟有效）
- 在线预览图片、音视频、PDF、文本
- 后台自定义网站名称、头像、ICP 备案等
- 安全防护：预处理防注入、CSRF 校验、频率限制、登录失败锁定

---

## 环境要求

- PHP 8.0（需 pdo_mysql、mbstring、openssl）
- MySQL 5.7
- Nginx（推荐宝塔面板）

---

## 安装步骤（宝塔面板）

1. **创建站点**  
   域名绑定，PHP 选择 8.0，MySQL 选择 5.7，记下数据库信息。

2. **上传源码**  
   将本项目文件上传至站点根目录，解压。

3. **设置权限**  
   `config/` 和 `storage/` 目录设为可写（755）。

4. **自动安装**  
   访问 `http://你的域名/`，按向导填写数据库与管理员信息，即可完成安装。

> 重新安装：删除 `config/.installed.lock` 后重访首页。

---

## Nginx 安全配置

在站点配置文件中加入：

```nginx
location ^~ /config/   { deny all; return 403; }
location ^~ /includes/ { deny all; return 403; }
location ^~ /storage/  { deny all; return 403; }
location ~* \.(php5|php7|phar|phtml|pht)$ { deny all; return 403; }
```

---

## 上传大小调整（5GB）

- **Nginx**：`client_max_body_size 5120m;`
- **PHP**：修改 `upload_max_filesize`、`post_max_size` 为 `5120M`，重启 PHP
- 代码层默认已支持 5GB

---

## 使用说明

| 操作 | 方式 |
|------|------|
| 登录 | 首页输入管理员账号 |
| 上传/下载 | 后台操作 |
| 分享 | 文件列表 → 分享 → 选直链或引导页 |
| 取消分享 | 我的分享 → 取消 |
| 预览 | 点击文件即可预览 |

---

## 安全说明

- 无注册与找回密码，公开入口仅登录页和分享页
- 文件改名存储，禁止 Web 直接访问 `storage/`
- 使用预处理语句、CSRF 校验、临时链接单次有效

---

## 常见问题

| 问题 | 解决 |
|------|------|
| 数据库连接失败 | 检查 `config/config.php` 配置 |
| 上传失败 | 检查 Nginx 和 PHP 上传限制，以及 `storage/` 权限 |
| 分享链接失效 | 确认分享未取消，链接完整 |
| 登录后白屏 | 检查 PHP 版本是否为 8.0，查看错误日志 |

---

## License

MIT
