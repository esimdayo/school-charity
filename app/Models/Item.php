<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

// status: pending (waiting for a teacher) | available | reserved | sold | rejected
final class Item
{
    public const ROLES = ['student', 'teacher', 'parent', 'other'];
    public const CATEGORIES = ['books', 'electronics', 'stationery', 'sports', 'toys', 'clothing', 'other'];

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM items WHERE id = ?', [$id]);
    }

    // Items on sale, optionally only one category and only names or
    // descriptions containing $search (any case, also Cyrillic).
    public static function available(string $category = '', string $search = ''): array
    {
        $items = $category !== ''
            ? DB::all("SELECT * FROM items WHERE status = 'available' AND category = ? ORDER BY created_at DESC", [$category])
            : DB::all("SELECT * FROM items WHERE status = 'available' ORDER BY created_at DESC");
        if ($search === '') return $items;
        return array_values(array_filter($items, fn ($i) =>
            mb_stripos($i['title'], $search) !== false || mb_stripos($i['description'], $search) !== false));
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
            'INSERT INTO items (title, description, price, category, photo, donor_name, donor_role, donor_class, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$d['title'], $d['description'], $d['price'], $d['category'], $d['photo'], $d['donor_name'], $d['donor_role'], $d['donor_class'], 'pending', time()]
        );
    }

    // $reason is required when rejecting: why the admin didn't accept the item.
    public static function setReview(int $id, bool $approved, string $reason = ''): void
    {
        DB::run(
            "UPDATE items SET status = ?, reject_reason = ? WHERE id = ? AND status IN ('pending', 'available', 'rejected')",
            [$approved ? 'available' : 'rejected', $approved ? '' : $reason, $id]
        );
    }
}
