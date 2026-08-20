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

$userId = (int) $_SESSION['user_id'];
$uploaded = 0;
$errors = [];

$pdo = Database::conn();

foreach ($_FILES['files']['name'] ?? [] as $i => $name) {
    $tmp = $_FILES['files']['tmp_name'][$i] ?? '';
    $size = (int) ($_FILES['files']['size'][$i] ?? 0);
    $error = $_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE;

    if (!is_string($name) || $name === '') {
        continue;
    }
    if ($error !== UPLOAD_ERR_OK) {
        $errors[] = safe_filename($name) . '：上传失败（错误码 ' . $error . '）';
        continue;
    }
    if (!is_uploaded_file($tmp)) {
        $errors[] = safe_filename($name) . '：非法上传';
        continue;
    }
    if ($size > MAX_UPLOAD_SIZE) {
        $errors[] = safe_filename($name) . '：超过单文件大小限制';
        continue;
    }

    $storedName = bin2hex(random_bytes(16));
    $dest = DATA_DIR . '/' . $storedName;

    if (!move_uploaded_file($tmp, $dest)) {
        $errors[] = safe_filename($name) . '：保存失败，请检查 storage 目录权限';
        continue;
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO files (user_id, original_name, stored_name, size, mime) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $userId,
            safe_filename($name),
            $storedName,
            $size,
            substr((string) ($_FILES['files']['type'][$i] ?? ''), 0, 120),
        ]);
        $uploaded++;
    } catch (Throwable $e) {
        @unlink($dest);
        $errors[] = safe_filename($name) . '：数据库保存失败';
    }
}

send_ok_json(['uploaded' => $uploaded, 'errors' => $errors]);
