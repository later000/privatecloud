<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
no_cache_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error_json('请求方式错误');
}
csrf_verify();

$fileId = (int) ($_POST['file_id'] ?? 0);
$type = $_POST['type'] ?? 'page';
if (!in_array($type, ['page', 'link'], true)) {
    send_error_json('分享类型不合法');
}

$pdo = Database::conn();
$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT id FROM files WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$fileId, $userId]);
if (!$stmt->fetch()) {
    send_error_json('文件不存在');
}

$stmt = $pdo->prepare('SELECT share_code FROM shares WHERE file_id = ? LIMIT 1');
$stmt->execute([$fileId]);
$existing = $stmt->fetch();

if ($existing) {
    $code = $existing['share_code'];
    $pdo->prepare('UPDATE shares SET type = ? WHERE file_id = ?')->execute([$type, $fileId]);
} else {
    do {
        $code = create_share_code();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM shares WHERE share_code = ?');
        $stmt->execute([$code]);
    } while ((int) $stmt->fetchColumn() > 0);
    $pdo->prepare('INSERT INTO shares (file_id, share_code, type) VALUES (?, ?, ?)')->execute([$fileId, $code, $type]);
}

$url = $type === 'link'
    ? share_base_url() . '/share_link.php?code=' . $code
    : share_base_url() . '/share.php?code=' . $code;

send_ok_json(['code' => $code, 'type' => $type, 'url' => $url]);
