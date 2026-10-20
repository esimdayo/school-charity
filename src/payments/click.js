// Click (click.uz) SHOP-API.
// Docs: https://docs.click.uz/en/click-api-request/
// In the Click merchant cabinet set:
//   Prepare URL:  https://YOUR-SITE/payments/click/prepare
//   Complete URL: https://YOUR-SITE/payments/click/complete
const crypto = require('node:crypto');
const express = require('express');
const shop = require('../shop');

const SERVICE_ID = process.env.CLICK_SERVICE_ID || '';
const MERCHANT_ID = process.env.CLICK_MERCHANT_ID || '';
const SECRET_KEY = process.env.CLICK_SECRET_KEY || '';

const md5 = (s) => crypto.createHash('md5').update(s).digest('hex');

function signOk(b, withPrepareId) {
  const parts = [b.click_trans_id, b.service_id, SECRET_KEY, b.merchant_trans_id];
  if (withPrepareId) parts.push(b.merchant_prepare_id);
  parts.push(b.amount, b.action, b.sign_time);
  const expected = md5(parts.join(''));
  const got = String(b.sign_string || '');
  return SECRET_KEY && got.length === expected.length &&
    crypto.timingSafeEqual(Buffer.from(got), Buffer.from(expected));
}

function reply(res, b, error, note, extra = {}) {
  res.json({
    click_trans_id: b.click_trans_id,
    merchant_trans_id: b.merchant_trans_id,
    error,
    error_note: note,
    ...extra,
  });
}

// Checks shared by prepare and complete. Returns the order, or sends an
// error response and returns null.
function check(res, b, withPrepareId, action) {
  if (!signOk(b, withPrepareId)) return reply(res, b, -1, 'SIGN CHECK FAILED'), null;
  if (String(b.action) !== action) return reply(res, b, -3, 'Action not found'), null;
  const order = shop.getOrder(Number(b.merchant_trans_id));
  if (!order) return reply(res, b, -5, 'Order not found'), null;
  if (withPrepareId && String(b.merchant_prepare_id) !== String(order.id)) {
    return reply(res, b, -6, 'Transaction not found'), null;
  }
  if (order.status === 'paid' || order.status === 'picked_up') return reply(res, b, -4, 'Already paid'), null;
  if (order.status !== 'pending') return reply(res, b, -9, 'Transaction cancelled'), null;
  if (Number(b.amount) !== order.amount) return reply(res, b, -2, 'Incorrect amount'), null;
  return order;
}

const router = express.Router();
router.use(express.urlencoded({ extended: false }));

router.post('/prepare', (req, res) => {
  const order = check(res, req.body, false, '0');
  if (!order) return;
  reply(res, req.body, 0, 'Success', { merchant_prepare_id: order.id });
});

router.post('/complete', (req, res) => {
  const b = req.body;
  // Click reports a failed payment with a negative error code.
  if (Number(b.error) < 0) {
    if (signOk(b, true)) shop.cancelOrder(Number(b.merchant_trans_id));
    return reply(res, b, -9, 'Transaction cancelled');
  }
  const order = check(res, b, true, '1');
  if (!order) return;
  shop.markPaid(order.id, `click-${b.click_trans_id}`);
  reply(res, b, 0, 'Success', { merchant_confirm_id: order.id });
});

module.exports = {
  name: 'click',
  label: 'Click',
  router,
  checkoutUrl(order, baseUrl) {
    const q = new URLSearchParams({
      service_id: SERVICE_ID,
      merchant_id: MERCHANT_ID,
      amount: String(order.amount),
      transaction_param: String(order.id),
      return_url: `${baseUrl}/order/${order.token}`,
    });
    return `https://my.click.uz/services/pay?${q}`;
  },
};
