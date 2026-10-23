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
        self::upgrade(self::$pdo);
        return self::$pdo;
    }

    // Adds what newer versions of the site need to a database made by an
    // older version, so updating with git pull is enough (SQLite and MySQL).
    private static function upgrade(PDO $pdo): void
    {
        $columns = [
            ['items', 'category', "VARCHAR(16) NOT NULL DEFAULT 'other'"],
            ['orders', 'receipt', 'VARCHAR(64) NULL'],
            ['orders', 'user_id', 'INT NULL'],
        ];
        foreach ($columns as [$table, $column, $type]) {
            try {
                $pdo->query("SELECT $column FROM $table LIMIT 1");
            } catch (\PDOException) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $column $type");
            }
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (id ' . self::autoId($pdo) . ', telegram_id VARCHAR(32) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL, username VARCHAR(64) NOT NULL DEFAULT \'\', created_at BIGINT NOT NULL)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS login_tokens (token VARCHAR(64) PRIMARY KEY, user_id INT NULL, created_at BIGINT NOT NULL)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS settings (name VARCHAR(64) PRIMARY KEY, value TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS telegram_item_messages (item_id INT NOT NULL, chat_id VARCHAR(32) NOT NULL, message_id BIGINT NOT NULL)');
    }

    private static function autoId(PDO $pdo): string
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'INT AUTO_INCREMENT PRIMARY KEY';
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
