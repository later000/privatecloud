<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function no_cache_headers(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
}

function format_size(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $size = (float) $bytes;
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}

function random_string(int $length): string
{
    return bin2hex(random_bytes(intdiv($length, 2)));
}

function create_share_code(): string
{
    $alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 10; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $code;
}

function safe_filename(string $name): string
{
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
    $name = str_replace(['\\', '/'], '_', $name);
    $name = trim($name);
    return $name === '' ? 'unnamed' : mb_substr($name, 0, 200);
}

function get_preview_info(string $name): ?array
{
    $map = [
        'jpg' => ['image/jpeg', 'image'],
        'jpeg' => ['image/jpeg', 'image'],
        'png' => ['image/png', 'image'],
        'gif' => ['image/gif', 'image'],
        'webp' => ['image/webp', 'image'],
        'bmp' => ['image/bmp', 'image'],
        'avif' => ['image/avif', 'image'],
        'mp4' => ['video/mp4', 'video'],
        'webm' => ['video/webm', 'video'],
        'ogv' => ['video/ogg', 'video'],
        'mp3' => ['audio/mpeg', 'audio'],
        'wav' => ['audio/wav', 'audio'],
        'ogg' => ['audio/ogg', 'audio'],
        'm4a' => ['audio/mp4', 'audio'],
        'aac' => ['audio/aac', 'audio'],
        'flac' => ['audio/flac', 'audio'],
        'pdf' => ['application/pdf', 'pdf'],
        'txt' => ['text/plain; charset=utf-8', 'text'],
        'log' => ['text/plain; charset=utf-8', 'text'],
        'md' => ['text/plain; charset=utf-8', 'text'],
        'json' => ['text/plain; charset=utf-8', 'text'],
        'csv' => ['text/plain; charset=utf-8', 'text'],
    ];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return $map[$ext] ?? null;
}

function is_installed(): bool
{
    return file_exists(LOCK_FILE);
}

function send_error_json(string $message): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function send_ok_json(array $data = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function get_setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = Database::conn()->query('SELECT skey, svalue FROM settings')->fetchAll();
            foreach ($rows as $r) {
                $cache[$r['skey']] = $r['svalue'];
            }
        } catch (Throwable $e) {
        }
    }
    return $cache[$key] ?? $default;
}

function site_name(): string
{
    return get_setting('site_name', SITE_NAME);
}

function site_url(): string
{
    return get_setting('site_url', SITE_URL);
}

function share_base_url(): string
{
    if (!empty($_SERVER['HTTP_HOST'])) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $fwdProto = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        $scheme = ($https || $fwdProto === 'https') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        if (preg_match('#^[A-Za-z0-9.\-]+(:\d+)?$#', $host)) {
            return $scheme . '://' . $host;
        }
    }
    return site_url();
}

function site_avatar(): string
{
    return get_setting('site_avatar', AUTHOR_AVATAR);
}

function share_feedback(): string
{
    return get_setting('share_feedback', SHARE_FEEDBACK_DEFAULT);
}

function per_page_default(): int
{
    return max(10, (int) get_setting('per_page_default', '10'));
}

function safe_setting_url(string $value, string $fallback): string
{
    return preg_match('#^https?://#', $value) ? $value : $fallback;
}

function client_ip(): string
{
    if (defined('TRUST_PROXY') && TRUST_PROXY && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function rate_limit(): void
{
    if (!defined('RATE_LIMIT_ENABLED') || !RATE_LIMIT_ENABLED) {
        return;
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $limit = $method === 'POST' ? RATE_LIMIT_POST : RATE_LIMIT_GET;
    $window = RATE_WINDOW;
    $dir = DATA_DIR . '/rate';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . '/' . md5(client_ip()) . '.log';
    $now = time();

    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return;
    }
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $times = $raw === '' ? [] : json_decode($raw, true);
    if (!is_array($times)) {
        $times = [];
    }
    $cutoff = $now - $window;
    $times = array_values(array_filter($times, static fn($t) => $t > $cutoff));

    if (count($times) >= $limit) {
        flock($fp, LOCK_UN);
        fclose($fp);
        http_response_code(429);
        exit('请求过于频繁，请稍后再试');
    }

    $times[] = $now;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($times));
    flock($fp, LOCK_UN);
    fclose($fp);
}

function login_attempt_limit(): void
{
    $dir = DATA_DIR . '/rate';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . '/login_' . md5(client_ip()) . '.log';
    $now = time();
    $data = @file_get_contents($file);
    $state = ['fails' => [], 'blocked_until' => 0];
    if ($data !== false) {
        $decoded = json_decode($data, true);
        if (is_array($decoded)) {
            $state = $decoded;
        }
    }
    if (!isset($state['fails']) || !is_array($state['fails'])) {
        $state['fails'] = [];
    }
    if (!isset($state['blocked_until']) || !is_int($state['blocked_until'])) {
        $state['blocked_until'] = 0;
    }

    if ($state['blocked_until'] > $now) {
        $left = (int) ceil(($state['blocked_until'] - $now) / 60);
        exit('登录尝试过于频繁，请 ' . max(1, $left) . ' 分钟后重试');
    }

    if ($state['blocked_until'] !== 0 && $state['blocked_until'] <= $now) {
        $state = ['fails' => [], 'blocked_until' => 0];
    }

    $cutoff = $now - 600;
    $state['fails'] = array_values(array_filter($state['fails'], static fn($t) => $t > $cutoff));
    if (count($state['fails']) >= LOGIN_FAIL_MAX) {
        $state['blocked_until'] = $now + LOGIN_LOCK_MINUTES * 60;
        file_put_contents($file, json_encode($state), LOCK_EX);
        $left = (int) ceil(LOGIN_LOCK_MINUTES);
        exit('登录尝试过于频繁，请 ' . max(1, $left) . ' 分钟后重试');
    }
    $state['_fails'] = $state['fails'];
    $state['_file'] = $file;
    $GLOBALS['login_rate_state'] = $state;
}

function login_failure_record(): void
{
    if (empty($GLOBALS['login_rate_state'])) {
        return;
    }
    $file = $GLOBALS['login_rate_state']['_file'];
    $state = $GLOBALS['login_rate_state'];
    unset($state['_fails'], $state['_file']);
    $state['fails'][] = time();
    file_put_contents($file, json_encode($state), LOCK_EX);
}

function login_success_reset(): void
{
    if (empty($GLOBALS['login_rate_state'])) {
        return;
    }
    @unlink($GLOBALS['login_rate_state']['_file']);
}
