<?php
// Copy this file to config.php and fill it in.
return [
    'site_name' => 'School Charity Shop',

    // The person in need. Buyers send money to this card. The full number is
    // shown only on the payment page, everywhere else just the last 4 digits.
    'recipient_name' => 'Name of the person in need',
    'recipient_card' => '1111 1111 2222 2222',

    // Password for the admin page (the teacher who approves items and payments)
    'admin_password' => 'change-me',

    // Public address of the site, without a trailing slash, e.g. https://shop.school.uz
    'base_url' => 'http://localhost:8000',

    // Database. SQLite needs no setup; the file is created automatically.
    // For MySQL: 'dsn' => 'mysql:host=localhost;dbname=charity;charset=utf8mb4'
    // and import database/schema.mysql.sql once.
    'db' => [
        'dsn' => 'sqlite:' . __DIR__ . '/storage/shop.db',
        'user' => '',
        'password' => '',
    ],

    // Payment methods: 'card' (transfer to recipient_card, a teacher confirms it)
    // and 'test' (fake payment button for trying the site; never on the live site!)
    'payments' => ['card'],

    // Minutes an unpaid order keeps an item reserved
    'reserve_minutes' => 30,

    'timezone' => 'Asia/Tashkent',
    'default_lang' => 'uz', // uz, ru or en

    // Telegram bot (optional): admins get every card payment in Telegram with
    // "Money arrived" / "Not received" buttons. See README.
    'telegram' => [
        'bot_token' => '',
        'admin_ids' => [], // e.g. [123456789, 987654321]
        'lang' => 'uz',
    ],
];
