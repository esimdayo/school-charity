<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

// status: pending (waiting for a teacher) | available | reserved | sold | rejected
final class Item
{
    public const ROLES = ['student', 'teacher', 'parent', 'other'];

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM items WHERE id = ?', [$id]);
    }

    public static function available(): array
    {
        return DB::all("SELECT * FROM items WHERE status = 'available' ORDER BY created_at DESC");
    }

    public static function pending(): array
    {
        return DB::all("SELECT * FROM items WHERE status = 'pending' ORDER BY created_at");
    }

    public static function reviewed(): array
    {
        return DB::all("SELECT * FROM items WHERE status != 'pending' ORDER BY created_at DESC LIMIT 200");
    }

    public static function create(array $d): int
    {
        return DB::insert(
            'INSERT INTO items (title, description, price, photo, donor_name, donor_role, donor_class, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$d['title'], $d['description'], $d['price'], $d['photo'], $d['donor_name'], $d['donor_role'], $d['donor_class'], 'pending', time()]
        );
    }

    public static function setReview(int $id, bool $approved): void
    {
        DB::run(
            "UPDATE items SET status = ? WHERE id = ? AND status IN ('pending', 'available', 'rejected')",
            [$approved ? 'available' : 'rejected', $id]
        );
    }
}
