<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Buyer, Controller, Lang};
use App\Models\Order;

// "My orders" for a buyer who logged in with Telegram.
final class MyController extends Controller
{
    public function orders(): void
    {
        $user = Buyer::current();
        if (!$user) $this->redirect('/login?back=' . urlencode('/my'));
        Order::releaseExpired();
        $this->view('my/orders', [
            'title' => Lang::t('my_orders_title'),
            'user' => $user,
            'orders' => Order::forUser((int) $user['id']),
        ]);
    }
}
