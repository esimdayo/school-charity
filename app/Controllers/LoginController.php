<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Buyer, Controller, Lang};
use App\Models\User;
use App\Services\Telegram;

// "Log in with Telegram": the buyer presses Start in the shop's bot and the
// page logs them in by itself. Needs the bot running (telegram-bot.bat or webhook).
final class LoginController extends Controller
{
    public function form(): void
    {
        $back = $this->safeBack((string) ($_GET['back'] ?? '/'));
        if (Buyer::current()) $this->redirect($back);
        $bot = Telegram::enabled() ? Telegram::botUsername() : '';
        if ($bot === '') {
            $this->view('message', ['title' => Lang::t('login_title'), 'text' => Lang::t('login_not_configured')], 503);
            return;
        }
        $token = (string) ($_SESSION['login_token'] ?? '');
        if ($token === '' || !User::loginToken($token)) {
            $token = User::newLoginToken();
            $_SESSION['login_token'] = $token;
        }
        $_SESSION['login_back'] = $back;
        $this->view('login', [
            'title' => Lang::t('login_title'),
            'link' => "https://t.me/$bot?start=login_$token",
            'bot' => $bot,
        ]);
    }

    // The login page asks this every few seconds until the bot confirmed the token.
    public function check(): void
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        $token = (string) ($_SESSION['login_token'] ?? '');
        $row = $token !== '' ? User::loginToken($token) : null;
        if (!$row) {
            echo json_encode(['status' => 'expired']);
            return;
        }
        if (!$row['user_id']) {
            echo json_encode(['status' => 'waiting']);
            return;
        }
        User::deleteLoginToken($token);
        $back = $this->safeBack((string) ($_SESSION['login_back'] ?? '/'));
        unset($_SESSION['login_token'], $_SESSION['login_back']);
        Buyer::login((int) $row['user_id']);
        // First login: open the profile, then go on to where they were.
        if (!User::profileDone(User::find((int) $row['user_id']))) {
            $back = '/profile' . ($back !== '/' ? '?back=' . urlencode($back) : '');
        }
        echo json_encode(['status' => 'ok', 'redirect' => $back]);
    }

    public function logout(): void
    {
        $this->checkCsrf();
        Buyer::logout();
        $this->redirect('/');
    }

    // Only paths on this site, never another website.
    private function safeBack(string $back): string
    {
        return preg_match('#^/(?!/)[^\s\\\\]*$#', $back) ? $back : '/';
    }
}
