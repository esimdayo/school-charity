const path = require('node:path');
const fs = require('node:fs');
const crypto = require('node:crypto');
const express = require('express');
const multer = require('multer');

const db = require('./db');
const shop = require('./shop');
const payments = require('./payments');
const { LANGS, translator, pickLang } = require('./i18n');
const {
  layout, esc, money, date, itemCard, statusLabel, orderLabel, RECIPIENT_NAME, maskedCard,
} = require('./views');

const PORT = Number(process.env.PORT || 3000);
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || '';
const SESSION_SECRET = process.env.SESSION_SECRET || crypto.randomBytes(32).toString('hex');
const uploadDir = process.env.UPLOAD_DIR || path.join(__dirname, '..', 'uploads');
fs.mkdirSync(uploadDir, { recursive: true });

if (!ADMIN_PASSWORD) console.warn('ADMIN_PASSWORD is not set: the admin page is locked until you set it.');
if (payments.get('card') && !process.env.RECIPIENT_CARD) console.warn('RECIPIENT_CARD is not set: buyers will not see a card number.');

const app = express();
app.set('trust proxy', true);
app.disable('x-powered-by');
app.use((req, res, next) => {
  res.set('X-Content-Type-Options', 'nosniff');
  res.set('X-Frame-Options', 'DENY');
  next();
});
app.use(express.static(path.join(__dirname, '..', 'public')));
app.use('/uploads', express.static(uploadDir, { maxAge: '7d' }));

// Language: chosen with the switcher (cookie), else the browser's language, else Uzbek.
app.use((req, res, next) => {
  req.lang = pickLang(req);
  req.t = translator(req.lang);
  next();
});
app.get('/lang/:lang', (req, res) => {
  const lang = LANGS.includes(req.params.lang) ? req.params.lang : 'uz';
  const back = String(req.query.back || '/');
  res.set('Set-Cookie', `lang=${lang}; Path=/; Max-Age=31536000; SameSite=Lax`);
  res.redirect(back.startsWith('/') && !back.startsWith('//') ? back : '/');
});

for (const p of payments.enabled) app.use(`/payments/${p.name}`, p.router);

const form = express.urlencoded({ extended: false });
const baseUrl = (req) => process.env.BASE_URL?.replace(/\/$/, '') || `${req.protocol}://${req.get('host')}`;
const ROLES = ['student', 'teacher', 'parent', 'other'];

// ---------- Admin login (one shared password, signed cookie) ----------

const sign = (value) => crypto.createHmac('sha256', SESSION_SECRET).update(value).digest('hex');

function readCookie(req, name) {
  const match = (req.get('cookie') || '').split(/;\s*/).find((c) => c.startsWith(`${name}=`));
  return match ? decodeURIComponent(match.slice(name.length + 1)) : '';
}

function isAdmin(req) {
  const [expires, sig] = readCookie(req, 'admin').split('.');
  if (!expires || !sig || Number(expires) < Date.now()) return false;
  const expected = sign(`admin.${expires}`);
  return sig.length === expected.length && crypto.timingSafeEqual(Buffer.from(sig), Buffer.from(expected));
}

function requireAdmin(req, res, next) {
  if (isAdmin(req)) return next();
  res.redirect('/admin/login');
}

function safeEqual(a, b) {
  const ha = crypto.createHash('sha256').update(a).digest();
  const hb = crypto.createHash('sha256').update(b).digest();
  return crypto.timingSafeEqual(ha, hb);
}

// ---------- Public pages ----------

app.get('/', (req, res) => {
  const { t } = req;
  shop.releaseExpired();
  const items = db.prepare("SELECT * FROM items WHERE status = 'available' ORDER BY created_at DESC").all();
  const raised = shop.totalRaised();
  const who = RECIPIENT_NAME ? `<strong>${esc(RECIPIENT_NAME)}</strong>` : t('hero_someone');
  res.send(layout(req, t('shop_title'), `
    <section class="hero">
      <div>
        <h1>${t('hero_title')}</h1>
        <p>${t('hero_text', { name: who })}</p>
        <p class="muted">${t('hero_pickup')}</p>
      </div>
      <div class="raised">
        <span class="muted">${t('raised')}</span>
        <strong>${money(raised.total, t)}</strong>
        <span class="muted">${t('items_sold', { n: raised.count })}</span>
      </div>
    </section>
    ${items.length
      ? `<div class="grid">${items.map((i) => itemCard(i, t)).join('')}</div>`
      : `<div class="empty"><p>${t('empty_shop')}</p><a class="btn" href="/donate">${t('donate_first')}</a></div>`}
  `, { admin: isAdmin(req) }));
});

app.get('/item/:id', (req, res) => {
  const { t } = req;
  shop.releaseExpired();
  const item = shop.getItem(Number(req.params.id));
  if (!item || !['available', 'reserved', 'sold'].includes(item.status)) {
    return res.status(404).send(layout(req, t('not_found'), `<p>${t('item_not_found')}</p>`));
  }
  const choice = payments.enabled.length > 1
    ? `<fieldset><legend>${t('pay_with')}</legend>${payments.enabled.map((p, i) => `
        <label class="radio"><input type="radio" name="provider" value="${p.name}" ${i === 0 ? 'checked' : ''}> ${t(`provider_${p.name}`)}</label>`).join('')}
       </fieldset>`
    : `<input type="hidden" name="provider" value="${payments.enabled[0].name}">`;
  const buy = item.status === 'available'
    ? `<form method="post" action="/item/${item.id}/buy" class="stack">
        <label>${t('your_name')} <input name="buyer_name" required maxlength="80" autocomplete="name"></label>
        <label>${t('phone')} <input name="buyer_phone" required maxlength="20" placeholder="+998 90 123 45 67" autocomplete="tel" inputmode="tel"></label>
        ${choice}
        <button class="btn big">${t('buy_for', { price: money(item.price, t) })}</button>
        <p class="muted small">${t('buy_note')}</p>
      </form>`
    : `<p class="badge">${item.status === 'sold' ? t('sold_note') : t('reserved_note')}</p>`;
  const donor = `${esc(item.donor_name)} (${t(`role_${item.donor_role}`)}${item.donor_class ? `, ${esc(item.donor_class)}` : ''})`;
  res.send(layout(req, item.title, `
    <a class="muted" href="/">${t('back_to_shop')}</a>
    <div class="detail">
      <img src="/uploads/${esc(item.photo)}" alt="${esc(item.title)}">
      <div>
        <h1>${esc(item.title)}</h1>
        <p class="price big">${money(item.price, t)}</p>
        ${item.description ? `<p>${esc(item.description).replace(/\n/g, '<br>')}</p>` : ''}
        <p class="muted">${t('donated_by', { name: donor })}</p>
        ${buy}
      </div>
    </div>`, { admin: isAdmin(req) }));
});

app.post('/item/:id/buy', form, (req, res) => {
  const { t } = req;
  const buyerName = String(req.body.buyer_name || '').trim().slice(0, 80);
  const buyerPhone = String(req.body.buyer_phone || '').trim().slice(0, 20);
  const provider = payments.get(String(req.body.provider || ''));
  if (!buyerName || !/^[+\d\s()-]{7,20}$/.test(buyerPhone) || !provider) {
    return res.status(400).send(layout(req, t('check_details'), `<p>${t('check_details_text')}</p>
      <a class="btn" href="/item/${Number(req.params.id)}">${t('back')}</a>`));
  }
  const order = shop.createOrder(Number(req.params.id), { buyerName, buyerPhone, provider: provider.name });
  if (!order) {
    return res.status(409).send(layout(req, t('not_available'), `<p>${t('not_available_text')}</p>
      <a class="btn" href="/">${t('see_other')}</a>`));
  }
  res.redirect(provider.checkoutUrl(order, baseUrl(req)));
});

app.get('/order/:token', (req, res) => {
  const { t, lang } = req;
  const order = shop.getOrderByToken(req.params.token);
  if (!order) return res.status(404).send(layout(req, t('not_found'), `<p>${t('order_not_found')}</p>`));
  const item = shop.getItem(order.item_id);
  let body;
  if (order.status === 'paid' || order.status === 'picked_up') {
    body = `
      <p class="badge ok">${order.status === 'paid' ? t('paid_badge') : t('picked_badge')}</p>
      <h1>${esc(item.title)}</h1>
      <p>${t('pickup_code')}</p>
      <div class="code">${esc(order.pickup_code)}</div>
      <p>${t('pickup_text')}</p>
      <p class="muted small">${t('paid_on', { amount: money(order.amount, t), date: date(order.paid_at, lang) })}</p>`;
  } else if (order.status === 'awaiting') {
    body = `
      <meta http-equiv="refresh" content="15">
      <p class="badge warn">${t('order_awaiting')}</p>
      <h1>${t('checking_title')}</h1>
      <p><strong>${esc(item.title)}</strong> · ${money(order.amount, t)}</p>
      <p>${t('checking_text')}</p>`;
  } else if (order.status === 'pending') {
    body = `
      <meta http-equiv="refresh" content="5">
      <h1>${t('waiting_title')}</h1>
      <p>${t('waiting_text')}</p>
      <p><a class="btn" href="${esc(payments.get(order.provider)?.checkoutUrl(order, baseUrl(req)) || '/')}">${t('open_payment')}</a></p>`;
  } else if (order.status === 'unpaid') {
    body = `<h1>${t('not_received_title')}</h1><p>${t('not_received_text')}</p>
      <a class="btn" href="/">${t('to_shop')}</a>`;
  } else {
    body = `<h1>${order.status === 'refunded' ? t('order_refunded') : t('order_cancelled')}</h1>
      <p>${t('no_money')}</p>
      <a class="btn" href="/">${t('to_shop')}</a>`;
  }
  res.send(layout(req, t('your_order'), `<div class="card narrow center">${body}</div>`));
});

// ---------- Donating an item ----------

const upload = multer({
  storage: multer.diskStorage({
    destination: uploadDir,
    filename: (req, file, cb) => {
      const ext = { 'image/jpeg': '.jpg', 'image/png': '.png', 'image/webp': '.webp' }[file.mimetype];
      cb(null, crypto.randomBytes(12).toString('hex') + ext);
    },
  }),
  limits: { fileSize: 8 * 1024 * 1024, files: 1 },
  fileFilter: (req, file, cb) => cb(null, ['image/jpeg', 'image/png', 'image/webp'].includes(file.mimetype)),
});

function donateForm(req, error = '', v = {}) {
  const { t } = req;
  return layout(req, t('donate_title'), `
    <div class="card narrow">
      <h1>${t('donate_title')}</h1>
      <p class="muted">${t('donate_intro')}</p>
      ${error ? `<p class="badge warn">${esc(error)}</p>` : ''}
      <form method="post" action="/donate" enctype="multipart/form-data" class="stack">
        <label>${t('photo')} <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="environment" required></label>
        <label>${t('what_is_it')} <input name="title" required maxlength="80" value="${esc(v.title)}" placeholder="${esc(t('what_placeholder'))}"></label>
        <label>${t('description')} <textarea name="description" maxlength="1000" rows="3" placeholder="${esc(t('description_placeholder'))}">${esc(v.description)}</textarea></label>
        <label>${t('price')} <input name="price" type="number" min="1000" step="500" required value="${esc(v.price)}" placeholder="20000"></label>
        <label>${t('your_name')} <input name="donor_name" required maxlength="80" value="${esc(v.donor_name)}"></label>
        <div class="row">
          <label>${t('i_am')}
            <select name="donor_role">
              ${ROLES.map((r) => `<option value="${r}" ${v.donor_role === r ? 'selected' : ''}>${t(`role_${r}`)}</option>`).join('')}
            </select>
          </label>
          <label>${t('class_optional')} <input name="donor_class" maxlength="20" value="${esc(v.donor_class)}" placeholder="9B"></label>
        </div>
        <button class="btn big">${t('send_for_approval')}</button>
      </form>
    </div>`);
}

app.get('/donate', (req, res) => res.send(donateForm(req)));

app.post('/donate', (req, res) => {
  const { t } = req;
  upload.single('photo')(req, res, (err) => {
    const v = req.body || {};
    const fail = (msg) => {
      if (req.file) fs.unlink(req.file.path, () => {});
      res.status(400).send(donateForm(req, msg, v));
    };
    if (err) return fail(err.code === 'LIMIT_FILE_SIZE' ? t('photo_too_big') : t('photo_failed'));
    if (!req.file) return fail(t('photo_missing'));
    const title = String(v.title || '').trim().slice(0, 80);
    const donorName = String(v.donor_name || '').trim().slice(0, 80);
    const price = Math.round(Number(v.price));
    const role = ROLES.includes(v.donor_role) ? v.donor_role : 'other';
    if (!title || !donorName) return fail(t('fill_names'));
    if (!Number.isFinite(price) || price < 1000 || price > 100_000_000) return fail(t('price_min'));
    db.prepare(`INSERT INTO items (title, description, price, photo, donor_name, donor_role, donor_class, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)`)
      .run(title, String(v.description || '').trim().slice(0, 1000), price, req.file.filename, donorName, role,
        String(v.donor_class || '').trim().slice(0, 20), Date.now());
    res.send(layout(req, t('donate_title'), `<div class="card narrow center">
      <h1>${t('thanks_title', { name: esc(donorName) })}</h1>
      <p>${t('thanks_text', { title: esc(title) })}</p>
      <div class="inline center-row">
        <a class="btn" href="/donate">${t('donate_another')}</a>
        <a class="btn ghost" href="/">${t('to_shop')}</a>
      </div>
    </div>`));
  });
});

// ---------- Admin ----------

app.get('/admin/login', (req, res) => {
  const { t } = req;
  res.send(layout(req, t('admin_login'), `<div class="card narrow">
    <h1>${t('admin_login')}</h1>
    ${req.query.error ? `<p class="badge warn">${t('wrong_password')}</p>` : ''}
    ${ADMIN_PASSWORD ? '' : `<p class="badge warn">${t('set_password')}</p>`}
    <form method="post" action="/admin/login" class="stack">
      <label>${t('password')} <input type="password" name="password" required autofocus></label>
      <button class="btn">${t('log_in')}</button>
    </form></div>`));
});

app.post('/admin/login', form, (req, res) => {
  if (!ADMIN_PASSWORD || !safeEqual(String(req.body.password || ''), ADMIN_PASSWORD)) {
    return res.redirect('/admin/login?error=1');
  }
  const expires = Date.now() + 12 * 60 * 60 * 1000;
  const secure = baseUrl(req).startsWith('https') ? '; Secure' : '';
  res.set('Set-Cookie', `admin=${expires}.${sign(`admin.${expires}`)}; Path=/; HttpOnly; SameSite=Lax; Max-Age=43200${secure}`);
  res.redirect('/admin');
});

app.post('/admin/logout', (req, res) => {
  res.set('Set-Cookie', 'admin=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0');
  res.redirect('/');
});

app.get('/admin', requireAdmin, (req, res) => {
  const { t, lang } = req;
  shop.releaseExpired();
  const pending = db.prepare("SELECT * FROM items WHERE status = 'pending' ORDER BY created_at").all();
  const items = db.prepare("SELECT * FROM items WHERE status != 'pending' ORDER BY created_at DESC LIMIT 200").all();
  const orders = db.prepare(`SELECT o.*, i.title, i.photo FROM orders o JOIN items i ON i.id = o.item_id
                             WHERE o.status != 'cancelled' ORDER BY o.created_at DESC LIMIT 200`).all();
  const toCheck = orders.filter((o) => o.status === 'awaiting').reverse();
  const raised = shop.totalRaised();
  const toPickUp = orders.filter((o) => o.status === 'paid').length;
  const providerNames = payments.enabled.map((p) => t(`provider_${p.name}`)).join(', ');

  res.send(layout(req, t('nav_admin'), `
    <div class="admin-head">
      <h1>${t('nav_admin')}</h1>
      <form method="post" action="/admin/logout"><button class="btn ghost small">${t('log_out')}</button></form>
    </div>
    ${payments.get('test') ? `<p class="badge warn">${t('test_warning')}</p>` : ''}
    <div class="stats">
      <div class="card"><span class="muted">${t('stat_raised')}</span><strong>${money(raised.total, t)}</strong></div>
      <div class="card"><span class="muted">${t('stat_check_payments')}</span><strong>${toCheck.length}</strong></div>
      <div class="card"><span class="muted">${t('stat_pending')}</span><strong>${pending.length}</strong></div>
      <div class="card"><span class="muted">${t('stat_pickup')}</span><strong>${toPickUp}</strong></div>
      <div class="card"><span class="muted">${t('stat_money_to')}</span><strong class="small">${esc(RECIPIENT_NAME || t('not_set'))} ${esc(maskedCard())}</strong><span class="muted small">${t('via', { names: esc(providerNames) })}</span></div>
    </div>

    ${payments.get('card') ? `<section class="card">
      <h2>${t('check_payments', { n: toCheck.length })}</h2>
      ${toCheck.length ? `<p class="muted small">${t('check_payments_help')}</p>
      <div class="checks">${toCheck.map((o) => `
        <div class="check">
          <img class="thumb" src="/uploads/${esc(o.photo)}" alt="">
          <div class="check-info">
            <strong class="price">${money(o.amount, t)}</strong>
            <span>${esc(o.title)}</span>
            <span class="muted small">${esc(o.buyer_name)} · ${esc(o.buyer_phone)} · ${t('payer_card_short', { last4: esc(o.payer_card) })} · ${date(o.sent_at, lang)}</span>
          </div>
          <form method="post" class="inline">
            <button class="btn small" formaction="/admin/orders/${o.id}/confirm">${t('money_arrived')}</button>
            <button class="btn ghost small" formaction="/admin/orders/${o.id}/unpaid">${t('not_received')}</button>
          </form>
        </div>`).join('')}</div>` : `<p class="muted">${t('nothing_to_check_payments')}</p>`}
    </section>` : ''}

    <section class="card">
      <h2>${t('hand_over')}</h2>
      <form method="get" action="/admin/pickup" class="inline">
        <input name="code" placeholder="${esc(t('code_placeholder'))}" required maxlength="6" style="text-transform:uppercase">
        <button class="btn">${t('find')}</button>
      </form>
    </section>

    <section>
      <h2>${t('waiting_approval', { n: pending.length })}</h2>
      ${pending.length ? `<div class="grid">${pending.map((i) => `
        <div class="item">
          <img src="/uploads/${esc(i.photo)}" alt="">
          <div class="item-body">
            <h3>${esc(i.title)}</h3>
            <p class="price">${money(i.price, t)}</p>
            <p class="muted small">${esc(i.donor_name)} · ${t(`role_${i.donor_role}`)} ${esc(i.donor_class)}</p>
            ${i.description ? `<p class="small">${esc(i.description)}</p>` : ''}
            <form method="post" class="inline">
              <button class="btn small" formaction="/admin/items/${i.id}/approve">${t('approve')}</button>
              <button class="btn ghost small" formaction="/admin/items/${i.id}/reject">${t('reject')}</button>
            </form>
          </div>
        </div>`).join('')}</div>` : `<p class="muted">${t('nothing_to_check')}</p>`}
    </section>

    <section>
      <h2>${t('orders')}</h2>
      <div class="table-wrap"><table>
        <tr><th>${t('col_item')}</th><th>${t('col_buyer')}</th><th>${t('col_amount')}</th><th>${t('col_status')}</th><th>${t('col_code')}</th><th>${t('col_date')}</th></tr>
        ${orders.map((o) => `<tr>
          <td>${esc(o.title)}</td><td>${esc(o.buyer_name)}<br><span class="muted small">${esc(o.buyer_phone)}</span></td>
          <td>${money(o.amount, t)}</td><td>${esc(orderLabel(t, o.status))}<br><span class="muted small">${t(`provider_${o.provider}`)}${o.payer_card ? ` · •••• ${esc(o.payer_card)}` : ''}</span></td>
          <td>${o.pickup_code ? `<a href="/admin/pickup?code=${esc(o.pickup_code)}">${esc(o.pickup_code)}</a>` : ''}</td>
          <td class="small">${date(o.created_at, lang)}</td></tr>`).join('') || `<tr><td colspan="6" class="muted">${t('no_orders')}</td></tr>`}
      </table></div>
    </section>

    <section>
      <h2>${t('all_items')}</h2>
      <div class="table-wrap"><table>
        <tr><th></th><th>${t('col_item')}</th><th>${t('col_price')}</th><th>${t('col_status')}</th><th></th></tr>
        ${items.map((i) => `<tr>
          <td><img class="thumb" src="/uploads/${esc(i.photo)}" alt=""></td>
          <td><a href="/item/${i.id}">${esc(i.title)}</a><br><span class="muted small">${esc(i.donor_name)}</span></td>
          <td>${money(i.price, t)}</td><td>${esc(statusLabel(t, i.status))}</td>
          <td>${i.status === 'available' ? `<form method="post" action="/admin/items/${i.id}/reject"><button class="btn ghost small">${t('remove')}</button></form>` : ''}
              ${i.status === 'rejected' ? `<form method="post" action="/admin/items/${i.id}/approve"><button class="btn ghost small">${t('put_back')}</button></form>` : ''}</td>
        </tr>`).join('') || `<tr><td colspan="5" class="muted">${t('no_items')}</td></tr>`}
      </table></div>
    </section>
  `, { admin: true }));
});

app.post('/admin/items/:id/:action(approve|reject)', requireAdmin, (req, res) => {
  const status = req.params.action === 'approve' ? 'available' : 'rejected';
  db.prepare("UPDATE items SET status = ? WHERE id = ? AND status IN ('pending', 'available', 'rejected')")
    .run(status, Number(req.params.id));
  res.redirect('/admin');
});

// A teacher saw the card transfer arrive (or not) in the bank app.
app.post('/admin/orders/:id/confirm', requireAdmin, (req, res) => {
  const order = shop.getOrder(Number(req.params.id));
  if (order?.status === 'awaiting') shop.markPaid(order.id, `card-${order.payer_card}`);
  res.redirect('/admin');
});

app.post('/admin/orders/:id/unpaid', requireAdmin, (req, res) => {
  const order = shop.getOrder(Number(req.params.id));
  if (order?.status === 'awaiting') shop.cancelOrder(order.id, 'unpaid');
  res.redirect('/admin');
});

app.get('/admin/pickup', requireAdmin, (req, res) => {
  const { t, lang } = req;
  const code = String(req.query.code || '').trim().toUpperCase();
  const order = db.prepare('SELECT * FROM orders WHERE pickup_code = ?').get(code);
  let body;
  if (!order) {
    body = `<p class="badge warn">${t('no_order_code', { code: esc(code) })}</p>`;
  } else {
    const item = shop.getItem(order.item_id);
    body = `
      <img class="pickup-photo" src="/uploads/${esc(item.photo)}" alt="">
      <h2>${esc(item.title)}</h2>
      <p>${t('buyer')}: <strong>${esc(order.buyer_name)}</strong> · ${esc(order.buyer_phone)}</p>
      <p>${t('paid_on', { amount: money(order.amount, t), date: date(order.paid_at, lang) })}</p>
      ${order.status === 'paid'
        ? `<form method="post" action="/admin/orders/${order.id}/pickup"><button class="btn big">${t('mark_handed')}</button></form>`
        : `<p class="badge ${order.status === 'picked_up' ? 'ok' : 'warn'}">${esc(orderLabel(t, order.status))}${order.picked_up_at ? ` ${t('on_date', { date: date(order.picked_up_at, lang) })}` : ''}</p>`}`;
  }
  res.send(layout(req, t('pickup_title'), `<a class="muted" href="/admin">${t('back_admin')}</a>
    <div class="card narrow center"><p class="muted">${t('code')}</p><div class="code">${esc(code)}</div>${body}</div>`, { admin: true }));
});

app.post('/admin/orders/:id/pickup', requireAdmin, (req, res) => {
  const order = shop.markPickedUp(Number(req.params.id));
  res.redirect(`/admin/pickup?code=${encodeURIComponent(order?.pickup_code || '')}`);
});

app.listen(PORT, () => {
  console.log(`Charity shop running on http://localhost:${PORT}`);
  console.log(`Payment providers: ${payments.enabled.map((p) => p.name).join(', ')}`);
});
