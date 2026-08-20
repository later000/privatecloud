<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

no_cache_headers();
$pdo = Database::conn();

if (isset($_GET['share'])) {
    $code = (string) $_GET['share'];
    if (!preg_match('/^[a-zA-Z0-9]{10}$/', $code)) {
        exit('无效的分享链接');
    }
    $stmt = $pdo->prepare(
        'SELECT f.* FROM shares s JOIN files f ON f.id = s.file_id
         WHERE s.share_code = ? LIMIT 1'
    );
    $stmt->execute([$code]);
    $file = $stmt->fetch();
    if (!$file) {
        exit('分享不存在或已失效');
    }
} else {
    require_login();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        exit('参数错误');
    }
    $stmt = $pdo->prepare('SELECT * FROM files WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$id, (int) $_SESSION['user_id']]);
    $file = $stmt->fetch();
}

if (!$file) {
    exit('文件不存在');
}

$info = get_preview_info($file['original_name']);
if (!$info) {
    exit('该文件类型暂不支持在线预览，请直接下载');
}

$path = DATA_DIR . '/' . $file['stored_name'];
if (!is_file($path)) {
    exit('文件存储异常，请联系管理员');
}

$size = (int) $file['size'];
$mime = $info[0];

header('Content-Type: ' . $mime);
header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode($file['original_name']));
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');

$fp = fopen($path, 'rb');
if (!$fp) {
    exit('文件读取失败');
}

@set_time_limit(0);
$range = $_SERVER['HTTP_RANGE'] ?? '';

if ($range !== '' && preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
    $start = $m[1] === '' ? null : (int) $m[1];
    $end = $m[2] === '' ? null : (int) $m[2];

    if ($start === null && $end !== null) {
        $len = min($end, $size);
        $start = $size - $len;
        $end = $size - 1;
    } else {
        $start = $start ?? 0;
        $end = ($end === null || $end >= $size) ? $size - 1 : $end;
    }

    if ($start > $end || $start >= $size || $end < 0) {
        header('HTTP/1.1 416 Range Not Satisfiable');
        header('Content-Range: bytes */' . $size);
        exit;
    }

    $length = $end - $start + 1;
    header('HTTP/1.1 206 Partial Content');
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    header('Content-Length: ' . $length);

    fseek($fp, $start);
    $remaining = $length;
    while ($remaining > 0 && !feof($fp)) {
        $chunk = fread($fp, min(1024 * 1024, $remaining));
        echo $chunk;
        flush();
        $remaining -= strlen($chunk);
    }
} else {
    header('Content-Length: ' . $size);
    while (!feof($fp)) {
        echo fread($fp, 1024 * 1024);
        flush();
    }
}

fclose($fp);
exit;
