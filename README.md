# School Charity Shop

A small website where students and teachers donate things, people buy them, and
all the money goes to one person in need by card transfer. Buyers collect their
item from the school charity room with a pickup code.

Written in plain PHP with an MVC structure. No framework and no Composer packages,
so it runs on ordinary shared hosting.

## How it works

1. **Donate**: a student or teacher opens *Donate an item*, takes a photo, writes a
   name and price, and brings the item to the charity room.
2. **Approve**: a teacher logs in to `/admin` and approves the item. Only approved
   items appear in the shop.
3. **Buy**: the buyer enters their name and phone and sees the card number and the
   exact amount. They send the money from their own bank app (Payme, Click, Uzum, ...)
   and press *I've sent the money*, giving the last 4 digits of their card. The item
   is held for 30 minutes while they pay, so two people can't buy it.
4. **Confirm**: a teacher checks that the money arrived on the card and presses
   *Money arrived* (or *Not received*) on the admin page or in Telegram.
5. **Pick up**: the buyer's page then shows a 6-letter pickup code. In the charity
   room the teacher types the code on the admin page and presses *Mark as handed over*.

Buyers can search items by name and filter by category (books, electronics,
school and office supplies, sports, toys, clothing, other). After a card transfer
they can attach a screenshot of the receipt (optional); admins see it on the admin
page and in Telegram. The admin page can reset the "raised" total to zero, which
keeps all orders and only restarts the count.

On the admin **Settings** page you set a fundraising goal (a progress bar on the
main page) and fill in the page about the person in need: name, age, condition,
health problem and their story. **Statistics** shows money raised per week, sales by
category and the top donors. Visitors can switch to dark mode at the top of the page.

The site is in **Uzbek, Russian and English**; visitors switch at the top of the page.

## Requirements

- PHP 8.1 or newer with the `pdo_sqlite` (or `pdo_mysql`), `curl`, `mbstring` and
  `fileinfo` extensions. Most hosting has these.
- Apache with `mod_rewrite` (the `.htaccess` files are included), or nginx (see below).

## Try it on your computer

```bash
cp config.example.php config.php    # then edit config.php
php -S localhost:8000 -t public public/index.php
```

Open http://localhost:8000. The admin page is http://localhost:8000/admin.
To try buying without sending money, add `'test'` to `payments` in `config.php`.

## Put it on hosting

1. Upload all files.
2. Point the site's folder (document root) at `public/`. If your hosting doesn't
   allow that, upload everything into `public_html`; the root `.htaccess` sends
   visitors into `public/` and blocks the other folders.
3. Copy `config.example.php` to `config.php` and fill it in: recipient name and card,
   admin password, `base_url` (your https address).
4. Make `storage/` and `public/uploads/` writable by PHP.

The site also works inside a folder, e.g. `http://localhost/charity/` in Laragon
or OpenServer: links and redirects follow the folder it runs in.

The database:
- **SQLite** (default): nothing to do, the file `storage/shop.db` is created on the
  first visit.
- **MySQL**: create a database, import `database/schema.mysql.sql` (phpMyAdmin →
  Import), and set `db.dsn`, `db.user`, `db.password` in `config.php`.

**Back up** the database and `public/uploads/` (the photos).

### nginx

```nginx
root /var/www/charity-shop/public;
location / { try_files $uri /index.php?$query_string; }
location ~ ^/index\.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root/index.php; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
location ~ \.php$ { deny all; }
```

## Telegram confirmations

Admins can get Telegram messages with buttons:
- every donated item, with its photo, price and donor, plus **Approve** and
  **Reject** buttons;
- every card payment, with the item photo, amount, buyer and the last 4 digits of
  their card, plus **Money arrived** and **Not received** buttons.

When one admin presses a button (or handles it on the admin page), the message
updates for every admin, so nothing is handled twice.

1. In Telegram, open **@BotFather**, send `/newbot` and follow the steps. Put the
   token in `telegram.bot_token` in `config.php`.
2. The site must be on https. Log in to `/admin` and press **Connect Telegram bot**.
3. Send any message to your bot. It replies with your Telegram ID.
4. Put that ID in `telegram.admin_ids` (e.g. `[123456789]`, several allowed).

Only the IDs in `admin_ids` can press the buttons.

**Site only on your own PC (no https address)?** Skip step 2 and run the bot
script instead, in a window you keep open:

```bash
php bin/telegram-bot.php
```

On Windows with Laragon you can double-click `telegram-bot.bat` instead. It also
restarts the bot if it stops; put a shortcut to it in the Startup folder
(Win + R, `shell:startup`) to start it with Windows.

It fetches button presses and messages from Telegram itself, so no public address
is needed. Use either the script or **Connect Telegram bot**, not both.

## Code layout (MVC)

```
public/index.php          front controller: all routes are listed here
public/style.css          styles
public/uploads/           donated item photos
app/Core/                 small framework: Router, Controller, View, Database, Lang, Auth, Csrf
app/Models/               Item, Order (all database queries)
app/Controllers/          Shop, Donate, Payment, Order, Admin, Telegram, Lang
app/Views/                HTML templates (layout.php wraps every page)
app/Services/Telegram.php Telegram bot
app/lang/                 texts in uz.php, ru.php, en.php
database/                 schema for SQLite and MySQL
config.example.php        settings (copy to config.php)
```

Every form is protected with a CSRF token, uploaded photos are checked by their
real file type, and the uploads folder can't run scripts.
