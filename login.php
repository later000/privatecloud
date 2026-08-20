<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

no_cache_headers();

if (!is_installed()) {
    redirect('install.php');
}

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    login_attempt_limit();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        $error = '请求已过期，请刷新页面重试';
    } else {
        $user = null;
        try {
            $stmt = Database::conn()->prepare('SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            $error = '系统尚未初始化，请先访问 install.php 完成安装';
        }

        if ($user && password_verify($password, $user['password_hash'])) {
            login_success_reset();
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['username'] = $user['username'];
            redirect('dashboard.php');
        } elseif ($user === null && $error === '') {
            $error = '用户名或密码错误';
        } elseif ($error === '') {
            login_failure_record();
            $error = '用户名或密码错误';
        }
    }
}

$siteName = site_name();
$siteAvatar = safe_setting_url(site_avatar(), AUTHOR_AVATAR);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>登录 - <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
<div class="card login-card">
    <div class="brand">
        <div class="brand-logo avatar-logo"><img src="<?= htmlspecialchars($siteAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>" onerror="this.parentNode.style.display='none'"></div>
        <h1><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></h1>
        <p>私人文件存储，请登录后使用</p>
    </div>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="post" action="login.php" autocomplete="off">
        <?= csrf_field() ?>
        <div class="field">
            <label>用户名</label>
            <input type="text" name="username" required autofocus>
        </div>
        <div class="field">
            <label>密码</label>
            <input type="password" name="password" required>
        </div>
        <button class="btn btn-primary btn-block" type="submit">登 录</button>
    </form>
    <?php $icp = get_setting('site_icp', ''); ?>
    <?php if ($icp !== ''): ?>
    <div class="icp-note"><?= htmlspecialchars($icp, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
</div>
</body>
</html>
