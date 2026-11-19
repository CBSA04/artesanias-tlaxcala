<?php
declare(strict_types=1);
namespace App\Core;
use PDO;

final class Database {
    private static ?PDO $instance = null;
    public static function connection(): PDO {
        if (self::$instance) return self::$instance;
        if (\env('DB_CONNECTION', 'mysql') === 'sqlite') {
            $file = \env('DB_DATABASE', ROOT . '/storage/demo.sqlite');
            if (!str_starts_with($file, '/')) $file = ROOT . '/' . $file;
            $db = new PDO('sqlite:' . $file);
            $db->exec('PRAGMA foreign_keys = ON');
            $db->exec('PRAGMA busy_timeout = 10000');
            $db->exec('PRAGMA journal_mode = WAL');
        } else {
            $name = \env('DB_DATABASE', 'artesanias_tlaxcala');
            if (!preg_match('/^[a-zA-Z0-9_]+$/D', $name)) throw new \RuntimeException('Nombre de base de datos no válido.');
            $dsn = 'mysql:host=' . \env('DB_HOST', '127.0.0.1') . ';port=' . \env('DB_PORT', '3306') . ';dbname=' . $name . ';charset=utf8mb4';
            $db = new PDO($dsn, \env('DB_USERNAME', 'root'), \env('DB_PASSWORD'), [PDO::ATTR_TIMEOUT => 5]);
        }
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        return self::$instance = $db;
    }
    public static function lock(PDO $db): string { return $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''; }
    public static function one(string $sql, array $params = []): ?array {
        $q = self::connection()->prepare($sql); $q->execute($params); return $q->fetch() ?: null;
    }
    public static function all(string $sql, array $params = []): array {
        $q = self::connection()->prepare($sql); $q->execute($params); return $q->fetchAll();
    }
    public static function run(string $sql, array $params = []): \PDOStatement {
        $q = self::connection()->prepare($sql); $q->execute($params); return $q;
    }
}
