<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{App, Controller, Lang};
use App\Models\{Item, Order};

final class ShopController extends Controller
{
    public function index(): void
    {
        Order::releaseExpired();
        $this->view('shop/index', [
            'title' => Lang::t('shop_title'),
            'items' => Item::available(),
            'raised' => Order::totalRaised(),
        ]);
    }

    public function show(string $id): void
    {
        Order::releaseExpired();
        $item = Item::find((int) $id);
        if (!$item || !in_array($item['status'], ['available', 'reserved', 'sold'], true)) {
            $this->notFound('item_not_found');
            return;
        }
        $this->view('shop/item', [
            'title' => $item['title'],
            'item' => $item,
            'payments' => (array) App::config('payments', ['card']),
        ]);
    }

    public function buy(string $id): void
    {
        $this->checkCsrf();
        $name = $this->input('buyer_name', 80);
        $phone = $this->input('buyer_phone', 20);
        $provider = (string) ($_POST['provider'] ?? '');
        if ($name === '' || !preg_match('/^[+\d\s()-]{7,20}$/', $phone) || !in_array($provider, (array) App::config('payments', ['card']), true)) {
            $this->view('message', [
                'title' => Lang::t('check_details'),
                'text' => Lang::t('check_details_text'),
                'link' => ['/item/' . (int) $id, Lang::t('back')],
            ], 400);
            return;
        }
        $order = Order::create((int) $id, $name, $phone, $provider);
        if (!$order) {
            $this->view('message', [
                'title' => Lang::t('not_available'),
                'text' => Lang::t('not_available_text'),
                'link' => ['/', Lang::t('see_other')],
            ], 409);
            return;
        }
        $this->redirect("/pay/$provider/{$order['token']}");
    }
}
