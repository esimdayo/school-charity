const path = require('node:path');
const fs = require('node:fs');
const crypto = require('node:crypto');
const express = require('express');
const multer = require('multer');

const db = require('./db');
const shop = require('./shop');
const payments = require('./payments');
const { layout, esc, money, date, itemCard, statusLabel, orderLabel, RECIPIENT_NAME, maskedCard } = require('./views');

const PORT = Number(process.env.PORT || 3000);
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || '';
const SESSION_SECRET = process.env.SESSION_SECRET || crypto.randomBytes(32).toString('hex');
const uploadDir = process.env.UPLOAD_DIR || path.join(__dirname, '..', 'uploads');
fs.mkdirSync(uploadDir, { recursive: true });

if (!ADMIN_PASSWORD) console.warn('ADMIN_PASSWORD is not set: the admin page is locked until you set it.');

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
for (const p of payments.enabled) app.use(`/payments/${p.name}`, p.router);

const form = express.urlencoded({ extended: false });
const baseUrl = (req) => process.env.BASE_URL?.replace(/\/$/, '') || `${req.protocol}://${req.get('host')}`;

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
  shop.releaseExpired();
  const items = db.prepare("SELECT * FROM items WHERE status = 'available' ORDER BY created_at DESC").all();
  const raised = shop.totalRaised();
  res.send(layout('Shop', `
    <section class="hero">
      <div>
        <h1>Buy something, help someone.</h1>
        <p>Students and teachers donate things they no longer need. Every so'm you pay goes straight to
        ${RECIPIENT_NAME ? `<strong>${esc(RECIPIENT_NAME)}</strong>, who needs our help` : 'a person who needs our help'}.</p>
        <p class="muted">Pay online, then pick up your item from the school charity room.</p>
      </div>
      <div class="raised">
        <span class="muted">Raised so far</span>
        <strong>${money(raised.total)}</strong>
        <span class="muted">${raised.count} item${raised.count === 1 ? '' : 's'} sold</span>
      </div>
    </section>
    ${items.length
      ? `<div class="grid">${items.map(itemCard).join('')}</div>`
      : `<div class="empty"><p>Nothing for sale right now.</p><a class="btn" href="/donate">Donate the first item</a></div>`}
  `, { admin: isAdmin(req) }));
});

app.get('/item/:id', (req, res) => {
  shop.releaseExpired();
  const item = shop.getItem(Number(req.params.id));
  if (!item || !['available', 'reserved', 'sold'].includes(item.status)) {
    return res.status(404).send(layout('Not found', '<p>This item does not exist.</p>'));
  }
  const choice = payments.enabled.length > 1
    ? `<fieldset><legend>Pay with</legend>${payments.enabled.map((p, i) => `
        <label class="radio"><input type="radio" name="provider" value="${p.name}" ${i === 0 ? 'checked' : ''}> ${esc(p.label)}</label>`).join('')}
       </fieldset>`
    : `<input type="hidden" name="provider" value="${payments.enabled[0].name}">`;
  const buy = item.status === 'available'
    ? `<form method="post" action="/item/${item.id}/buy" class="stack">
        <label>Your name <input name="buyer_name" required maxlength="80" autocomplete="name"></label>
        <label>Phone number <input name="buyer_phone" required maxlength="20" placeholder="+998 90 123 45 67" autocomplete="tel"></label>
        ${choice}
        <button class="btn big">Buy for ${money(item.price)}</button>
        <p class="muted small">After payment you get a pickup code. Show it in the school charity room to collect your item.</p>
      </form>`
    : `<p class="badge">${item.status === 'sold' ? 'Sold. Thank you to the buyer!' : 'Someone is paying for this right now.'}</p>`;
  res.send(layout(item.title, `
    <a class="muted" href="/">← Back to shop</a>
    <div class="detail">
      <img src="/uploads/${esc(item.photo)}" alt="${esc(item.title)}">
      <div>
        <h1>${esc(item.title)}</h1>
        <p class="price big">${money(item.price)}</p>
        ${item.description ? `<p>${esc(item.description).replace(/\n/g, '<br>')}</p>` : ''}
        <p class="muted">Donated by ${esc(item.donor_name)} (${esc(item.donor_role)}${item.donor_class ? `, ${esc(item.donor_class)}` : ''})</p>
        ${buy}
      </div>
    </div>`, { admin: isAdmin(req) }));
});

app.post('/item/:id/buy', form, (req, res) => {
  const buyerName = String(req.body.buyer_name || '').trim().slice(0, 80);
  const buyerPhone = String(req.body.buyer_phone || '').trim().slice(0, 20);
  const provider = payments.get(String(req.body.provider || ''));
  if (!buyerName || !/^[+\d\s()-]{7,20}$/.test(buyerPhone) || !provider) {
    return res.status(400).send(layout('Check your details', `<p>Please enter your name and a valid phone number.</p>
      <a class="btn" href="/item/${Number(req.params.id)}">Go back</a>`));
  }
  const order = shop.createOrder(Number(req.params.id), { buyerName, buyerPhone, provider: provider.name });
  if (!order) {
    return res.status(409).send(layout('Not available', `<p>Sorry, this item was just bought or reserved by someone else.</p>
      <a class="btn" href="/">See other items</a>`));
  }
  res.redirect(provider.checkoutUrl(order, baseUrl(req)));
});

app.get('/order/:token', (req, res) => {
  const order = shop.getOrderByToken(req.params.token);
  if (!order) return res.status(404).send(layout('Not found', '<p>Order not found.</p>'));
  const item = shop.getItem(order.item_id);
  let body;
  if (order.status === 'paid' || order.status === 'picked_up') {
    body = `
      <p class="badge ok">${order.status === 'paid' ? 'Payment received. Thank you!' : 'Picked up. Thank you!'}</p>
      <h1>${esc(item.title)}</h1>
      <p>Your pickup code</p>
      <div class="code">${esc(order.pickup_code)}</div>
      <p>Show this code in the <strong>school charity room</strong> to collect your item.
      Save this page or take a screenshot.</p>
      <p class="muted small">Paid ${money(order.amount)} on ${date(order.paid_at)}</p>`;
  } else if (order.status === 'pending') {
    body = `
      <meta http-equiv="refresh" content="5">
      <h1>Waiting for your payment…</h1>
      <p>This page refreshes by itself. If you already paid, it will update in a moment.</p>
      <p><a class="btn" href="${esc(payments.get(order.provider)?.checkoutUrl(order, baseUrl(req)) || '/')}">Open payment again</a></p>`;
  } else {
    body = `<h1>Order ${esc(orderLabel[order.status].toLowerCase())}</h1>
      <p>No money was taken for this order${order.status === 'refunded' ? ' (it was refunded)' : ''}.</p>
      <a class="btn" href="/">Back to shop</a>`;
  }
  res.send(layout('Your order', `<div class="card narrow center">${body}</div>`));
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

function donateForm(error = '', v = {}) {
  return layout('Donate an item', `
    <div class="card narrow">
      <h1>Donate an item</h1>
      <p class="muted">Take a photo, name a price and bring the item to the school charity room.
      A teacher checks every item before it appears in the shop.</p>
      ${error ? `<p class="badge warn">${esc(error)}</p>` : ''}
      <form method="post" action="/donate" enctype="multipart/form-data" class="stack">
        <label>Photo <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="environment" required></label>
        <label>What is it? <input name="title" required maxlength="80" value="${esc(v.title)}" placeholder="e.g. Blue school backpack"></label>
        <label>Description <textarea name="description" maxlength="1000" rows="3" placeholder="Size, condition, anything useful">${esc(v.description)}</textarea></label>
        <label>Price (so'm) <input name="price" type="number" min="1000" step="500" required value="${esc(v.price)}" placeholder="20000"></label>
        <label>Your name <input name="donor_name" required maxlength="80" value="${esc(v.donor_name)}"></label>
        <div class="row">
          <label>I am a
            <select name="donor_role">
              ${['student', 'teacher', 'parent', 'other'].map((r) => `<option ${v.donor_role === r ? 'selected' : ''}>${r}</option>`).join('')}
            </select>
          </label>
          <label>Class (optional) <input name="donor_class" maxlength="20" value="${esc(v.donor_class)}" placeholder="9B"></label>
        </div>
        <button class="btn big">Send for approval</button>
      </form>
    </div>`);
}

app.get('/donate', (req, res) => res.send(donateForm()));

app.post('/donate', (req, res) => {
  upload.single('photo')(req, res, (err) => {
    const v = req.body || {};
    const fail = (msg) => {
      if (req.file) fs.unlink(req.file.path, () => {});
      res.status(400).send(donateForm(msg, v));
    };
    if (err) return fail(err.code === 'LIMIT_FILE_SIZE' ? 'The photo is too big (max 8 MB).' : 'Could not upload the photo.');
    if (!req.file) return fail('Please add a photo (JPG, PNG or WEBP).');
    const title = String(v.title || '').trim().slice(0, 80);
    const donorName = String(v.donor_name || '').trim().slice(0, 80);
    const price = Math.round(Number(v.price));
    const role = ['student', 'teacher', 'parent', 'other'].includes(v.donor_role) ? v.donor_role : 'other';
    if (!title || !donorName) return fail('Please fill in the item name and your name.');
    if (!Number.isFinite(price) || price < 1000 || price > 100_000_000) return fail("Price must be at least 1 000 so'm.");
    db.prepare(`INSERT INTO items (title, description, price, photo, donor_name, donor_role, donor_class, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)`)
      .run(title, String(v.description || '').trim().slice(0, 1000), price, req.file.filename, donorName, role,
        String(v.donor_class || '').trim().slice(0, 20), Date.now());
    res.send(layout('Thank you', `<div class="card narrow center">
      <h1>Thank you, ${esc(donorName)}!</h1>
      <p>Your item <strong>${esc(title)}</strong> was sent. Please bring it to the school charity room.
      It will appear in the shop after a teacher approves it.</p>
      <a class="btn" href="/donate">Donate another</a> <a class="btn ghost" href="/">Back to shop</a>
    </div>`));
  });
});

// ---------- Admin ----------

app.get('/admin/login', (req, res) => {
  res.send(layout('Admin login', `<div class="card narrow">
    <h1>Admin login</h1>
    ${req.query.error ? '<p class="badge warn">Wrong password.</p>' : ''}
    ${ADMIN_PASSWORD ? '' : '<p class="badge warn">Set ADMIN_PASSWORD in the .env file to enable the admin page.</p>'}
    <form method="post" action="/admin/login" class="stack">
      <label>Password <input type="password" name="password" required autofocus></label>
      <button class="btn">Log in</button>
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
  shop.releaseExpired();
  const pending = db.prepare("SELECT * FROM items WHERE status = 'pending' ORDER BY created_at").all();
  const items = db.prepare("SELECT * FROM items WHERE status != 'pending' ORDER BY created_at DESC LIMIT 200").all();
  const orders = db.prepare(`SELECT o.*, i.title FROM orders o JOIN items i ON i.id = o.item_id
                             WHERE o.status != 'cancelled' ORDER BY o.created_at DESC LIMIT 200`).all();
  const raised = shop.totalRaised();
  const toPickUp = orders.filter((o) => o.status === 'paid').length;
  const providerNames = payments.enabled.map((p) => p.label).join(', ');

  res.send(layout('Admin', `
    <div class="admin-head">
      <h1>Admin</h1>
      <form method="post" action="/admin/logout"><button class="btn ghost small">Log out</button></form>
    </div>
    ${payments.get('test') ? '<p class="badge warn">Test payments are switched on. Turn them off before going live (PAYMENT_PROVIDERS in .env).</p>' : ''}
    <div class="stats">
      <div class="card"><span class="muted">Raised</span><strong>${money(raised.total)}</strong></div>
      <div class="card"><span class="muted">Waiting for approval</span><strong>${pending.length}</strong></div>
      <div class="card"><span class="muted">Waiting for pickup</span><strong>${toPickUp}</strong></div>
      <div class="card"><span class="muted">Money goes to</span><strong class="small">${esc(RECIPIENT_NAME || 'not set')} ${esc(maskedCard())}</strong><span class="muted small">via ${esc(providerNames)}</span></div>
    </div>

    <section class="card">
      <h2>Hand over an item</h2>
      <form method="get" action="/admin/pickup" class="inline">
        <input name="code" placeholder="Pickup code, e.g. K7M2QX" required maxlength="6" style="text-transform:uppercase">
        <button class="btn">Find</button>
      </form>
    </section>

    <section>
      <h2>Waiting for approval (${pending.length})</h2>
      ${pending.length ? `<div class="grid">${pending.map((i) => `
        <div class="item">
          <img src="/uploads/${esc(i.photo)}" alt="">
          <div class="item-body">
            <h3>${esc(i.title)}</h3>
            <p class="price">${money(i.price)}</p>
            <p class="muted small">${esc(i.donor_name)} · ${esc(i.donor_role)} ${esc(i.donor_class)}</p>
            ${i.description ? `<p class="small">${esc(i.description)}</p>` : ''}
            <form method="post" class="inline">
              <button class="btn small" formaction="/admin/items/${i.id}/approve">Approve</button>
              <button class="btn ghost small" formaction="/admin/items/${i.id}/reject">Reject</button>
            </form>
          </div>
        </div>`).join('')}</div>` : '<p class="muted">Nothing to check.</p>'}
    </section>

    <section>
      <h2>Orders</h2>
      <div class="table-wrap"><table>
        <tr><th>Item</th><th>Buyer</th><th>Amount</th><th>Status</th><th>Code</th><th>Date</th></tr>
        ${orders.map((o) => `<tr>
          <td>${esc(o.title)}</td><td>${esc(o.buyer_name)}<br><span class="muted small">${esc(o.buyer_phone)}</span></td>
          <td>${money(o.amount)}</td><td>${esc(orderLabel[o.status])}<br><span class="muted small">${esc(o.provider)}</span></td>
          <td>${o.pickup_code ? `<a href="/admin/pickup?code=${esc(o.pickup_code)}">${esc(o.pickup_code)}</a>` : ''}</td>
          <td class="small">${date(o.created_at)}</td></tr>`).join('') || '<tr><td colspan="6" class="muted">No orders yet.</td></tr>'}
      </table></div>
    </section>

    <section>
      <h2>All items</h2>
      <div class="table-wrap"><table>
        <tr><th></th><th>Item</th><th>Price</th><th>Status</th><th></th></tr>
        ${items.map((i) => `<tr>
          <td><img class="thumb" src="/uploads/${esc(i.photo)}" alt=""></td>
          <td><a href="/item/${i.id}">${esc(i.title)}</a><br><span class="muted small">${esc(i.donor_name)}</span></td>
          <td>${money(i.price)}</td><td>${esc(statusLabel[i.status])}</td>
          <td>${i.status === 'available' ? `<form method="post" action="/admin/items/${i.id}/reject"><button class="btn ghost small">Remove</button></form>` : ''}
              ${i.status === 'rejected' ? `<form method="post" action="/admin/items/${i.id}/approve"><button class="btn ghost small">Put back</button></form>` : ''}</td>
        </tr>`).join('') || '<tr><td colspan="5" class="muted">No items yet.</td></tr>'}
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

app.get('/admin/pickup', requireAdmin, (req, res) => {
  const code = String(req.query.code || '').trim().toUpperCase();
  const order = db.prepare('SELECT * FROM orders WHERE pickup_code = ?').get(code);
  let body;
  if (!order) {
    body = `<p class="badge warn">No paid order with code ${esc(code)}.</p>`;
  } else {
    const item = shop.getItem(order.item_id);
    body = `
      <img class="pickup-photo" src="/uploads/${esc(item.photo)}" alt="">
      <h2>${esc(item.title)}</h2>
      <p>Buyer: <strong>${esc(order.buyer_name)}</strong> · ${esc(order.buyer_phone)}</p>
      <p>Paid ${money(order.amount)} on ${date(order.paid_at)}</p>
      ${order.status === 'paid'
        ? `<form method="post" action="/admin/orders/${order.id}/pickup"><button class="btn big">Mark as handed over</button></form>`
        : `<p class="badge ${order.status === 'picked_up' ? 'ok' : 'warn'}">${esc(orderLabel[order.status])}${order.picked_up_at ? ` on ${date(order.picked_up_at)}` : ''}</p>`}`;
  }
  res.send(layout('Pickup', `<a class="muted" href="/admin">← Admin</a>
    <div class="card narrow center"><p class="muted">Code</p><div class="code">${esc(code)}</div>${body}</div>`, { admin: true }));
});

app.post('/admin/orders/:id/pickup', requireAdmin, (req, res) => {
  const order = shop.markPickedUp(Number(req.params.id));
  res.redirect(`/admin/pickup?code=${encodeURIComponent(order?.pickup_code || '')}`);
});

app.listen(PORT, () => {
  console.log(`Charity shop running on http://localhost:${PORT}`);
  console.log(`Payment providers: ${payments.enabled.map((p) => p.name).join(', ')}`);
});
