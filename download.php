<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

no_cache_headers();

$pdo = Database::conn();
$file = null;

if (isset($_GET['id'])) {
    require_login();
    $id = (int) $_GET['id'];
    $stmt = $pdo->prepare('SELECT * FROM files WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$id, (int) $_SESSION['user_id']]);
    $file = $stmt->fetch();
} elseif (isset($_GET['share'])) {
    $code = (string) $_GET['share'];
    if (!preg_match('/^[a-zA-Z0-9]{10}$/', $code)) {
        exit('无效的分享链接');
    }
    $stmt = $pdo->prepare(
        'SELECT f.* FROM shares s JOIN files f ON f.id = s.file_id
         WHERE s.share_code = ? AND s.type = "link" LIMIT 1'
    );
    $stmt->execute([$code]);
    $file = $stmt->fetch();
} elseif (isset($_GET['token'])) {
    $token = (string) $_GET['token'];
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        exit('无效的下载链接');
    }
    $stmt = $pdo->prepare(
        'SELECT f.* FROM download_tokens dt
         JOIN shares s ON s.id = dt.share_id
         JOIN files f ON f.id = s.file_id
         WHERE dt.token = ? AND dt.expires_at > NOW() AND dt.used = 0 LIMIT 1'
    );
    $stmt->execute([$token]);
    $file = $stmt->fetch();
    if ($file) {
        $pdo->prepare('UPDATE download_tokens SET used = 1 WHERE token = ?')->execute([$token]);
    }
} else {
    exit('参数错误');
}

if (!$file) {
    exit('文件不存在或下载链接已失效，请返回分享页面重新获取下载链接。');
}

$path = DATA_DIR . '/' . $file['stored_name'];
if (!is_file($path)) {
    exit('文件存储异常，请联系管理员。');
}

$name = $file['original_name'];

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/octet-stream');
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($name));
header('Content-Length: ' . (int) $file['size']);
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

@set_time_limit(0);
$fp = fopen($path, 'rb');
if ($fp) {
    while (!feof($fp)) {
        echo fread($fp, 1024 * 1024);
        flush();
    }
    fclose($fp);
}
exit;
