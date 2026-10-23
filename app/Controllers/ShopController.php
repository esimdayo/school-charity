<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{App, Buyer, Controller, Lang, Setting};
use App\Models\User;
use App\Models\{Item, Order};

final class ShopController extends Controller
{
    public function index(): void
    {
        Order::releaseExpired();
        $category = in_array($_GET['cat'] ?? '', Item::CATEGORIES, true) ? $_GET['cat'] : '';
        $search = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        $this->view('shop/index', [
            'title' => Lang::t('shop_title'),
            'items' => Item::available($category, $search),
            'anyItems' => $category !== '' || $search !== '' ? (bool) Item::available() : null,
            'category' => $category,
            'search' => $search,
            'raised' => Order::totalRaised(),
            'goal' => (int) Setting::get('goal_amount', '0'),
            'hasStory' => StoryController::exists(),
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
            'buyer' => Buyer::current(),
        ]);
    }

    public function buy(string $id): void
    {
        $this->checkCsrf();
        // Buyers log in with Telegram, so the bot can send them their pickup code.
        $user = Buyer::current();
        if (!$user) $this->redirect('/login?back=' . urlencode('/item/' . (int) $id));
        if (!User::profileDone($user)) $this->redirect('/profile?back=' . urlencode('/item/' . (int) $id));
        $provider = (string) ($_POST['provider'] ?? '');
        if (!in_array($provider, (array) App::config('payments', ['card']), true)) {
            $this->view('message', [
                'title' => Lang::t('check_details'),
                'text' => Lang::t('check_details_text'),
                'link' => ['/item/' . (int) $id, Lang::t('back')],
            ], 400);
            return;
        }
        $contact = $user['username'] !== '' ? '@' . $user['username'] : 'Telegram';
        $name = User::realName($user) . ($user['class_name'] !== '' ? ', ' . $user['class_name'] : '');
        $order = Order::create((int) $id, $name, $contact, $provider, (int) $user['id']);
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
