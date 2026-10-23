<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

// Buyers, known by their Telegram account.
final class User
{
    // A login link is valid this long after the login page made it.
    public const LOGIN_SECONDS = 600;

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    // Creates or updates the user for a Telegram account ("from" of a message).
    public static function fromTelegram(array $from): array
    {
        $tgId = (string) $from['id'];
        $name = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')) ?: 'Telegram';
        $username = (string) ($from['username'] ?? '');
        if (DB::one('SELECT id FROM users WHERE telegram_id = ?', [$tgId])) {
            DB::run('UPDATE users SET name = ?, username = ? WHERE telegram_id = ?', [$name, $username, $tgId]);
        } else {
            DB::run('INSERT INTO users (telegram_id, name, username, created_at) VALUES (?, ?, ?, ?)', [$tgId, $name, $username, time()]);
        }
        return DB::one('SELECT * FROM users WHERE telegram_id = ?', [$tgId]);
    }

    public static function displayName(array $user): string
    {
        return self::realName($user) . ($user['username'] !== '' ? ' (@' . $user['username'] . ')' : '');
    }

    // The name from the profile, or the Telegram name until the profile is filled in.
    public static function realName(array $user): string
    {
        return self::profileDone($user) ? $user['first_name'] . ' ' . $user['last_name'] : $user['name'];
    }

    // Right after the first login the profile is empty; the site asks for it before buying.
    public static function profileDone(array $user): bool
    {
        return $user['first_name'] !== '' && $user['last_name'] !== '' && $user['role'] !== '';
    }

    public static function saveProfile(int $id, string $first, string $last, string $role, string $class): void
    {
        DB::run('UPDATE users SET first_name = ?, last_name = ?, role = ?, class_name = ? WHERE id = ?',
            [$first, $last, $role, $class, $id]);
    }

    // Login links: the site makes a token, the buyer opens t.me/bot?start=login_TOKEN,
    // the bot marks the token with their account, and the site logs them in.
    public static function newLoginToken(): string
    {
        $token = bin2hex(random_bytes(16));
        DB::run('DELETE FROM login_tokens WHERE created_at < ?', [time() - self::LOGIN_SECONDS]);
        DB::run('INSERT INTO login_tokens (token, created_at) VALUES (?, ?)', [$token, time()]);
        return $token;
    }

    public static function loginToken(string $token): ?array
    {
        return DB::one('SELECT * FROM login_tokens WHERE token = ? AND created_at >= ?', [$token, time() - self::LOGIN_SECONDS]);
    }

    // Called by the bot. Returns false if the link is unknown, old or already used.
    public static function confirmLoginToken(string $token, int $userId): bool
    {
        return DB::run('UPDATE login_tokens SET user_id = ? WHERE token = ? AND user_id IS NULL AND created_at >= ?',
            [$userId, $token, time() - self::LOGIN_SECONDS]) === 1;
    }

    public static function deleteLoginToken(string $token): void
    {
        DB::run('DELETE FROM login_tokens WHERE token = ?', [$token]);
    }
}
