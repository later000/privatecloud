<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

final class Database
{
    private static ?PDO $pdo = null;

    public static function conn(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
            try {
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$pdo->exec("SET time_zone = '+08:00'");
            } catch (PDOException $e) {
                self::fatal('数据库连接失败，请检查 config/config.php 中的配置是否正确。');
            }
        }
        return self::$pdo;
    }

    public static function fatal(string $message): void
    {
        http_response_code(500);
        exit('<div style="font-family:sans-serif;max-width:520px;margin:80px auto;padding:24px;border:1px solid #e5e7eb;border-radius:10px;color:#111827">'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</div>');
    }
}
