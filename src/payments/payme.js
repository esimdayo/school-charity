// Payme (paycom.uz) Merchant API.
// Docs: https://developer.help.paycom.uz
// In the Payme merchant cabinet set the endpoint URL to
//   https://YOUR-SITE/payments/payme
// and the account field name to "order_id".
const crypto = require('node:crypto');
const express = require('express');
const db = require('../db');
const shop = require('../shop');

const MERCHANT_ID = process.env.PAYME_MERCHANT_ID || '';
const KEY = process.env.PAYME_KEY || ''; // test key in test mode, real key in production
const TEST = process.env.PAYME_TEST_MODE !== 'false';
const CHECKOUT = TEST ? 'https://test.paycom.uz' : 'https://checkout.paycom.uz';
const TIMEOUT_MS = 12 * 60 * 60 * 1000; // Payme cancels unperformed transactions after 12h

const msg = (en, ru = en, uz = en) => ({ en, ru, uz });
const E = {
  auth: { code: -32504, message: msg('Not authorized', 'Недостаточно привилегий', 'Ruxsat yo‘q') },
  method: { code: -32601, message: msg('Method not found', 'Метод не найден', 'Metod topilmadi') },
  amount: { code: -31001, message: msg('Wrong amount', 'Неверная сумма', 'Noto‘g‘ri summa') },
  notFound: { code: -31003, message: msg('Transaction not found', 'Транзакция не найдена', 'Tranzaksiya topilmadi') },
  cantCancel: { code: -31007, message: msg('Item already handed over', 'Товар уже выдан', 'Buyum allaqachon berilgan') },
  cantPerform: { code: -31008, message: msg('Cannot perform operation', 'Невозможно выполнить операцию', 'Amalni bajarib bo‘lmaydi') },
  noOrder: { code: -31050, message: msg('Order not found', 'Заказ не найден', 'Buyurtma topilmadi'), data: 'order_id' },
  busy: { code: -31051, message: msg('Order is waiting for another payment', 'Заказ ожидает другую оплату', 'Buyurtma boshqa to‘lovni kutmoqda'), data: 'order_id' },
};

class RpcError extends Error {
  constructor(err) { super(err.message.en); this.rpc = err; }
}

function authorized(req) {
  const header = req.get('authorization') || '';
  const expected = 'Basic ' + Buffer.from(`Paycom:${KEY}`).toString('base64');
  const a = Buffer.from(header);
  const b = Buffer.from(expected);
  return KEY && a.length === b.length && crypto.timingSafeEqual(a, b);
}

const getTx = (id) => db.prepare('SELECT * FROM payme_transactions WHERE id = ?').get(id);

function txResult(tx) {
  return {
    create_time: tx.create_time,
    perform_time: tx.perform_time,
    cancel_time: tx.cancel_time,
    transaction: String(tx.order_id) + ':' + tx.id,
    state: tx.state,
    reason: tx.reason ?? null,
  };
}

function orderFor(params) {
  const order = shop.getOrder(Number(params.account?.order_id));
  if (!order) throw new RpcError(E.noOrder);
  if (order.status !== 'pending') throw new RpcError(E.cantPerform);
  if (Number(params.amount) !== order.amount * 100) throw new RpcError(E.amount);
  return order;
}

function cancelTx(tx, reason) {
  const state = tx.state === 2 ? -2 : -1;
  db.prepare('UPDATE payme_transactions SET state = ?, cancel_time = ?, reason = ? WHERE id = ?')
    .run(state, Date.now(), reason, tx.id);
  shop.cancelOrder(tx.order_id, state === -2 ? 'refunded' : 'cancelled');
  return getTx(tx.id);
}

const methods = {
  CheckPerformTransaction(params) {
    orderFor(params);
    return { allow: true };
  },

  CreateTransaction(params) {
    let tx = getTx(params.id);
    if (tx) {
      if (tx.state !== 1) throw new RpcError(E.cantPerform);
      if (Date.now() - tx.payme_time > TIMEOUT_MS) {
        cancelTx(tx, 4);
        throw new RpcError(E.cantPerform);
      }
      return { create_time: tx.create_time, transaction: txResult(tx).transaction, state: tx.state };
    }
    const order = orderFor(params);
    const other = db.prepare('SELECT 1 FROM payme_transactions WHERE order_id = ? AND state = 1').get(order.id);
    if (other) throw new RpcError(E.busy);
    db.prepare(`INSERT INTO payme_transactions (id, order_id, amount, state, payme_time, create_time)
                VALUES (?, ?, ?, 1, ?, ?)`).run(params.id, order.id, Number(params.amount), Number(params.time), Date.now());
    tx = getTx(params.id);
    return { create_time: tx.create_time, transaction: txResult(tx).transaction, state: tx.state };
  },

  PerformTransaction(params) {
    let tx = getTx(params.id);
    if (!tx) throw new RpcError(E.notFound);
    if (tx.state === 1) {
      if (Date.now() - tx.payme_time > TIMEOUT_MS) {
        cancelTx(tx, 4);
        throw new RpcError(E.cantPerform);
      }
      db.prepare('UPDATE payme_transactions SET state = 2, perform_time = ? WHERE id = ?').run(Date.now(), tx.id);
      shop.markPaid(tx.order_id, `payme-${tx.id}`);
      tx = getTx(tx.id);
    } else if (tx.state !== 2) {
      throw new RpcError(E.cantPerform);
    }
    return { transaction: txResult(tx).transaction, perform_time: tx.perform_time, state: tx.state };
  },

  CancelTransaction(params) {
    let tx = getTx(params.id);
    if (!tx) throw new RpcError(E.notFound);
    if (tx.state === 1 || tx.state === 2) {
      if (tx.state === 2 && shop.getOrder(tx.order_id).status === 'picked_up') {
        throw new RpcError(E.cantCancel);
      }
      tx = cancelTx(tx, Number(params.reason));
    }
    return { transaction: txResult(tx).transaction, cancel_time: tx.cancel_time, state: tx.state };
  },

  CheckTransaction(params) {
    const tx = getTx(params.id);
    if (!tx) throw new RpcError(E.notFound);
    return txResult(tx);
  },

  GetStatement(params) {
    const rows = db.prepare('SELECT * FROM payme_transactions WHERE payme_time BETWEEN ? AND ? ORDER BY payme_time')
      .all(Number(params.from), Number(params.to));
    return {
      transactions: rows.map((tx) => ({
        id: tx.id,
        time: tx.payme_time,
        amount: tx.amount,
        account: { order_id: String(tx.order_id) },
        ...txResult(tx),
      })),
    };
  },
};

const router = express.Router();
router.post('/', express.json(), (req, res) => {
  const { id = null, method, params = {} } = req.body || {};
  if (!authorized(req)) return res.json({ id, error: E.auth });
  const fn = methods[method];
  if (!fn) return res.json({ id, error: E.method });
  try {
    res.json({ id, result: fn(params) });
  } catch (err) {
    if (err.rpc) return res.json({ id, error: err.rpc });
    console.error('Payme error', err);
    res.json({ id, error: { code: -32400, message: msg('Internal error') } });
  }
});

module.exports = {
  name: 'payme',
  label: 'Payme',
  router,
  checkoutUrl(order, baseUrl) {
    const params = `m=${MERCHANT_ID};ac.order_id=${order.id};a=${order.amount * 100};c=${baseUrl}/order/${order.token}`;
    return `${CHECKOUT}/${Buffer.from(params).toString('base64')}`;
  },
};
