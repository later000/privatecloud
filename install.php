<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

if (is_installed()) {
    exit('<div style="font-family:sans-serif;max-width:520px;margin:80px auto;padding:24px;border-radius:14px;background:rgba(255,255,255,.85);box-shadow:0 10px 30px rgba(0,0,0,.08)">系统已安装。如需重新安装，请删除 config/.installed.lock 文件后再次访问本页。</div>');
}

function test_db_connection(string $host, int $port, string $name, string $user, string $pass): string
{
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name),
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        $pdo->query('SELECT 1');
        return '';
    } catch (PDOException $e) {
        return '数据库连接失败：' . $e->getMessage();
    }
}

function write_db_config(string $host, int $port, string $name, string $user, string $pass): string
{
    $cfg = file_get_contents(__DIR__ . '/config/config.php');
    if ($cfg === false) {
        return '无法读取 config/config.php';
    }
    $replace = [
        "define('DB_HOST', '127.0.0.1');" => "define('DB_HOST', '" . addslashes($host) . "');",
        "define('DB_PORT', 3306);" => "define('DB_PORT', " . $port . ");",
        "define('DB_NAME', 'pan_vxva');" => "define('DB_NAME', '" . addslashes($name) . "');",
        "define('DB_USER', 'pan_user');" => "define('DB_USER', '" . addslashes($user) . "');",
        "define('DB_PASS', '此处填写数据库密码');" => "define('DB_PASS', '" . addslashes($pass) . "');",
    ];
    foreach ($replace as $from => $to) {
        $cfg = str_replace($from, $to, $cfg);
    }
    if (file_put_contents(__DIR__ . '/config/config.php', $cfg) === false) {
        return '无法写入 config/config.php，请检查文件权限（需可写）';
    }
    return '';
}

$errors = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'install';

    if ($action === 'test') {
        header('Content-Type: application/json; charset=utf-8');
        $host = trim($_POST['db_host'] ?? '127.0.0.1');
        $port = (int) ($_POST['db_port'] ?? 3306);
        $name = trim($_POST['db_name'] ?? '');
        $user = trim($_POST['db_user'] ?? '');
        $pass = (string) ($_POST['db_pass'] ?? '');
        $err = test_db_connection($host, $port, $name, $user, $pass);
        echo json_encode(['ok' => $err === '', 'message' => $err === '' ? '连接成功' : $err], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
    $dbPort = (int) ($_POST['db_port'] ?? 3306);
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = (string) ($_POST['db_pass'] ?? '');
    $siteName = trim($_POST['site_name'] ?? '');
    $siteAvatar = trim($_POST['site_avatar'] ?? '');
    $siteUrl = trim($_POST['site_url'] ?? '');
    $username = trim($_POST['admin_user'] ?? '');
    $password = (string) ($_POST['admin_pass'] ?? '');
    $password2 = (string) ($_POST['admin_pass2'] ?? '');

    if ($dbName === '' || $dbUser === '' || $dbPass === '') {
        $errors[] = '请填写数据库名、用户名和密码';
    }
    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $dbName)) {
        $errors[] = '数据库名包含非法字符';
    }
    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $dbUser)) {
        $errors[] = '数据库用户名包含非法字符';
    }
    if ($siteName === '') {
        $errors[] = '请填写网站名称';
    }
    if (!preg_match('#^https?://[A-Za-z0-9][A-Za-z0-9.\-:/]*$#', $siteUrl)) {
        $siteUrl = SITE_URL;
    }
    if ($siteAvatar !== '' && !preg_match('#^https?://#', $siteAvatar)) {
        $errors[] = '头像地址必须是 http(s) 链接';
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
        $errors[] = '管理员用户名需为 3-32 位字母、数字或下划线';
    }
    if (strlen($password) < 8) {
        $errors[] = '管理员密码至少 8 位';
    }
    if ($password !== $password2) {
        $errors[] = '两次输入的管理员密码不一致';
    }

    if (empty($errors)) {
        $err = test_db_connection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
        if ($err !== '') {
            $errors[] = $err;
        }
    }

    if (empty($errors)) {
        try {
            $cfgErr = write_db_config($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
            if ($cfgErr !== '') {
                throw new RuntimeException($cfgErr);
            }

            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName),
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
            );
            $pdo->exec("SET time_zone = '+08:00'");

            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS files (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(64) NOT NULL UNIQUE,
                size BIGINT UNSIGNED NOT NULL,
                mime VARCHAR(120) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS shares (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                file_id BIGINT UNSIGNED NOT NULL,
                share_code VARCHAR(16) NOT NULL UNIQUE,
                type VARCHAR(10) NOT NULL DEFAULT 'page',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_file (file_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS download_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                share_id BIGINT UNSIGNED NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                used TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_token (token),
                KEY idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
                skey VARCHAR(64) NOT NULL PRIMARY KEY,
                svalue TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $stmt = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
            $stmt->execute(['site_name', $siteName]);
            $stmt->execute(['site_url', $siteUrl]);
            $stmt->execute(['site_avatar', $siteAvatar]);
            $stmt->execute(['per_page_default', '10']);
            $stmt->execute(['share_feedback', SHARE_FEEDBACK_DEFAULT]);
            $stmt->execute(['site_icp', '']);

            $stmt = $pdo->prepare('INSERT IGNORE INTO users (username, password_hash) VALUES (?, ?)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE username = ?');
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $username]);

            if (!is_dir(DATA_DIR) && !mkdir(DATA_DIR, 0755, true)) {
                throw new RuntimeException('无法创建 storage 存储目录，请检查目录权限');
            }

            file_put_contents(LOCK_FILE, date('Y-m-d H:i:s'));
            $done = true;
        } catch (Throwable $e) {
            $errors[] = '安装失败：' . $e->getMessage();
        }
    }
}

$phpOk = PHP_VERSION_ID >= 70400;
$exts = ['pdo_mysql' => extension_loaded('pdo_mysql'), 'mbstring' => extension_loaded('mbstring'), 'openssl' => extension_loaded('openssl')];
$configWritable = is_writable(__DIR__ . '/config');
$storageWritable = is_writable(DATA_DIR) || (!file_exists(DATA_DIR) && is_writable(dirname(DATA_DIR)));
$defaultSiteUrl = share_base_url();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>安装向导 - <?= htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="install-page">
<div class="glass-card install-card">
    <?php if ($done): ?>
        <div class="install-done">
            <div class="check-anim">&#10003;</div>
            <h1>安装完成</h1>
            <p>系统初始化成功，管理员账号已创建，现在可以登录使用了。</p>
            <a class="btn btn-primary" href="login.php">前往登录</a>
        </div>
    <?php else: ?>
        <h1>安装向导</h1>
        <p class="install-sub">填写环境信息即可完成部署，无需手动修改代码</p>

        <div class="steps">
            <div class="step-item active" data-step="1"><span class="step-num">1</span>环境检测</div>
            <div class="step-item" data-step="2"><span class="step-num">2</span>数据库</div>
            <div class="step-item" data-step="3"><span class="step-num">3</span>站点设置</div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $e): ?><p><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="install.php" id="install-form" autocomplete="off">
            <input type="hidden" name="action" value="install">

            <div class="step-panel" data-panel="1">
                <table class="env-table">
                    <tr><td>PHP 版本</td><td><span class="env-badge <?= $phpOk ? 'ok' : 'bad' ?>"><?= PHP_VERSION ?></span></td></tr>
                    <tr><td>pdo_mysql 扩展</td><td><span class="env-badge <?= $exts['pdo_mysql'] ? 'ok' : 'bad' ?>"><?= $exts['pdo_mysql'] ? '已启用' : '未启用' ?></span></td></tr>
                    <tr><td>mbstring 扩展</td><td><span class="env-badge <?= $exts['mbstring'] ? 'ok' : 'bad' ?>"><?= $exts['mbstring'] ? '已启用' : '未启用' ?></span></td></tr>
                    <tr><td>openssl 扩展</td><td><span class="env-badge <?= $exts['openssl'] ? 'ok' : 'bad' ?>"><?= $exts['openssl'] ? '已启用' : '未启用' ?></span></td></tr>
                    <tr><td>config 目录可写</td><td><span class="env-badge <?= $configWritable ? 'ok' : 'bad' ?>"><?= $configWritable ? '可写' : '不可写' ?></span></td></tr>
                    <tr><td>storage 目录可写</td><td><span class="env-badge <?= $storageWritable ? 'ok' : 'bad' ?>"><?= $storageWritable ? '可写' : '不可写' ?></span></td></tr>
                </table>
                <button class="btn btn-primary btn-block" type="button" data-next="1">下一步</button>
            </div>

            <div class="step-panel" data-panel="2" style="display:none">
                <div class="field">
                    <label>数据库地址</label>
                    <input type="text" name="db_host" value="127.0.0.1" required>
                    <p class="hint">一般填 127.0.0.1，宝塔本地数据库不需要改</p>
                </div>
                <div class="field">
                    <label>数据库端口</label>
                    <input type="number" name="db_port" value="3306" required>
                </div>
                <div class="field">
                    <label>数据库名</label>
                    <input type="text" name="db_name" placeholder="例如 pan_vxva" required>
                    <p class="hint">宝塔「数据库」里创建的空库名</p>
                </div>
                <div class="field">
                    <label>数据库用户名</label>
                    <input type="text" name="db_user" placeholder="例如 pan_user" required>
                </div>
                <div class="field">
                    <label>数据库密码</label>
                    <input type="password" name="db_pass" placeholder="数据库密码" required>
                </div>
                <div class="btn-row">
                    <button class="btn btn-ghost" type="button" data-prev="2">上一步</button>
                    <button class="btn btn-ghost" type="button" id="test-db">测试连接</button>
                    <button class="btn btn-primary" type="button" data-next="2">下一步</button>
                </div>
                <div class="test-result" id="test-result"></div>
            </div>

            <div class="step-panel" data-panel="3" style="display:none">
                <div class="field">
                    <label>网站名称</label>
                    <input type="text" name="site_name" placeholder="例如：我的网盘" required>
                </div>
                <div class="field">
                    <label>网站头像（Logo，可选）</label>
                    <input type="url" name="site_avatar" placeholder="填写图片 http(s) 链接，可留空">
                    <p class="hint">用于登录页与分享页展示</p>
                </div>
                <div class="field">
                    <label>网站访问地址</label>
                    <input type="url" name="site_url" value="<?= htmlspecialchars($defaultSiteUrl, ENT_QUOTES, 'UTF-8') ?>" required>
                    <p class="hint">用于生成分享链接，默认为当前访问地址，可改为正式域名</p>
                </div>
                <div class="field">
                    <label>管理员用户名</label>
                    <input type="text" name="admin_user" placeholder="3-32 位字母、数字或下划线" required>
                </div>
                <div class="field">
                    <label>管理员密码</label>
                    <input type="password" name="admin_pass" placeholder="至少 8 位" required>
                </div>
                <div class="field">
                    <label>确认密码</label>
                    <input type="password" name="admin_pass2" placeholder="再次输入密码" required>
                </div>
                <div class="btn-row">
                    <button class="btn btn-ghost" type="button" data-prev="3">上一步</button>
                    <button class="btn btn-primary" type="submit">开始安装</button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script src="assets/js/main.js"></script>
</body>
</html>
