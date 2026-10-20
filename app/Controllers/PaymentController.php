<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{App, Controller, Lang, Upload};
use App\Models\{Item, Order};
use App\Services\Telegram;

final class PaymentController extends Controller
{
    // Card transfer: the buyer sends money to the recipient's card from their
    // own bank app, then a teacher confirms it arrived.
    public function card(string $token, string $error = ''): void
    {
        $order = $this->pendingOrder($token, 'card');
        if (!$order) return;
        $this->view('payment/card', [
            'title' => Lang::t('provider_card'),
            'order' => $order,
            'item' => Item::find((int) $order['item_id']),
            'error' => $error,
            'minutes' => (int) App::config('reserve_minutes', 30),
        ], $error ? 400 : 200);
    }

    public function cardSubmit(string $token): void
    {
        $this->checkCsrf();
        $order = $this->pendingOrder($token, 'card');
        if (!$order) return;
        if (($_POST['result'] ?? '') === 'cancel') {
            Order::cancel((int) $order['id']);
        } else {
            $last4 = $this->input('last4', 4);
            if (!preg_match('/^\d{4}$/', $last4)) {
                $this->card($token, Lang::t('last4_error'));
                return;
            }
            // Optional screenshot of the transfer; kept outside public/ so only admins see it.
            try {
                $receipt = Upload::image($_FILES['receipt'] ?? null, ROOT . '/storage/receipts', false);
            } catch (\RuntimeException $e) {
                $this->card($token, Lang::t('receipt_' . $e->getMessage()));
                return;
            }
            Telegram::notifyPayment(Order::markAwaiting((int) $order['id'], $last4, $receipt));
        }
        $this->redirect("/order/$token");
    }

    // Fake payment for trying the site. Only works when 'test' is in config payments.
    public function test(string $token): void
    {
        $order = $this->pendingOrder($token, 'test');
        if (!$order) return;
        $this->view('payment/test', [
            'title' => Lang::t('provider_test'),
            'order' => $order,
            'item' => Item::find((int) $order['item_id']),
        ]);
    }

    public function testSubmit(string $token): void
    {
        $this->checkCsrf();
        $order = $this->pendingOrder($token, 'test');
        if (!$order) return;
        if (($_POST['result'] ?? '') === 'paid') Order::markPaid((int) $order['id']);
        else Order::cancel((int) $order['id']);
        $this->redirect("/order/$token");
    }

    private function pendingOrder(string $token, string $provider): ?array
    {
        $order = Order::findByToken($token);
        if (!$order || $order['provider'] !== $provider || !in_array($provider, (array) App::config('payments', []), true)) {
            $this->notFound('order_not_found');
            return null;
        }
        if ($order['status'] !== 'pending') $this->redirect("/order/$token");
        return $order;
    }
}
