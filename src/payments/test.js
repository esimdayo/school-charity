// Fake payment page for trying the shop without a real payment account.
// Never enable this on the live site: anyone could "pay" without money.
const express = require('express');
const shop = require('../shop');
const { layout, esc, money } = require('../views');

const router = express.Router();

router.get('/:token', (req, res) => {
  const { t } = req;
  const order = shop.getOrderByToken(req.params.token);
  if (!order) return res.status(404).send(layout(req, t('not_found'), `<p>${t('order_not_found')}</p>`));
  const item = shop.getItem(order.item_id);
  res.send(layout(req, t('provider_test'), `
    <div class="card narrow">
      <p class="badge warn">${t('test_mode')}</p>
      <h1>${t('pay_amount', { amount: money(order.amount, t) })}</h1>
      <p>${t('pay_for', { title: esc(item.title) })}</p>
      <form method="post" action="/payments/test/${esc(order.token)}" class="inline">
        <button class="btn" name="result" value="paid">${t('pay_test')}</button>
        <button class="btn ghost" name="result" value="cancel">${t('cancel')}</button>
      </form>
    </div>`));
});

router.post('/:token', express.urlencoded({ extended: false }), (req, res) => {
  const order = shop.getOrderByToken(req.params.token);
  if (!order) return res.status(404).send(req.t('order_not_found'));
  if (req.body.result === 'paid') shop.markPaid(order.id, `test-${Date.now()}`);
  else shop.cancelOrder(order.id);
  res.redirect(`/order/${order.token}`);
});

module.exports = {
  name: 'test',
  router,
  checkoutUrl: (order) => `/payments/test/${order.token}`,
};
