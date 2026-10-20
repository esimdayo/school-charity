<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo) return self::$pdo;
        $dsn = (string) App::config('db.dsn');
        self::$pdo = new PDO($dsn, App::config('db.user') ?: null, App::config('db.password') ?: null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // SQLite: create the tables on first run. MySQL users import schema.mysql.sql.
        if (str_starts_with($dsn, 'sqlite:')) {
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::$pdo->exec(file_get_contents(ROOT . '/database/schema.sqlite.sql'));
        }
        return self::$pdo;
    }

    public static function all(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetch() ?: null;
    }

    // Returns the number of changed rows.
    public static function run(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }
}
