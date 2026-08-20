<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
no_cache_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
redirect($type === 'share' ? 'dashboard.php?tab=share' : 'dashboard.php');
}
csrf_verify();

$type = $_POST['type'] ?? '';
$id = (int) ($_POST['id'] ?? 0);
$pdo = Database::conn();
$userId = (int) $_SESSION['user_id'];

if ($type === 'file' && $id > 0) {
    $stmt = $pdo->prepare('SELECT stored_name FROM files WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$id, $userId]);
    $file = $stmt->fetch();
    if ($file) {
        $stmt = $pdo->prepare('SELECT id FROM shares WHERE file_id = ?');
        $stmt->execute([$id]);
        $shareIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if ($shareIds) {
            $in = implode(',', array_map('intval', $shareIds));
            $pdo->exec('DELETE FROM download_tokens WHERE share_id IN (' . $in . ')');
            $pdo->exec('DELETE FROM shares WHERE file_id = ' . $id);
        }
        $pdo->prepare('DELETE FROM files WHERE id = ?')->execute([$id]);
        $path = DATA_DIR . '/' . $file['stored_name'];
        if (is_file($path)) {
            @unlink($path);
        }
    }
} elseif ($type === 'share' && $id > 0) {
    $stmt = $pdo->prepare('SELECT s.id FROM shares s JOIN files f ON f.id = s.file_id WHERE s.id = ? AND f.user_id = ? LIMIT 1');
    $stmt->execute([$id, $userId]);
    if ($stmt->fetch()) {
        $pdo->prepare('DELETE FROM download_tokens WHERE share_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM shares WHERE id = ?')->execute([$id]);
    }
}

redirect('dashboard.php');
