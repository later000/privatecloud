<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
no_cache_headers();

$pdo = Database::conn();
$userId = (int) $_SESSION['user_id'];

$tab = ($_GET['tab'] ?? 'files') === 'share' ? 'share' : 'files';

$per = (int) ($_COOKIE['pan_per_page'] ?? 0);
if (!in_array($per, [10, 20, 30, 40, 50], true)) {
    $per = per_page_default();
    if (!in_array($per, [10, 20, 30, 40, 50], true)) {
        $per = 10;
    }
}

$filePage = max(1, (int) ($_GET['page'] ?? 1));
$sharePage = max(1, (int) ($_GET['spage'] ?? 1));

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM files WHERE user_id = ?');
$countStmt->execute([$userId]);
$fileTotal = (int) $countStmt->fetchColumn();
$filePages = max(1, (int) ceil($fileTotal / $per));
$filePage = min($filePage, $filePages);
$fileOffset = ($filePage - 1) * $per;

$stmt = $pdo->prepare('SELECT * FROM files WHERE user_id = ? ORDER BY id DESC LIMIT ' . $per . ' OFFSET ' . $fileOffset);
$stmt->execute([$userId]);
$files = $stmt->fetchAll();

$countStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM shares s JOIN files f ON f.id = s.file_id WHERE f.user_id = ?'
);
$countStmt->execute([$userId]);
$shareTotal = (int) $countStmt->fetchColumn();
$sharePages = max(1, (int) ceil($shareTotal / $per));
$sharePage = min($sharePage, $sharePages);
$shareOffset = ($sharePage - 1) * $per;

$stmt = $pdo->prepare(
    'SELECT s.id AS share_id, s.share_code, s.type, s.created_at, f.original_name, f.size
     FROM shares s JOIN files f ON f.id = s.file_id
     WHERE f.user_id = ? ORDER BY s.id DESC LIMIT ' . $per . ' OFFSET ' . $shareOffset
);
$stmt->execute([$userId]);
$shares = $stmt->fetchAll();

function paginate(int $current, int $total, int $window = 4): array
{
    if ($total <= 1) {
        return [];
    }
    $start = max(1, $current - $window);
    $end = min($total, $current + $window);
    return range($start, $end);
}

$siteAvatar = safe_setting_url(site_avatar(), AUTHOR_AVATAR);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $tab === 'share' ? '我的分享' : '文件管理' ?> - <?= htmlspecialchars(site_name(), ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-page">

<aside class="sidebar glass">
    <div class="sidebar-brand">
        <div class="brand-logo avatar-logo"><img src="<?= htmlspecialchars($siteAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="" onerror="this.parentNode.style.display='none'"></div>
        <div class="sidebar-name"><?= htmlspecialchars(site_name(), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <nav class="sidebar-nav">
        <a class="nav-item <?= $tab === 'files' ? 'active' : '' ?>" href="dashboard.php?tab=files">文件管理</a>
        <a class="nav-item <?= $tab === 'share' ? 'active' : '' ?>" href="dashboard.php?tab=share">我的分享</a>
        <a class="nav-item" href="settings.php">系统设置</a>
    </nav>
    <div class="sidebar-foot">
        <span class="user-name"><?= htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') ?></span>
        <a class="btn btn-ghost btn-sm" href="logout.php">退出登录</a>
    </div>
</aside>

<main class="content">
<?php if ($tab === 'files'): ?>
<div class="page-enter">
    <section class="glass-card upload-card">
        <h2>上传文件</h2>
        <form id="upload-form" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="upload-zone">
                <label for="file-input" class="upload-label">选择文件</label>
                <input type="file" name="files[]" id="file-input" multiple>
                <p class="hint">支持多文件，单文件最大 <?= htmlspecialchars(format_size(MAX_UPLOAD_SIZE), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </form>
    </section>

    <section class="glass-card">
        <div class="card-head">
            <h2>我的文件（<?= $fileTotal ?>）</h2>
            <select class="per-select" data-per>
                <?php foreach ([10, 20, 30, 40, 50] as $n): ?>
                <option value="<?= $n ?>" <?= $per === $n ? 'selected' : '' ?>><?= $n ?> 条/页</option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (!$files): ?>
            <p class="empty">暂无文件，请先上传。</p>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>文件名</th>
                    <th class="col-size">大小</th>
                    <th class="col-time">上传时间</th>
                    <th class="col-actions">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $f): ?>
                <?php $pv = get_preview_info($f['original_name']); ?>
                <tr>
                    <td class="col-name" title="<?= htmlspecialchars($f['original_name'], ENT_QUOTES, 'UTF-8') ?>">
                        <span class="file-ext"><?= strtoupper(substr((string) pathinfo($f['original_name'], PATHINFO_EXTENSION), 0, 4)) ?: 'FILE' ?></span>
                        <?= htmlspecialchars($f['original_name'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td class="col-size"><?= htmlspecialchars(format_size((int) $f['size']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="col-time"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($f['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="col-actions">
                        <?php if ($pv): ?>
                        <button class="btn btn-ghost btn-sm" type="button" data-preview="<?= (int) $f['id'] ?>" data-name="<?= htmlspecialchars($f['original_name'], ENT_QUOTES, 'UTF-8') ?>" data-type="<?= $pv[1] ?>">预览</button>
                        <?php endif; ?>
                        <button class="btn btn-ghost btn-sm" type="button" data-share-id="<?= (int) $f['id'] ?>">分享</button>
                        <a class="btn btn-ghost btn-sm" href="download.php?id=<?= (int) $f['id'] ?>">下载</a>
                        <form class="inline-form" method="post" action="delete.php" onsubmit="return confirm('确定删除该文件？');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="type" value="file">
                            <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">删除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($filePages > 1): ?>
        <div class="pagination">
            <?php foreach (paginate($filePage, $filePages) as $p): ?>
                <a class="page-num <?= $p === $filePage ? 'active' : '' ?>" href="dashboard.php?tab=files&page=<?= $p ?>"><?= $p ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<?php else: ?>
<div class="page-enter">
    <section class="glass-card">
        <div class="card-head">
            <h2>我的分享（<?= $shareTotal ?>）</h2>
            <select class="per-select" data-per>
                <?php foreach ([10, 20, 30, 40, 50] as $n): ?>
                <option value="<?= $n ?>" <?= $per === $n ? 'selected' : '' ?>><?= $n ?> 条/页</option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (!$shares): ?>
            <p class="empty">暂无分享。</p>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>文件名</th>
                    <th>类型</th>
                    <th class="col-time">分享时间</th>
                    <th class="col-actions">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shares as $s): ?>
                <?php
                $shareUrl = $s['type'] === 'link'
                    ? share_base_url() . '/share_link.php?code=' . $s['share_code']
                    : share_base_url() . '/share.php?code=' . $s['share_code'];
                ?>
                <tr>
                    <td class="col-name" title="<?= htmlspecialchars($s['original_name'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($s['original_name'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td>
                        <span class="type-badge <?= $s['type'] === 'link' ? 'link' : 'page' ?>"><?= $s['type'] === 'link' ? '直链' : '分享页' ?></span>
                    </td>
                    <td class="col-time"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($s['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="col-actions">
                        <button class="btn btn-ghost btn-sm" type="button" data-copy="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?>">复制</button>
                        <?php if ($s['type'] !== 'link'): ?>
                        <a class="btn btn-ghost btn-sm" href="share.php?code=<?= urlencode($s['share_code']) ?>" target="_blank">预览</a>
                        <?php endif; ?>
                        <form class="inline-form" method="post" action="delete.php" onsubmit="return confirm('确定取消该分享？');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="type" value="share">
                            <input type="hidden" name="id" value="<?= (int) $s['share_id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">取消</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($sharePages > 1): ?>
        <div class="pagination">
            <?php foreach (paginate($sharePage, $sharePages) as $p): ?>
                <a class="page-num <?= $p === $sharePage ? 'active' : '' ?>" href="dashboard.php?tab=share&spage=<?= $p ?>"><?= $p ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
<?php endif; ?>
</main>

<div class="modal-mask" id="upload-modal" style="display:none">
    <div class="modal upload-modal">
        <div class="modal-head">
            <span class="modal-title">上传文件</span>
            <button class="modal-close" id="upload-close" type="button">&times;</button>
        </div>
        <div class="modal-body upload-body">
            <div class="upload-info" id="upload-files"></div>
            <div class="progress"><div class="progress-bar" id="upload-progress-bar"></div></div>
            <div class="upload-pct" id="upload-pct">0%</div>
            <div class="upload-result" id="upload-result"></div>
        </div>
    </div>
</div>

<div class="modal-mask" id="share-modal" style="display:none">
    <div class="modal share-modal">
        <div class="modal-head">
            <span class="modal-title">分享文件</span>
            <button class="modal-close" id="share-close" type="button">&times;</button>
        </div>
        <div class="modal-body share-body">
            <div class="share-type-options">
                <button class="share-type-card" type="button" data-share-type="page">
                    <span class="share-type-title">分享页面</span>
                    <span class="share-type-desc">打开引导页预览并下载</span>
                </button>
                <button class="share-type-card" type="button" data-share-type="link">
                    <span class="share-type-title">直链</span>
                    <span class="share-type-desc">点击链接直接开始下载</span>
                </button>
            </div>
            <div class="share-result" id="share-result" style="display:none">
                <p class="share-result-label" id="share-result-label"></p>
                <div class="share-result-url"><input type="text" id="share-result-input" readonly></div>
                <div class="btn-row">
                    <button class="btn btn-primary" type="button" id="share-copy">复制链接</button>
                    <a class="btn btn-ghost" href="#" id="share-open" target="_blank">打开预览</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-mask" id="preview-modal" style="display:none">
    <div class="modal">
        <div class="modal-head">
            <span class="modal-title" id="preview-title"></span>
            <button class="modal-close" id="preview-close" type="button">&times;</button>
        </div>
        <div class="modal-body" id="preview-body"></div>
    </div>
</div>

<script src="assets/js/main.js"></script>
</body>
</html>
