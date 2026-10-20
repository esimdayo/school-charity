// Order and item state changes. Every payment provider goes through these
// functions so the rules (reserve, mark sold, release) live in one place.
const crypto = require('node:crypto');
const db = require('./db');

// How long an unpaid order holds its item before it goes back on sale.
const RESERVE_MINUTES = Number(process.env.RESERVE_MINUTES || 30);

const now = () => Date.now();

function getItem(id) {
  return db.prepare('SELECT * FROM items WHERE id = ?').get(id);
}

function getOrder(id) {
  return db.prepare('SELECT * FROM orders WHERE id = ?').get(id);
}

function getOrderByToken(token) {
  return db.prepare('SELECT * FROM orders WHERE token = ?').get(token);
}

function newPickupCode() {
  // 6 characters without look-alikes (0/O, 1/I) so it is easy to read aloud.
  const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  for (;;) {
    let code = '';
    for (const b of crypto.randomBytes(6)) code += alphabet[b % alphabet.length];
    if (!db.prepare('SELECT 1 FROM orders WHERE pickup_code = ?').get(code)) return code;
  }
}

// Unpaid orders past the reservation time are cancelled and their items go
// back on sale. Orders with a Payme transaction in progress are left alone,
// Payme will cancel those itself.
function releaseExpired() {
  const cutoff = now() - RESERVE_MINUTES * 60 * 1000;
  const expired = db.prepare(`
    SELECT o.id FROM orders o
    WHERE o.status = 'pending' AND o.created_at < ?
      AND NOT EXISTS (SELECT 1 FROM payme_transactions t WHERE t.order_id = o.id AND t.state = 1)
  `).all(cutoff);
  for (const { id } of expired) cancelOrder(id);
}

function createOrder(itemId, { buyerName, buyerPhone, provider }) {
  releaseExpired();
  db.exec('BEGIN IMMEDIATE');
  try {
    const item = getItem(itemId);
    if (!item || item.status !== 'available') {
      db.exec('ROLLBACK');
      return null;
    }
    db.prepare("UPDATE items SET status = 'reserved' WHERE id = ?").run(itemId);
    const token = crypto.randomBytes(16).toString('hex');
    const { lastInsertRowid } = db.prepare(`
      INSERT INTO orders (item_id, amount, buyer_name, buyer_phone, provider, token, created_at)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    `).run(itemId, item.price, buyerName, buyerPhone, provider, token, now());
    db.exec('COMMIT');
    return getOrder(Number(lastInsertRowid));
  } catch (err) {
    db.exec('ROLLBACK');
    throw err;
  }
}

// Card transfers: the buyer says they sent the money, a teacher then checks
// the bank app. These orders don't expire; the teacher confirms or rejects.
function markAwaiting(orderId, payerCard) {
  db.prepare("UPDATE orders SET status = 'awaiting', payer_card = ?, sent_at = ? WHERE id = ? AND status = 'pending'")
    .run(payerCard, now(), orderId);
  return getOrder(orderId);
}

function markPaid(orderId, providerTx) {
  const order = getOrder(orderId);
  if (!order || !['pending', 'awaiting'].includes(order.status)) return order;
  db.prepare(`
    UPDATE orders SET status = 'paid', paid_at = ?, pickup_code = ?, provider_tx = ? WHERE id = ?
  `).run(now(), newPickupCode(), providerTx ?? null, orderId);
  db.prepare("UPDATE items SET status = 'sold' WHERE id = ?").run(order.item_id);
  return getOrder(orderId);
}

function cancelOrder(orderId, status = 'cancelled') {
  const order = getOrder(orderId);
  if (!order || !['pending', 'awaiting', 'paid'].includes(order.status)) return order;
  db.prepare('UPDATE orders SET status = ? WHERE id = ?').run(status, orderId);
  db.prepare("UPDATE items SET status = 'available' WHERE id = ? AND status IN ('reserved', 'sold')")
    .run(order.item_id);
  return getOrder(orderId);
}

function markPickedUp(orderId) {
  db.prepare("UPDATE orders SET status = 'picked_up', picked_up_at = ? WHERE id = ? AND status = 'paid'")
    .run(now(), orderId);
  return getOrder(orderId);
}

function totalRaised() {
  return db.prepare("SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS count FROM orders WHERE status IN ('paid', 'picked_up')").get();
}

module.exports = {
  getItem, getOrder, getOrderByToken, createOrder, markAwaiting, markPaid, cancelOrder,
  markPickedUp, releaseExpired, totalRaised,
};
