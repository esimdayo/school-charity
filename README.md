# School Charity Shop

A small website where students and teachers donate things, people buy them online,
and all the money goes to one person in need. Buyers collect their item from the
school charity room with a pickup code.

## How it works

1. **Donate**: a student or teacher opens *Donate an item*, takes a photo, writes a
   name and price, and brings the item to the charity room.
2. **Approve**: a teacher logs in to `/admin` and approves the item. Only approved
   items appear in the shop.
3. **Buy**: a buyer opens the item, enters name and phone, and sees the card number
   and the exact amount. They send the money from their own bank app (Payme, Click,
   Uzum, ...) and press *I've sent the money*, giving the last 4 digits of their card.
   The item is held for 30 minutes while they pay, so two people can't buy it.
4. **Confirm**: the teacher checks the bank app for the card. On the admin page,
   under *Check payments*, they press *Money arrived* (or *Not received*).
5. **Pick up**: after the payment is confirmed the buyer's page shows a 6-letter
   pickup code. In the charity room the teacher types the code on the admin page,
   checks the photo, and presses *Mark as handed over*.

The site is in **Uzbek, Russian and English**. Visitors switch language at the top
of the page; the first visit uses the browser's language (Uzbek by default).
All texts are in `src/i18n.js`.

The home page shows how much money has been raised.

## Run it on your computer

You need [Node.js](https://nodejs.org) 22.13 or newer.

```bash
npm install
cp .env.example .env     # then edit .env: at least ADMIN_PASSWORD and SESSION_SECRET
npm start
```

Open http://localhost:3000. The admin page is http://localhost:3000/admin.

To try the whole flow without sending real money, set `PAYMENT_PROVIDERS=card,test`.
"Test" adds a fake payment button. Remove it before going live.

## Put it on a server

Any small VPS (1 GB RAM is plenty) works.

```bash
# on the server
git clone <your repo> charity-shop && cd charity-shop
npm install --omit=dev
cp .env.example .env && nano .env
npm start
```

Or with Docker:

```bash
docker build -t charity-shop .
docker run -d --name charity-shop --restart unless-stopped -p 3000:3000 \
  --env-file .env -v charity-data:/app/data -v charity-uploads:/app/uploads charity-shop
```

Put it behind HTTPS (for example [Caddy](https://caddyserver.com): `caddy reverse-proxy --from shop.yourschool.uz --to :3000`).
Payme and Click require HTTPS for their callbacks. Set `BASE_URL` in `.env` to your
public address.

**Back up** the `data/` folder (the database) and `uploads/` folder (photos).

## Payments

**Card transfer (default, `PAYMENT_PROVIDERS=card`)**: buyers send money straight
to the card in `RECIPIENT_CARD`. No contract with a payment company is needed. The
teacher who has access to that card's bank app (or SMS notifications) confirms each
payment on the admin page. Only after that does the buyer get the pickup code.

**Automatic payments (optional)**: Payme and Click can confirm payments
automatically, but they pay out to the bank account of a registered business (the
*merchant*), not to a personal card. If the school or a charity fund registers with
**Payme Business** (https://business.payme.uz) or **Click** (https://click.uz/en/business),
you can turn these on as well, e.g. `PAYMENT_PROVIDERS=card,payme`.

### Payme setup

In the Payme merchant cabinet:
- Endpoint URL: `https://YOUR-SITE/payments/payme`
- Account field: `order_id`

In `.env`:
```
PAYMENT_PROVIDERS=payme
PAYME_MERCHANT_ID=...      # from the cabinet
PAYME_KEY=...              # test key first, then the real key
PAYME_TEST_MODE=true       # set to false when going live
```
Run Payme's sandbox tests (https://test.paycom.uz) before switching to live.

### Click setup

In the Click merchant cabinet:
- Prepare URL: `https://YOUR-SITE/payments/click/prepare`
- Complete URL: `https://YOUR-SITE/payments/click/complete`

In `.env`:
```
PAYMENT_PROVIDERS=click        # or payme,click to offer both
CLICK_SERVICE_ID=...
CLICK_MERCHANT_ID=...
CLICK_SECRET_KEY=...
```

**Never leave `test` in `PAYMENT_PROVIDERS` on the live site**, otherwise anyone could
"pay" with the fake payment page. The admin page shows a warning while it's on.

## Project layout

```
src/server.js          pages and routes
src/shop.js            order rules: reserve, paid, cancel, picked up
src/db.js              SQLite database (built into Node, no setup needed)
src/views.js           shared page layout and language switch
src/i18n.js            all texts in Uzbek, Russian and English
src/payments/          card, payme, click and test payments
public/style.css       styles
```
