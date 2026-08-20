# 后来的网盘 部署文档

私人 PHP 网盘系统，支持登录管理、文件上传下载、一键分享（引导页 + 10 分钟临时下载链接）。
环境要求：PHP 8.0 + MySQL 5.7 + Nginx（宝塔面板 v12.0.0 环境已验证思路）。

---

## 一、目录结构

```
pan/
├── config/config.php      # 站点与数据库配置（部署时必须修改）
├── includes/              # 核心库（db / auth / functions）
├── install.php            # 一键安装脚本（建表 + 创建管理员）
├── index.php              # 入口（未登录跳登录页）
├── login.php              # 登录页
├── logout.php             # 退出登录
├── dashboard.php          # 文件管理主界面
├── upload.php             # 上传接口
├── download.php           # 下载接口（登录下载 / 分享临时链接下载）
├── delete.php             # 删除文件 / 取消分享
├── share.php              # 分享引导页（含创建分享）
├── share_link.php         # 生成 10 分钟有效下载链接并跳转
├── storage/               # 文件存储目录（已禁止 Web 直接访问，勿删）
├── assets/                # 前端样式与脚本
└── docs/DEPLOY.md         # 本部署文档
```

---

## 二、宝塔面板部署步骤

### 1. 创建站点

1. 打开宝塔面板 →「网站」→「添加站点」。
2. 域名填写：`pan.vxva.cn`（不要带 https，后面单独配 SSL）。
3. 根目录使用默认 `/www/wwwroot/pan.vxva.cn`。
4. PHP 版本选择 **PHP 8.0**（宝塔的 PHP-8.0）。
5. 数据库选择 **MySQL 5.7**，数据库名与密码**自定义**（例如库名 `pan_vxva`、用户 `pan_user`），创建后记下密码。
6. 提交后，宝塔会在 `/www/wwwroot/pan.vxva.cn` 下生成站点目录。

### 2. 上传代码

把本项目的 `pan/` 目录下**所有文件**（config、includes、install.php、dashboard.php 等）上传到 `/www/wwwroot/pan.vxva.cn/`。

推荐方式：在宝塔「文件」管理里操作，或者本地压缩成 zip 后上传解压。

### 3. 修改数据库配置

编辑 `/www/wwwroot/pan.vxva.cn/config/config.php`，修改三处：

```php
define('DB_NAME', 'pan_vxva');      // 改成你建站时填的数据库名
define('DB_USER', 'pan_user');      // 改成你建站时填的数据库用户
define('DB_PASS', '你的数据库密码'); // 改成数据库密码
```

### 4. 设置目录权限

在宝塔「文件」里右键 `/www/wwwroot/pan.vxva.cn/storage` →「权限」，设为 **755**。
（首次安装时若 storage 不存在，安装脚本会自动创建，保证 www 用户可写即可。）

### 5. 执行安装

浏览器访问：`http://pan.vxva.cn/install.php`

1. 站点地址填 `https://pan.vxva.cn`。
2. 管理员用户名默认 `admin`，密码默认 `wjb333wjb`，**建议安装时直接改成更强的密码**。
3. 点击「开始安装」，看到"安装完成"即可。
4. 安装完成后，`config/.installed.lock` 会生成，重复访问 install.php 会被拒绝。若想重装，删除该文件后重新访问。

> 安装后建议手动删除服务器上的 `install.php`，或至少确认 `.installed.lock` 已生成。

### 6. 配置 HTTPS（必做）

1. 宝塔 →「网站」→ 对应站点 →「SSL」→ 申请 Let's Encrypt 免费证书（需域名已解析到本服务器）。
2. 开启「强制 HTTPS」。

### 7. Nginx 安全配置（重要）

进入 站点 →「配置文件」，在 `location ~ \.php` 区块**之前**加入以下内容，然后保存重载 Nginx：

```nginx
# 禁止 Web 直接访问 config / includes / storage 目录
location ^~ /config/  { deny all; return 403; }
location ^~ /includes/ { deny all; return 403; }
location ^~ /storage/ { deny all; return 403; }

# 禁止任何以 .php 结尾文件之外的可疑执行
location ~* \.(php5|php7|phar|phtml|pht)$ {
    deny all;
    return 403;
}
```

这样即使将来有人上传了 `.php` 文件，也会被 Nginx 直接拒绝执行（上传的文件还会被改名为无扩展名的随机文件名，双保险）。

### 8. CC 攻击防护

系统已内置应用层防护（`includes/functions.php`）：
- **全站请求频率限制**：按 IP 每分钟限制 GET 300 次、POST 60 次（`config/config.php` 中 `RATE_LIMIT_GET` / `RATE_LIMIT_POST` 可调），超出返回 429。
- **登录失败锁定**：同一 IP 连续 5 次登录失败锁定 15 分钟（`LOGIN_FAIL_MAX` / `LOGIN_LOCK_MINUTES`）。
- 限流记录存放在 `storage/rate/` 目录，自动过期清理，无数据库压力。

再叠加一层 Nginx 限制（站点 → 配置文件，`zone` 定义必须放在 `server {}` 之外的 http 级，即文件顶部）：

```nginx
# 文件顶部（http 级）：定义限速与连接数 zone
limit_req_zone $binary_remote_addr zone=cc:10m rate=10r/s;
limit_conn_zone $binary_remote_addr zone=conn:10m;
```

在 `server {}` 内加入：

```nginx
# 全局限速：超过 10 请求/秒 的部分直接 429
limit_req zone=cc burst=20 nodelay;

# 单个 IP 并发连接数限制
limit_conn conn 20;
```

> 说明：Nginx 限速 10r/s + burst 20，正常使用完全够用；CC 攻击会被 Nginx 直接拦截，PHP 不参与，几乎不消耗应用资源。若被攻击更猛烈，可在 ESA 或阿里云 Web 防火墙（WAF）里配置 IP 黑名单、地区封禁和 CC 防护策略（WAF 拦截更专业，推荐在控制台开通免费试用版）。

### 9. 调整上传大小限制

网盘支持大文件上传（单文件最大 5GB），需要改三处：

1. **Nginx 层**（站点配置文件的 `server {}` 里）：
   ```nginx
   client_max_body_size 5120m;
   ```

2. **PHP 层**（宝塔 → 软件商店 → PHP-8.0 → 配置修改）：
   ```
   upload_max_filesize = 5120M
   post_max_size = 5120M
   max_execution_time = 0
   max_input_time = -1
   memory_limit = 512M
   ```
   改完在 PHP 配置页点「保存」并重启 PHP 服务。

3. **代码层**：`config/config.php` 里的 `MAX_UPLOAD_SIZE` 默认 5GB，如需更大可自行调高。

> 大文件上传注意：服务器需保证至少 5GB 以上的磁盘剩余空间（临时目录 `/tmp` 和 `storage/` 存储目录）；ESA 免费版对上传请求体大小有限制（通常 100MB 左右），上传大文件建议用直连服务器的域名，或临时关闭 ESA 后上传。

---

## 三、阿里云 ESA 加速配置

> ESA（边缘安全加速）免费版可以给下载文件加速。网盘是动态 + 私密应用，**核心原则：一切动态页面和下载请求都不要缓存**，只让 ESA 起到回源加速（回源链路优化 + 边缘节点就近分发）的作用。

### 1. 添加 ESA 站点

1. 阿里云控制台 →「ESA」→ 添加站点，输入 `pan.vxva.cn`，套餐选免费版。
2. 按提示把域名的 DNS 解析改成 ESA 给的 CNAME（在域名 DNS 服务商处操作）。
3. 等待 ESA 站点状态变为「已生效」。

### 2. 配置回源

1. 进入该站点的「DNS/回源」设置。
2. 回源地址填**服务器公网 IP**，回源 HOST 填 `pan.vxva.cn`。
3. 端口填 `443`（HTTPS 回源），并开启「跟随客户端协议」或手动指定。

### 3. 缓存规则（关键，防止缓存泄露私人内容）

本系统所有 PHP 页面都自动返回 `Cache-Control: no-store`，ESA 默认对这类响应不缓存。但为了保险，请在 ESA 的「缓存」→「缓存规则」里新增：

- 规则名：`网盘全部不缓存`
- 匹配：所有路径 `/`、所有文件类型、所有请求
- 操作：**缓存模式 = 不缓存**

> 解释：登录页、文件列表、下载链接都是私密的，任何缓存都可能导致私人文件或临时链接被误缓存给他人。关闭缓存后，ESA 仍会做回源链路加速（国内访问更快），只是不缓存内容。

### 4. 上传大小注意

ESA 免费版对请求体大小有限制（通常 100MB 左右）。如果你上传大文件失败：

- 方案 A：临时暂停/绕过 ESA 上传（例如用解析直接指向服务器的备用域名上传）。
- 方案 B：将大文件通过服务器直连域名上传后再正常使用。

> 下载方向一般无此限制（下载是回源响应）。

### 5. HTTPS 证书

ESA 站点也需要证书。若 ESA 站点开启了 HTTPS，SSL 证书可以在 ESA 控制台申请（免费），也可以让 ESA 自动回源到宝塔上的 HTTPS 证书。

---

## 四、使用方法

| 功能 | 操作 |
|------|------|
| 登录 | 访问 `https://pan.vxva.cn` → 输入 admin 账号密码 |
| 上传 | 管理页点「选择文件」，支持多选，选完自动上传 |
| 下载 | 文件列表点「下载」（需登录） |
| 分享 | 文件列表点「分享」→ 跳转引导页，复制地址栏链接发给别人 |
| 接收方下载 | 打开分享链接 → 点「下载文件」→ 生成 10 分钟有效链接并直接开始下载，全程免登录 |
| 取消分享 | 管理页「我的分享」→「取消分享」，之后原链接立即失效 |

---

## 五、安全注意事项

1. **改密码**：登录后目前没有改密界面，改密码方式：宝塔 → 软件商店 → 打开 phpMyAdmin（或网站 → 数据库 → 管理），执行：
   ```sql
   UPDATE users SET password_hash = '$2y$10$...' WHERE username='admin';
   ```
   `$2y$10$...` 是 `password_hash()` 生成的密文，可用任何 PHP 环境 `echo password_hash('新密码', PASSWORD_DEFAULT);` 生成。
2. **数据库密码**：使用强密码，不要用简单密码。
3. **config/config.php**：包含数据库密码，宝塔中权限保持 644 即可（Nginx 已拒绝 web 访问该目录）。
4. **备份**：定期备份数据库（宝塔「计划任务」可自动备份）和 `storage/` 目录。
5. **storage 目录**：绝不能放到 Web 可访问的位置，也不要改掉无扩展名存储的逻辑。
6. **临时下载链接**：10 分钟有效、单次使用（下载完成后即失效）。若被泄露影响很小，因为 10 分钟很短。
7. 本系统无注册功能、无找回密码功能，公开入口只有登录页和分享页，符合"私人网盘、不对外开放"要求。

---

## 六、常见问题排查

| 现象 | 排查步骤 |
|------|---------|
| 打开 install.php 提示"未填写数据库配置" | 检查 config/config.php 的 DB_PASS 是否还是占位文字 |
| 上传失败 / 提示超过大小 | 确认 Nginx `client_max_body_size` 和 PHP `upload_max_filesize` 都已调大并重启 |
| 上传保存失败 | 检查 storage 目录是否有写权限（www 用户） |
| 下载很慢 | 检查 ESA 是否生效、回源是否正常；确认下载没有走代理 |
| 打开分享提示已失效 | 分享被取消，或地址码复制不完整（分享码共 10 位） |
| 登录后白屏 | 确认 PHP 版本是 8.0，打开 PHP 错误日志（宝塔 PHP 设置 → 日志）查看 |
| 误删锁文件后重装 | 删除 config/.installed.lock 后可重新访问 install.php（会清空重建表） |

---

## 七、问题反馈

无法下载或遇到 bug，请联系作者：
- QQ：3785320778
- 邮箱：later0@88.com / later0@111.com
