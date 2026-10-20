const { LANGS, LANG_NAMES } = require('./i18n');

const SITE_NAME = process.env.SITE_NAME || 'School Charity Shop';
const RECIPIENT_NAME = process.env.RECIPIENT_NAME || '';
const RECIPIENT_CARD = process.env.RECIPIENT_CARD || '';

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[c]));

const money = (n, t) => `${Number(n).toLocaleString('en-US').replace(/,/g, ' ')} ${t ? t('currency') : "so'm"}`;

// Uzbek and Russian use 20.10.2026, 14:24 (browsers' Uzbek month names are unreliable).
const TZ = process.env.TZ || 'Asia/Tashkent';
function date(ms, lang = 'en') {
  if (!ms) return '';
  if (lang === 'en') return new Date(ms).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short', timeZone: TZ });
  const p = Object.fromEntries(new Intl.DateTimeFormat('en-GB', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: TZ,
  }).formatToParts(new Date(ms)).map((x) => [x.type, x.value]));
  return `${p.day}.${p.month}.${p.year}, ${p.hour}:${p.minute}`;
}

// Card number grouped in fours, e.g. "1111 1111 2222 2222".
const fullCard = () => RECIPIENT_CARD.replace(/\D/g, '').replace(/(\d{4})(?=\d)/g, '$1 ');
// Elsewhere on the site only the last 4 digits are shown.
const maskedCard = () => (RECIPIENT_CARD ? `•••• ${RECIPIENT_CARD.replace(/\D/g, '').slice(-4)}` : '');

// req.t and req.lang are set by the language middleware in server.js.
function layout(req, title, body, { admin = false } = {}) {
  const { t, lang } = req;
  const back = encodeURIComponent(req.originalUrl || '/');
  const switcher = LANGS.map((l) => (l === lang
    ? `<strong>${LANG_NAMES[l]}</strong>`
    : `<a href="/lang/${l}?back=${back}" lang="${l}">${LANG_NAMES[l]}</a>`)).join('');
  return `<!doctype html>
<html lang="${lang}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(title)} · ${esc(SITE_NAME)}</title>
<link rel="stylesheet" href="/style.css">
</head>
<body>
<div class="langbar">${switcher}</div>
<header class="top">
  <a class="brand" href="/"><span class="heart">♥</span> ${esc(SITE_NAME)}</a>
  <nav>
    <a href="/">${t('nav_shop')}</a>
    <a href="/donate" class="btn small">${t('nav_donate')}</a>
    ${admin ? `<a href="/admin">${t('nav_admin')}</a>` : ''}
  </nav>
</header>
<main>${body}</main>
<footer>${t('footer')}${RECIPIENT_NAME ? ` ${t('footer_recipient', { name: `${esc(RECIPIENT_NAME)} ${esc(maskedCard())}` })}` : ''}</footer>
</body>
</html>`;
}

function itemCard(item, t) {
  return `<a class="item" href="/item/${item.id}">
    <img src="/uploads/${esc(item.photo)}" alt="${esc(item.title)}" loading="lazy">
    <div class="item-body">
      <h3>${esc(item.title)}</h3>
      <p class="price">${money(item.price, t)}</p>
      <p class="muted">${t('from', { name: esc(item.donor_name) + (item.donor_class ? `, ${esc(item.donor_class)}` : '') })}</p>
    </div>
  </a>`;
}

const statusLabel = (t, s) => t(`status_${s}`);
const orderLabel = (t, s) => t(['cancelled', 'refunded', 'unpaid'].includes(s) ? `order_${s}_s` : `order_${s}`);

module.exports = {
  SITE_NAME, RECIPIENT_NAME, esc, money, date, fullCard, maskedCard, layout, itemCard, statusLabel, orderLabel,
};
