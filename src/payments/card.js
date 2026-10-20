// Card-to-card transfer. The buyer sends the money to RECIPIENT_CARD from
// their own bank app, then a teacher checks that it arrived and confirms the
// order on the admin page. No payment company account is needed.
const express = require('express');
const shop = require('../shop');
const telegram = require('../telegram');
const { layout, esc, money, fullCard, RECIPIENT_NAME } = require('../views');

const RESERVE_MINUTES = Number(process.env.RESERVE_MINUTES || 30);
const router = express.Router();

function page(req, res, order, error = '') {
  const { t } = req;
  const item = shop.getItem(order.item_id);
  const card = fullCard();
  const amount = money(order.amount, t);
  res.send(layout(req, t('provider_card'), `
    <div class="card narrow">
      <h1>${t('card_title', { amount })}</h1>
      <p>${t('pay_for', { title: esc(item.title) })}</p>
      <p>${t('card_step1')}</p>
      <div class="transfer">
        <div class="transfer-row">
          <div><span class="muted small">${t('card_number')}</span><strong class="cardno" id="cardno">${esc(card)}</strong></div>
          <button type="button" class="btn ghost small" data-copy="${esc(card.replace(/\s/g, ''))}">${t('copy')}</button>
        </div>
        ${RECIPIENT_NAME ? `<div><span class="muted small">${t('card_owner')}</span><strong>${esc(RECIPIENT_NAME)}</strong></div>` : ''}
        <div class="transfer-row">
          <div><span class="muted small">${t('amount')}</span><strong>${amount}</strong></div>
          <button type="button" class="btn ghost small" data-copy="${order.amount}">${t('copy')}</button>
        </div>
      </div>
      <p class="muted small">${t('exact_amount')} ${t('card_time', { min: RESERVE_MINUTES })}</p>
      <p>${t('card_step2')}</p>
      ${error ? `<p class="badge warn">${esc(error)}</p>` : ''}
      <form method="post" action="/payments/card/${esc(order.token)}" class="stack">
        <label>${t('payer_card')}
          <input name="last4" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required placeholder="1234">
          <span class="muted small" style="font-weight:400">${t('payer_card_hint')}</span>
        </label>
        <button class="btn big" name="result" value="sent">${t('sent_button')}</button>
      </form>
      <form method="post" action="/payments/card/${esc(order.token)}">
        <button class="btn ghost small" name="result" value="cancel" formnovalidate>${t('cancel_order')}</button>
      </form>
    </div>
    <script>
      document.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', () => {
        navigator.clipboard?.writeText(b.dataset.copy).then(() => { b.textContent = ${JSON.stringify(t('copied'))}; }).catch(() => {});
      }));
    </script>`));
}

router.get('/:token', (req, res) => {
  const order = shop.getOrderByToken(req.params.token);
  if (!order) return res.status(404).send(layout(req, req.t('not_found'), `<p>${req.t('order_not_found')}</p>`));
  if (order.status !== 'pending') return res.redirect(`/order/${order.token}`);
  page(req, res, order);
});

router.post('/:token', express.urlencoded({ extended: false }), (req, res) => {
  const order = shop.getOrderByToken(req.params.token);
  if (!order) return res.status(404).send(req.t('order_not_found'));
  if (order.status === 'pending') {
    if (req.body.result === 'cancel') {
      shop.cancelOrder(order.id);
    } else {
      const last4 = String(req.body.last4 || '').trim();
      if (!/^\d{4}$/.test(last4)) return page(req, res.status(400), order, req.t('last4_error'));
      telegram.notifyPayment(shop.markAwaiting(order.id, last4));
    }
  }
  res.redirect(`/order/${order.token}`);
});

module.exports = {
  name: 'card',
  router,
  checkoutUrl: (order) => `/payments/card/${order.token}`,
};
