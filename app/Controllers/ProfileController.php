<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Buyer, Controller, Lang};
use App\Models\{Item, User};

// The profile of someone who logged in with Telegram: real name, who they are
// (student, teacher...) and class. Opens by itself after the first login.
final class ProfileController extends Controller
{
    public function form(): void
    {
        $user = Buyer::current();
        if (!$user) $this->redirect('/login?back=' . urlencode('/profile'));
        $this->show($user, '', isset($_GET['saved']));
    }

    public function save()
    {
        $this->checkCsrf();
        $user = Buyer::current();
        if (!$user) $this->redirect('/login?back=' . urlencode('/profile'));
        $first = $this->input('first_name', 40);
        $last = $this->input('last_name', 40);
        $role = (string) ($_POST['role'] ?? '');
        $class = $this->input('class_name', 20);
        $old = ['first_name' => $first, 'last_name' => $last, 'role' => $role, 'class_name' => $class] + $user;
        if ($first === '' || $last === '') return $this->show($old, 'profile_fill_names');
        if (!in_array($role, Item::ROLES, true)) return $this->show($old, 'profile_choose_role');
        if ($role === 'student' && $class === '') return $this->show($old, 'profile_need_class');
        User::saveProfile((int) $user['id'], $first, $last, $role, $class);
        $back = self::safeBack((string) ($_POST['back'] ?? ''));
        $this->redirect($back !== '' ? $back : '/profile?saved=1');
    }

    private function show(array $user, string $error, bool $saved = false): void
    {
        $this->view('profile', [
            'title' => Lang::t('profile_title'),
            'user' => $user,
            'error' => $error !== '' ? Lang::t($error) : '',
            'saved' => $saved,
            'back' => self::safeBack((string) ($_GET['back'] ?? $_POST['back'] ?? '')),
        ], $error !== '' ? 400 : 200);
    }

    // Only paths on this site, never another website.
    public static function safeBack(string $back): string
    {
        return preg_match('#^/(?!/)[^\s\\\\]*$#', $back) ? $back : '';
    }
}
