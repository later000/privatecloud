# PrivateCloud 私人网盘

一个轻量、私密的个人网盘系统。支持文件上传下载、一键分享（直链 / 引导页）、在线预览图片音视频，内置 SQL 注入与 CC 攻击防护，UI 采用毛玻璃圆润风格。

- **技术栈**：PHP 8.0 + MySQL 5.7 + Nginx（宝塔面板一键部署）
- **定位**：私人文件存储，不对外开放注册，仅管理员登录使用
- **零配置安装**：上传源码后访问首页自动跳转安装向导，页面填库即可，无需手动改 config

---

## 功能特性

- 登录鉴权，禁止注册，公开入口仅登录页与分享页
- 文件上传（多文件、断点友好），单文件最大 5GB
- 一键分享，两种模式可选：
  - **直链**：点击链接直接开始下载，不经过临时 token
  - **分享引导页**：打开引导页预览后下载，下载链接 10 分钟有效、单次使用
- 分享下载免登录、免费
- 文件在线预览：图片、音频、视频、PDF、文本（后台与分享页均可预览）
- 后台可配置：网站名称、头像、访问地址、分享页反馈文案、ICP 备案号、每页条数
- 文件/分享列表每页默认 10 条，可选 20 / 30 / 40 / 50，支持分页切换
- 安全防护：全站 prepared statements（防 SQL 注入）、CSRF 校验、按 IP 请求频率限制（防 CC）、登录失败锁定
- 毛玻璃 UI、圆润设计、登录后进入后台过渡动画

---

## 环境要求

| 组件 | 版本 |
|------|------|
| PHP | 8.0（需 pdo_mysql、mbstring、openssl 扩展） |
| MySQL | 5.7 |
| Web 服务器 | Nginx（推荐宝塔面板） |

> 可选：阿里云 ESA 加速（免费版），所有动态页面建议关闭缓存。

---

## 目录结构

```
privatecloud/
├── config/
│   └── config.php          # 数据库与站点配置（安装向导自动写入）
├── includes/               # 核心库（db / auth / functions）
├── install.php             # 一键安装脚本
├── index.php               # 入口（未安装跳安装页，未登录跳登录页）
├── login.php / logout.php  # 登录 / 退出
├── dashboard.php           # 文件管理后台
├── upload.php              # 上传接口
├── download.php            # 下载接口（登录下载 / 直链 / 临时链接）
├── delete.php              # 删除文件 / 取消分享
├── create_share.php        # 创建分享（直链 / 引导页）
├── share.php               # 分享引导页
├── share_link.php          # 生成 10 分钟临时下载链接
├── preview.php             # 文件预览接口
├── settings.php            # 系统设置
├── storage/                # 文件存储目录（已禁止 Web 直接访问，勿删）
└── assets/                 # 前端样式与脚本
```

---

## 安装教程（宝塔面板）

### 1. 创建站点

1. 宝塔面板 →「网站」→「添加站点」。
2. 域名填写你的域名（如 `pan.example.com`，先不带 https）。
3. 根目录使用默认 `/www/wwwroot/pan.example.com`。
4. PHP 版本选择 **PHP 8.0**。
5. 数据库选择 **MySQL 5.7**，自定义库名与密码（例如库名 `privatecloud`、用户 `cloud_user`），记下密码。

### 2. 上传源码

把本项目**所有文件**上传到站点根目录 `/www/wwwroot/pan.example.com/`。

推荐：本地压缩为 zip 上传，宝塔「文件」管理中解压；或使用 Git 克隆。

### 3. 设置目录权限

- `config/` 目录需可写（安装向导要写入数据库配置）
- `storage/` 目录需可写（存储上传文件），权限 755

### 4. 访问首页自动安装

浏览器访问 `http://pan.example.com/`，系统检测到未安装会自动跳转到安装向导 `install.php`，按提示填写：

1. **环境检测**：确认 PHP 版本、扩展、目录可写均正常
2. **数据库**：填写数据库地址（一般 `127.0.0.1`）、端口（`3306`）、库名、用户名、密码，可先点「测试连接」验证
3. **站点设置**：网站名称、头像链接（可选）、访问地址、管理员用户名与密码

点击「开始安装」后，系统会自动：

- 将数据库信息写入 `config/config.php`
- 自动创建数据表
- 创建管理员账号
- 生成 `config/.installed.lock` 锁定文件

安装完成后点击「前往登录」即可使用。

> 重新安装：删除 `config/.installed.lock` 后重新访问首页。

---

## Nginx 安全配置（重要）

进入 站点 →「配置文件」，在 `location ~ \.php` 区块之前加入：

```nginx
# 禁止 Web 直接访问 config / includes / storage 目录
location ^~ /config/   { deny all; return 403; }
location ^~ /includes/ { deny all; return 403; }
location ^~ /storage/  { deny all; return 403; }

# 禁止可疑脚本扩展名执行
location ~* \.(php5|php7|phar|phtml|pht)$ {
    deny all;
    return 403;
}
```

---

## 调整上传大小限制（5GB）

1. **Nginx 层**（站点配置 `server {}` 内）：
   ```nginx
   client_max_body_size 5120m;
   ```

2. **PHP 层**（宝塔 → 软件商店 → PHP 8.0 → 配置修改）：
   ```
   upload_max_filesize = 5120M
   post_max_size = 5120M
   max_execution_time = 0
   max_input_time = -1
   memory_limit = 512M
   ```
   保存后重启 PHP 服务。

3. **代码层**：`config/config.php` 中 `MAX_UPLOAD_SIZE` 默认 5GB。

> 注意：ESA 免费版对上传请求体大小有限制（约 100MB），上传大文件建议使用直连服务器的域名或临时关闭 ESA。

---

## CC 攻击防护

系统内置应用层防护（`includes/functions.php`）：

- 全站请求频率限制：按 IP 每分钟 GET 300 次、POST 60 次，超出返回 429
- 登录失败锁定：同一 IP 连续 5 次失败锁定 15 分钟
- 阈值可在 `config/config.php` 中调整（`RATE_LIMIT_GET` / `RATE_LIMIT_POST` / `LOGIN_FAIL_MAX` / `LOGIN_LOCK_MINUTES`）

可再叠加 Nginx 限速（`zone` 定义放 http 级，限制放 `server {}` 内）：

```nginx
# http 级（文件顶部）
limit_req_zone $binary_remote_addr zone=cc:10m rate=10r/s;
limit_conn_zone $binary_remote_addr zone=conn:10m;

# server 级
limit_req zone=cc burst=20 nodelay;
limit_conn conn 20;
```

---

## 使用方法

| 功能 | 操作 |
|------|------|
| 登录 | 访问首页 → 输入管理员账号密码 |
| 上传 | 后台点「选择文件」，支持多选自动上传 |
| 下载 | 文件列表点「下载」（需登录） |
| 分享 | 文件列表点「分享」→ 选择「直链」或「分享页面」 |
| 接收方下载 | 直链：点开即下载；引导页：打开后点「下载文件」生成 10 分钟有效链接 |
| 取消分享 | 后台「我的分享」→「取消」 |
| 预览 | 图片 / 音视频 / PDF / 文本，后台与分享页均可预览 |

---

## 安全说明

- 无注册、无找回密码功能，公开入口仅登录页与分享页
- 上传文件改名为随机无扩展名存储，且 `storage/` 目录被 Nginx 禁止访问
- 数据库操作全部使用 prepared statements，防 SQL 注入
- 所有写操作带 CSRF 校验
- 临时下载链接 10 分钟有效、单次使用

---

## 常见问题

| 现象 | 排查 |
|------|------|
| 访问首页提示"数据库连接失败" | 检查 `config/config.php` 数据库配置是否正确 |
| 上传失败 / 提示超过大小 | 确认 Nginx `client_max_body_size` 与 PHP 上传限制已调大并重启 |
| 上传保存失败 | 检查 `storage/` 目录是否有写权限 |
| 分享链接已失效 | 分享被取消，或分享码复制不完整（10 位） |
| 登录后白屏 | 确认 PHP 版本为 8.0，查看 PHP 错误日志 |

---

## License

MIT
