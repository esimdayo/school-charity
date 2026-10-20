// Fake payment page for trying the shop without a real payment account.
// Never enable this on the live site: anyone could "pay" without money.
const express = require('express');
const shop = require('../shop');
const { layout, esc, money } = require('../views');

const router = express.Router();

router.get('/:token', (req, res) => {
  const order = shop.getOrderByToken(req.params.token);
  if (!order) return res.status(404).send(layout('Not found', '<p>Order not found.</p>'));
  const item = shop.getItem(order.item_id);
  res.send(layout('Test payment', `
    <div class="card narrow">
      <p class="badge warn">TEST MODE: no real money is charged</p>
      <h1>Pay ${money(order.amount)}</h1>
      <p>For: <strong>${esc(item.title)}</strong></p>
      <form method="post" action="/payments/test/${esc(order.token)}">
        <button class="btn" name="result" value="paid">Pay (test)</button>
        <button class="btn ghost" name="result" value="cancel">Cancel</button>
      </form>
    </div>`));
});

router.post('/:token', express.urlencoded({ extended: false }), (req, res) => {
  const order = shop.getOrderByToken(req.params.token);
  if (!order) return res.status(404).send('Order not found');
  if (req.body.result === 'paid') shop.markPaid(order.id, `test-${Date.now()}`);
  else shop.cancelOrder(order.id);
  res.redirect(`/order/${order.token}`);
});

module.exports = {
  name: 'test',
  label: 'Test payment',
  router,
  checkoutUrl: (order) => `/payments/test/${order.token}`,
};
