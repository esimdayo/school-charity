<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Lang, Upload};
use App\Models\Item;
use App\Services\Telegram;

final class DonateController extends Controller
{
    public function form(): void
    {
        $this->view('donate/form', ['title' => Lang::t('donate_title'), 'error' => '', 'old' => []]);
    }

    public function store()
    {
        $this->checkCsrf();
        $old = $_POST;
        $fail = fn (string $key) => $this->view('donate/form', ['title' => Lang::t('donate_title'), 'error' => Lang::t($key), 'old' => $old], 400);

        $title = $this->input('title', 80);
        $donor = $this->input('donor_name', 80);
        $price = (int) round((float) ($_POST['price'] ?? 0));
        $role = in_array($_POST['donor_role'] ?? '', Item::ROLES, true) ? $_POST['donor_role'] : 'other';
        $category = (string) ($_POST['category'] ?? '');
        if ($title === '' || $donor === '') return $fail('fill_names');
        if (!in_array($category, Item::CATEGORIES, true)) return $fail('choose_category');
        if ($price < 1000 || $price > 100_000_000) return $fail('price_min');

        try {
            $photo = Upload::image($_FILES['photo'] ?? null, ROOT . '/public/uploads');
        } catch (\RuntimeException $e) {
            return $fail($e->getMessage());
        }

        $id = Item::create([
            'title' => $title,
            'description' => $this->input('description', 1000),
            'price' => $price,
            'category' => $category,
            'photo' => $photo,
            'donor_name' => $donor,
            'donor_role' => $role,
            'donor_class' => $this->input('donor_class', 20),
        ]);
        Telegram::notifyDonation($id);
        $this->view('donate/thanks', ['title' => Lang::t('donate_title'), 'donor' => $donor, 'itemTitle' => $title]);
    }
}
