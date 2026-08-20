<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

no_cache_headers();

$code = $_GET['code'] ?? '';
if (!preg_match('/^[a-zA-Z0-9]{10}$/', $code)) {
    exit('分享不存在或已失效');
}

$pdo = Database::conn();

$stmt = $pdo->prepare('SELECT id, type FROM shares WHERE share_code = ? LIMIT 1');
$stmt->execute([$code]);
$share = $stmt->fetch();

if (!$share) {
    exit('分享不存在或已失效');
}

if ($share['type'] === 'link') {
    redirect('download.php?share=' . urlencode($code));
}

$shareId = (int) $share['id'];

$pdo->prepare('DELETE FROM download_tokens WHERE expires_at <= NOW()')->execute();

$token = bin2hex(random_bytes(32));
$stmt = $pdo->prepare('INSERT INTO download_tokens (share_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))');
$stmt->execute([$shareId, $token, TOKEN_TTL]);

redirect('download.php?token=' . $token);
