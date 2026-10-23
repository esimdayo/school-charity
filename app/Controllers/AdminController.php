<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{App, Auth, Controller, Lang, Setting, Upload};
use App\Models\{Item, Order, Stats};
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
            Telegram::notifyBuyer((int) $id);
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
            Telegram::notifyBuyer((int) $id);
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

    public function stats(): void
    {
        $this->requireAdmin();
        $this->view('admin/stats', [
            'title' => Lang::t('stats_title'),
            'totals' => Stats::totals(),
            'categories' => Stats::byCategory(),
            'donors' => Stats::topDonors(),
            'weeks' => Stats::weekly(),
        ]);
    }

    // Fundraising goal and the story of the person in need.
    public function settings(string $error = ''): void
    {
        $this->requireAdmin();
        $this->view('admin/settings', [
            'title' => Lang::t('settings_title'),
            'goal' => (int) Setting::get('goal_amount', '0'),
            'person' => StoryController::person(),
            'storyTitle' => Setting::get('story_title'),
            'storyText' => Setting::get('story_text'),
            'storyPhoto' => Setting::get('story_photo'),
            'error' => $error,
            'flash' => $this->takeFlash(),
        ], $error ? 400 : 200);
    }

    public function saveSettings(): void
    {
        $this->requireAdmin();
        $this->checkCsrf();
        try {
            $photo = Upload::image($_FILES['story_photo'] ?? null, ROOT . '/public/uploads', false);
        } catch (\RuntimeException $e) {
            $this->settings(Lang::t($e->getMessage()));
            return;
        }
        $goal = max(0, min(10_000_000_000, (int) round((float) ($_POST['goal_amount'] ?? 0))));
        Setting::set('goal_amount', (string) $goal);
        $condition = (string) ($_POST['person_condition'] ?? '');
        Setting::set('person_name', $this->input('person_name', 120));
        Setting::set('person_age', (string) max(0, min(120, (int) ($_POST['person_age'] ?? 0))));
        Setting::set('person_condition', in_array($condition, StoryController::CONDITIONS, true) ? $condition : '');
        Setting::set('person_health', $this->input('person_health', 300));
        Setting::set('person_need', $this->input('person_need', 300));
        Setting::set('story_title', $this->input('story_title', 120));
        Setting::set('story_text', $this->input('story_text', 10000));
        if ($photo) Setting::set('story_photo', $photo);
        elseif (isset($_POST['remove_photo'])) Setting::set('story_photo', '');
        $_SESSION['flash'] = ['ok', 'settings_saved'];
        $this->redirect('/admin/settings');
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
