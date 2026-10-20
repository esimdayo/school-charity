<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Database as DB;

// Small values the admin can change on the site, e.g. when "raised" was reset.
final class Setting
{
    public static function get(string $key, string $default = ''): string
    {
        return DB::one('SELECT value FROM settings WHERE name = ?', [$key])['value'] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        DB::run('DELETE FROM settings WHERE name = ?', [$key]);
        DB::run('INSERT INTO settings (name, value) VALUES (?, ?)', [$key, $value]);
    }
}
