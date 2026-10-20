const SITE_NAME = process.env.SITE_NAME || 'School Charity Shop';
const RECIPIENT_NAME = process.env.RECIPIENT_NAME || '';
const RECIPIENT_CARD = process.env.RECIPIENT_CARD || '';

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[c]));

const money = (n) => `${Number(n).toLocaleString('en-US').replace(/,/g, ' ')} so'm`;

const date = (ms) => (ms ? new Date(ms).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' }) : '');

// Only the last 4 digits of the card are ever shown on the site.
const maskedCard = () => (RECIPIENT_CARD ? `•••• ${RECIPIENT_CARD.replace(/\D/g, '').slice(-4)}` : '');

function layout(title, body, { admin = false } = {}) {
  return `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(title)} · ${esc(SITE_NAME)}</title>
<link rel="stylesheet" href="/style.css">
</head>
<body>
<header class="top">
  <a class="brand" href="/"><span class="heart">♥</span> ${esc(SITE_NAME)}</a>
  <nav>
    <a href="/">Shop</a>
    <a href="/donate" class="btn small">Donate an item</a>
    ${admin ? '<a href="/admin">Admin</a>' : ''}
  </nav>
</header>
<main>${body}</main>
<footer>Every so'm goes to someone who needs it.${RECIPIENT_NAME ? ` Recipient: ${esc(RECIPIENT_NAME)} ${esc(maskedCard())}` : ''}</footer>
</body>
</html>`;
}

const statusLabel = {
  pending: 'Waiting for approval',
  available: 'For sale',
  reserved: 'Reserved',
  sold: 'Sold',
  rejected: 'Rejected',
};

const orderLabel = {
  pending: 'Waiting for payment',
  paid: 'Paid, ready for pickup',
  picked_up: 'Picked up',
  cancelled: 'Cancelled',
  refunded: 'Refunded',
};

function itemCard(item) {
  return `<a class="item" href="/item/${item.id}">
    <img src="/uploads/${esc(item.photo)}" alt="${esc(item.title)}" loading="lazy">
    <div class="item-body">
      <h3>${esc(item.title)}</h3>
      <p class="price">${money(item.price)}</p>
      <p class="muted">From ${esc(item.donor_name)}${item.donor_class ? `, ${esc(item.donor_class)}` : ''}</p>
    </div>
  </a>`;
}

module.exports = {
  SITE_NAME, RECIPIENT_NAME, esc, money, date, maskedCard, layout, itemCard, statusLabel, orderLabel,
};
