<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\{App, Database as DB};

// status: pending (buyer is paying) | awaiting (buyer says they sent the money,
// a teacher checks) | paid | picked_up | cancelled | unpaid (money never arrived)
final class Order
{
    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM orders WHERE id = ?', [$id]);
    }

    public static function findByToken(string $token): ?array
    {
        return DB::one('SELECT * FROM orders WHERE token = ?', [$token]);
    }

    public static function findByCode(string $code): ?array
    {
        return DB::one('SELECT * FROM orders WHERE pickup_code = ?', [$code]);
    }

    public static function recent(): array
    {
        return DB::all("SELECT o.*, i.title, i.photo FROM orders o JOIN items i ON i.id = o.item_id
                        WHERE o.status != 'cancelled' ORDER BY o.created_at DESC, o.id DESC LIMIT 200");
    }

    public static function awaiting(): array
    {
        return DB::all("SELECT o.*, i.title, i.photo FROM orders o JOIN items i ON i.id = o.item_id
                        WHERE o.status = 'awaiting' ORDER BY o.sent_at");
    }

    public static function totalRaised(): array
    {
        return DB::one("SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS count FROM orders WHERE status IN ('paid', 'picked_up')");
    }

    // Reserves the item and creates the order, or returns null if someone else got it first.
    public static function create(int $itemId, string $buyerName, string $buyerPhone, string $provider): ?array
    {
        self::releaseExpired();
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            if (DB::run("UPDATE items SET status = 'reserved' WHERE id = ? AND status = 'available'", [$itemId]) !== 1) {
                $pdo->rollBack();
                return null;
            }
            $item = Item::find($itemId);
            $id = DB::insert(
                'INSERT INTO orders (item_id, amount, buyer_name, buyer_phone, provider, token, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$itemId, $item['price'], $buyerName, $buyerPhone, $provider, bin2hex(random_bytes(16)), 'pending', time()]
            );
            $pdo->commit();
            return self::find($id);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // Unpaid orders past the reservation time go back on sale. Orders where
    // the buyer already said they paid wait for the teacher instead.
    public static function releaseExpired(): void
    {
        $cutoff = time() - (int) App::config('reserve_minutes', 30) * 60;
        foreach (DB::all("SELECT id FROM orders WHERE status = 'pending' AND created_at < ?", [$cutoff]) as $row) {
            self::cancel((int) $row['id']);
        }
    }

    public static function markAwaiting(int $id, string $payerCard): ?array
    {
        DB::run("UPDATE orders SET status = 'awaiting', payer_card = ?, sent_at = ? WHERE id = ? AND status = 'pending'",
            [$payerCard, time(), $id]);
        return self::find($id);
    }

    public static function markPaid(int $id): ?array
    {
        $order = self::find($id);
        if (!$order || !in_array($order['status'], ['pending', 'awaiting'], true)) return $order;
        DB::run("UPDATE orders SET status = 'paid', paid_at = ?, pickup_code = ? WHERE id = ?", [time(), self::newPickupCode(), $id]);
        DB::run("UPDATE items SET status = 'sold' WHERE id = ?", [$order['item_id']]);
        return self::find($id);
    }

    public static function cancel(int $id, string $status = 'cancelled'): ?array
    {
        $order = self::find($id);
        if (!$order || !in_array($order['status'], ['pending', 'awaiting'], true)) return $order;
        DB::run('UPDATE orders SET status = ? WHERE id = ?', [$status, $id]);
        DB::run("UPDATE items SET status = 'available' WHERE id = ? AND status = 'reserved'", [$order['item_id']]);
        return self::find($id);
    }

    public static function markPickedUp(int $id): ?array
    {
        DB::run("UPDATE orders SET status = 'picked_up', picked_up_at = ? WHERE id = ? AND status = 'paid'", [time(), $id]);
        return self::find($id);
    }

    // 6 characters without look-alikes (0/O, 1/I) so it is easy to read aloud.
    private static function newPickupCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        } while (self::findByCode($code));
        return $code;
    }
}
