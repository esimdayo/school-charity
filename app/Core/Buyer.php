<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\User;

// The buyer who logged in with Telegram (separate from the admin login).
final class Buyer
{
    public static function current(): ?array
    {
        $id = (int) ($_SESSION['user_id'] ?? 0);
        return $id ? User::find($id) : null;
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
    }
}
