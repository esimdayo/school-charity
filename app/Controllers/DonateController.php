<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Lang};
use App\Models\Item;

final class DonateController extends Controller
{
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const MAX_BYTES = 8 * 1024 * 1024;

    public function form(): void
    {
        $this->view('donate/form', ['title' => Lang::t('donate_title'), 'error' => '', 'old' => []]);
    }

    public function store()
    {
        $this->checkCsrf();
        $old = $_POST;
        $fail = fn (string $key) => $this->view('donate/form', ['title' => Lang::t('donate_title'), 'error' => Lang::t($key), 'old' => $old], 400);

        $file = $_FILES['photo'] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) return $fail('photo_missing');
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file['size'] > self::MAX_BYTES) return $fail('photo_too_big');
        if ($file['error'] !== UPLOAD_ERR_OK) return $fail('photo_failed');
        // Check the real file type, not the name the browser sent.
        $type = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::TYPES[$type])) return $fail('photo_missing');

        $title = $this->input('title', 80);
        $donor = $this->input('donor_name', 80);
        $price = (int) round((float) ($_POST['price'] ?? 0));
        $role = in_array($_POST['donor_role'] ?? '', Item::ROLES, true) ? $_POST['donor_role'] : 'other';
        if ($title === '' || $donor === '') return $fail('fill_names');
        if ($price < 1000 || $price > 100_000_000) return $fail('price_min');

        $photo = bin2hex(random_bytes(12)) . '.' . self::TYPES[$type];
        if (!move_uploaded_file($file['tmp_name'], ROOT . '/public/uploads/' . $photo)) return $fail('photo_failed');

        Item::create([
            'title' => $title,
            'description' => $this->input('description', 1000),
            'price' => $price,
            'photo' => $photo,
            'donor_name' => $donor,
            'donor_role' => $role,
            'donor_class' => $this->input('donor_class', 20),
        ]);
        $this->view('donate/thanks', ['title' => Lang::t('donate_title'), 'donor' => $donor, 'itemTitle' => $title]);
    }
}
