<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

no_cache_headers();
$pdo = Database::conn();

$code = $_GET['code'] ?? '';
if (!preg_match('/^[a-zA-Z0-9]{10}$/', $code)) {
    exit('分享不存在或已失效');
}

$stmt = $pdo->prepare(
    'SELECT s.type, s.created_at AS shared_at, f.id AS file_id, f.original_name, f.size, f.created_at
     FROM shares s JOIN files f ON f.id = s.file_id WHERE s.share_code = ? LIMIT 1'
);
$stmt->execute([$code]);
$share = $stmt->fetch();

if (!$share) {
    exit('分享不存在或已失效');
}

$pv = get_preview_info($share['original_name']);
$previewUrl = 'preview.php?share=' . urlencode($code);
$siteName = site_name();
$siteAvatar = safe_setting_url(site_avatar(), AUTHOR_AVATAR);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>文件分享 - <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="share-page">
<div class="glass-card share-card">
    <div class="brand">
        <?php if ($siteAvatar): ?>
        <div class="brand-logo avatar-logo"><img src="<?= htmlspecialchars($siteAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>" onerror="this.parentNode.style.display='none'"></div>
        <?php else: ?>
        <div class="brand-logo">H</div>
        <?php endif; ?>
        <h1><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></h1>
    </div>

    <div class="share-file-card">
        <h2 class="file-name" title="<?= htmlspecialchars($share['original_name'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($share['original_name'], ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <div class="file-meta">
            <span>大小：<?= htmlspecialchars(format_size((int) $share['size']), ENT_QUOTES, 'UTF-8') ?></span>
            <span>分享时间：<?= htmlspecialchars(date('Y-m-d H:i', strtotime($share['shared_at'])), ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <?php if ($pv): ?>
        <div class="share-preview">
            <?php if ($pv[1] === 'image'): ?>
                <img src="<?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
            <?php elseif ($pv[1] === 'video'): ?>
                <video src="<?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?>" controls preload="metadata"></video>
            <?php elseif ($pv[1] === 'audio'): ?>
                <audio src="<?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?>" controls></audio>
            <?php elseif ($pv[1] === 'pdf' || $pv[1] === 'text'): ?>
                <iframe src="<?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?>"></iframe>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <a class="btn btn-primary btn-block" href="share_link.php?code=<?= urlencode($code) ?>">下载文件</a>
        <?php if ($share['type'] === 'page'): ?>
        <p class="download-note">点击下载后将生成 10 分钟有效的下载链接，请及时下载。</p>
        <?php endif; ?>
    </div>

    <div class="footer-note"><?= htmlspecialchars(share_feedback(), ENT_QUOTES, 'UTF-8') ?></div>
    <?php $icp = get_setting('site_icp', ''); ?>
    <?php if ($icp !== ''): ?>
    <div class="icp-note"><?= htmlspecialchars($icp, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
</div>
</body>
</html>
