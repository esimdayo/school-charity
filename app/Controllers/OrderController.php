<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Lang};
use App\Models\{Item, Order};

final class OrderController extends Controller
{
    // The buyer's page: payment status and, once paid, the pickup code.
    public function show(string $token): void
    {
        $order = Order::findByToken($token);
        if (!$order) {
            $this->notFound('order_not_found');
            return;
        }
        $this->view('order/show', [
            'title' => Lang::t('your_order'),
            'order' => $order,
            'item' => Item::find((int) $order['item_id']),
        ]);
    }
}
