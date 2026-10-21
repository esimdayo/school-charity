<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

// Numbers for the admin statistics page. Counts paid and picked-up orders of all time.
final class Stats
{
    private const SOLD = "o.status IN ('paid', 'picked_up')";

    public static function totals(): array
    {
        $sold = DB::one('SELECT COALESCE(SUM(o.amount), 0) AS raised, COUNT(*) AS sold, COALESCE(AVG(o.amount), 0) AS average
                         FROM orders o WHERE ' . self::SOLD);
        $sold['on_sale'] = (int) DB::one("SELECT COUNT(*) AS n FROM items WHERE status = 'available'")['n'];
        $sold['donors'] = (int) DB::one("SELECT COUNT(DISTINCT donor_name) AS n FROM items WHERE status NOT IN ('pending', 'rejected')")['n'];
        return $sold;
    }

    // [category => ['raised' => .., 'sold' => ..]] for every category, biggest first.
    public static function byCategory(): array
    {
        $rows = [];
        foreach (Item::CATEGORIES as $c) $rows[$c] = ['raised' => 0, 'sold' => 0];
        foreach (DB::all('SELECT i.category, SUM(o.amount) AS raised, COUNT(*) AS sold FROM orders o
                          JOIN items i ON i.id = o.item_id WHERE ' . self::SOLD . ' GROUP BY i.category') as $r) {
            $rows[$r['category']] = ['raised' => (int) $r['raised'], 'sold' => (int) $r['sold']];
        }
        uasort($rows, fn ($a, $b) => $b['raised'] <=> $a['raised']);
        return $rows;
    }

    // Donors whose items raised the most money.
    public static function topDonors(int $limit = 10): array
    {
        return DB::all("SELECT i.donor_name, i.donor_role,
                               COUNT(DISTINCT i.id) AS donated,
                               COUNT(o.id) AS sold,
                               COALESCE(SUM(o.amount), 0) AS raised
                        FROM items i
                        LEFT JOIN orders o ON o.item_id = i.id AND " . self::SOLD . "
                        WHERE i.status NOT IN ('pending', 'rejected')
                        GROUP BY i.donor_name, i.donor_role
                        ORDER BY raised DESC, donated DESC
                        LIMIT " . $limit);
    }

    // Money raised in each of the last $weeks weeks (Monday to Sunday), oldest first:
    // [['start' => timestamp, 'raised' => .., 'sold' => ..], ...]
    public static function weekly(int $weeks = 12): array
    {
        $monday = strtotime('monday this week', time());
        $result = [];
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $result[] = ['start' => strtotime("-$i week", $monday), 'raised' => 0, 'sold' => 0];
        }
        $from = $result[0]['start'];
        foreach (DB::all('SELECT o.amount, o.paid_at FROM orders o WHERE ' . self::SOLD . ' AND o.paid_at >= ?', [$from]) as $r) {
            $index = intdiv((int) $r['paid_at'] - $from, 7 * 86400);
            if (isset($result[$index])) {
                $result[$index]['raised'] += (int) $r['amount'];
                $result[$index]['sold']++;
            }
        }
        return $result;
    }
}
