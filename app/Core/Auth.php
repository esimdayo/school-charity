<?php
declare(strict_types=1);

namespace App\Core;

// One shared admin password from config.php.
final class Auth
{
    public static function check(): bool
    {
        return ($_SESSION['admin'] ?? false) === true;
    }

    public static function attempt(string $password): bool
    {
        $expected = (string) App::config('admin_password', '');
        if ($expected === '' || !hash_equals(hash('sha256', $expected), hash('sha256', $password))) return false;
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }
}
