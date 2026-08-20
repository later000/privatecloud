<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
no_cache_headers();

$pdo = Database::conn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $siteName = trim($_POST['site_name'] ?? '');
    $siteAvatar = trim($_POST['site_avatar'] ?? '');
    $siteUrl = trim($_POST['site_url'] ?? '');
    $perPage = (int) ($_POST['per_page'] ?? 10);
    $shareFeedback = trim($_POST['share_feedback'] ?? '');
    $siteIcp = trim($_POST['site_icp'] ?? '');

    if ($siteName === '') {
        $error = '网站名称不能为空';
    } elseif (!preg_match('#^https?://[A-Za-z0-9][A-Za-z0-9.\-:/]*$#', $siteUrl)) {
        $error = '网站地址格式不正确';
    } elseif ($siteAvatar !== '' && !preg_match('#^https?://#', $siteAvatar)) {
        $error = '头像地址必须是 http(s) 链接';
    } elseif (!in_array($perPage, [10, 20, 30, 40, 50], true)) {
        $error = '每页数量不合法';
    } else {
        $stmt = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
        $stmt->execute(['site_name', $siteName]);
        $stmt->execute(['site_url', rtrim($siteUrl, '/')]);
        $stmt->execute(['site_avatar', $siteAvatar]);
        $stmt->execute(['per_page_default', (string) $perPage]);
        $stmt->execute(['share_feedback', $shareFeedback]);
        $stmt->execute(['site_icp', $siteIcp]);
        $saved = true;
    }
}

$siteName = get_setting('site_name', SITE_NAME);
$siteAvatar = get_setting('site_avatar', AUTHOR_AVATAR);
$siteUrl = get_setting('site_url', SITE_URL);
$perPage = per_page_default();
$shareFeedback = get_setting('share_feedback', SHARE_FEEDBACK_DEFAULT);
$siteIcp = get_setting('site_icp', '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>系统设置 - <?= htmlspecialchars(site_name(), ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-page">
<aside class="sidebar glass">
    <div class="sidebar-brand">
        <div class="brand-logo small avatar-logo"><img src="<?= htmlspecialchars(safe_setting_url(site_avatar(), AUTHOR_AVATAR), ENT_QUOTES, 'UTF-8') ?>" alt="" onerror="this.parentNode.style.display='none'"></div>
        <div class="sidebar-name"><?= htmlspecialchars(site_name(), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <nav class="sidebar-nav">
        <a class="nav-item" href="dashboard.php?tab=files">文件管理</a>
        <a class="nav-item" href="dashboard.php?tab=share">我的分享</a>
        <a class="nav-item active" href="settings.php">系统设置</a>
    </nav>
    <div class="sidebar-foot">
        <span class="user-name"><?= htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') ?></span>
        <a class="btn btn-ghost btn-sm" href="logout.php">退出登录</a>
    </div>
</aside>

<main class="content">
    <div class="page-enter">
    <section class="glass-card container-narrow">
        <div class="card-head"><h2>系统设置</h2></div>
        <?php if (!empty($saved)): ?>
            <div class="alert alert-success">保存成功</div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post" action="settings.php">
            <?= csrf_field() ?>
            <div class="field">
                <label>网站名称</label>
                <input type="text" name="site_name" value="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <div class="field">
                <label>网站头像（Logo 链接）</label>
                <input type="url" name="site_avatar" value="<?= htmlspecialchars($siteAvatar, ENT_QUOTES, 'UTF-8') ?>">
                <p class="hint">显示在登录页、分享页和管理后台顶部，填写图片 http(s) 链接</p>
            </div>
            <div class="field">
                <label>网站访问地址</label>
                <input type="url" name="site_url" value="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>" required>
                <p class="hint">用于生成分享链接，例如 https://pan.vxva.cn</p>
            </div>
            <div class="field">
                <label>每页默认显示数量</label>
                <select name="per_page">
                    <?php foreach ([10, 20, 30, 40, 50] as $n): ?>
                    <option value="<?= $n ?>" <?= $perPage === $n ? 'selected' : '' ?>><?= $n ?> 条/页</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>分享页反馈文案</label>
                <input type="text" name="share_feedback" value="<?= htmlspecialchars($shareFeedback, ENT_QUOTES, 'UTF-8') ?>">
                <p class="hint">显示在分享页面底部，留空使用默认文案</p>
            </div>
            <div class="field">
                <label>网站备案号（ICP）</label>
                <input type="text" name="site_icp" value="<?= htmlspecialchars($siteIcp, ENT_QUOTES, 'UTF-8') ?>" placeholder="例如 京ICP备00000000号">
                <p class="hint">显示在分享页和登录页底部，留空不显示</p>
            </div>
            <button class="btn btn-primary" type="submit">保存设置</button>
        </form>
    </section>
    </div>
</main>

<script src="assets/js/main.js"></script>
</body>
</html>
