<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{App, Auth, Controller, Lang, Setting};
use App\Models\{Item, Order};
use App\Services\Telegram;

final class AdminController extends Controller
{
    public function loginForm(): void
    {
        $this->view('admin/login', ['title' => Lang::t('admin_login'), 'error' => isset($_GET['error'])]);
    }

    public function login(): void
    {
        $this->checkCsrf();
        if (!Auth::attempt((string) ($_POST['password'] ?? ''))) $this->redirect('/admin/login?error=1');
        $this->redirect('/admin');
    }

    public function logout(): void
    {
        $this->checkCsrf();
        Auth::logout();
        $this->redirect('/');
    }

    public function dashboard(): void
    {
        $this->requireAdmin();
        Order::releaseExpired();
        $orders = Order::recent();
        $this->view('admin/dashboard', [
            'title' => Lang::t('nav_admin'),
            'pending' => Item::pending(),
            'items' => Item::reviewed(),
            'orders' => $orders,
            'toCheck' => Order::awaiting(),
            'raised' => Order::totalRaised(),
            'raisedSince' => (int) Setting::get('raised_since', '0'),
            'toPickUp' => count(array_filter($orders, fn ($o) => $o['status'] === 'paid')),
            'payments' => (array) App::config('payments', ['card']),
            'telegram' => Telegram::enabled(),
            'flash' => $this->takeFlash(),
        ]);
    }

    public function approve(string $id): void
    {
        $this->requireAdmin();
        $this->checkCsrf();
        $wasPending = (Item::find((int) $id)['status'] ?? '') === 'pending';
        Item::setReview((int) $id, true);
        if ($wasPending) Telegram::markItemHandled((int) $id, Telegram::websiteName());
        $this->redirect('/admin');
    }

    public function reject(string $id): void
    {
        $this->requireAdmin();
        $this->checkCsrf();
        $wasPending = (Item::find((int) $id)['status'] ?? '') === 'pending';
        Item::setReview((int) $id, false);
        if ($wasPending) Telegram::markItemHandled((int) $id, Telegram::websiteName());
        $this->redirect('/admin');
    }

    // The teacher saw the card transfer arrive in the bank app.
    public function confirmPayment(string $id): void
    {
        $this->requireAdmin();
        $this->checkCsrf();
        $order = Order::find((int) $id);
        if ($order && $order['status'] === 'awaiting') {
            Order::markPaid((int) $id);
            Telegram::markHandled((int) $id, Telegram::websiteName());
        }
        $this->redirect('/admin');
    }

    public function rejectPayment(string $id): void
    {
        $this->requireAdmin();
        $this->checkCsrf();
        $order = Order::find((int) $id);
        if ($order && $order['status'] === 'awaiting') {
            Order::cancel((int) $id, 'unpaid');
            Telegram::markHandled((int) $id, Telegram::websiteName());
        }
        $this->redirect('/admin');
    }

    public function pickup(): void
    {
        $this->requireAdmin();
        $code = strtoupper(trim((string) ($_GET['code'] ?? '')));
        $order = $code !== '' ? Order::findByCode($code) : null;
        $this->view('admin/pickup', [
            'title' => Lang::t('pickup_title'),
            'code' => $code,
            'order' => $order,
            'item' => $order ? Item::find((int) $order['item_id']) : null,
        ]);
    }

    public function handOver(string $id): void
    {
        $this->requireAdmin();
        $this->checkCsrf();
        $order = Order::markPickedUp((int) $id);
        $this->redirect('/admin/pickup?code=' . urlencode((string) ($order['pickup_code'] ?? '')));
    }

    public function resetRaised(): void
    {
        $this->requireAdmin();
        $this->checkCsrf();
        Order::resetRaised();
        $_SESSION['flash'] = ['ok', 'raised_reset_done'];
        $this->redirect('/admin');
    }

    // Receipt screenshots live in storage/receipts, outside public/, so only
    // a logged-in admin can open them.
    public function receipt(string $id): void
    {
        $this->requireAdmin();
        $order = Order::find((int) $id);
        $file = $order && $order['receipt'] ? ROOT . '/storage/receipts/' . basename($order['receipt']) : '';
        if (!$file || !is_file($file)) {
            $this->notFound('order_not_found');
            return;
        }
        header('Content-Type: ' . (new \finfo(FILEINFO_MIME_TYPE))->file($file));
        header('Cache-Control: private, max-age=3600');
        readfile($file);
    }

    public function connectTelegram(): void
    {
        $this->requireAdmin();
        $this->checkCsrf();
        $_SESSION['flash'] = Telegram::connectWebhook() ? ['ok', 'tg_connected'] : ['warn', 'tg_connect_failed'];
        $this->redirect('/admin');
    }

    private function requireAdmin(): void
    {
        if (!Auth::check()) $this->redirect('/admin/login');
    }

    private function takeFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }
}
