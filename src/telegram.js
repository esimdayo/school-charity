// Telegram bot for admins: when a buyer says they sent money to the card, each
// admin gets a message with the item photo and two buttons, "Money arrived" and
// "Not received". Pressing one does the same as the buttons on the admin page.
//
// The bot uses long polling (getUpdates), so it works on any server without a
// public HTTPS address. Set TELEGRAM_BOT_TOKEN and TELEGRAM_ADMIN_IDS in .env.
const fs = require('node:fs');
const path = require('node:path');
const db = require('./db');
const shop = require('./shop');
const { translator } = require('./i18n');
const { money, date, esc } = require('./views');

const TOKEN = process.env.TELEGRAM_BOT_TOKEN || '';
const ADMIN_IDS = (process.env.TELEGRAM_ADMIN_IDS || '').split(',').map((s) => s.trim()).filter(Boolean);
const LANG = process.env.TELEGRAM_LANG || 'uz';
const API = `${process.env.TELEGRAM_API_URL || 'https://api.telegram.org'}/bot${TOKEN}`;
const uploadDir = process.env.UPLOAD_DIR || path.join(__dirname, '..', 'uploads');
const t = translator(LANG);

const enabled = Boolean(TOKEN);

db.exec(`CREATE TABLE IF NOT EXISTS telegram_messages (
  order_id INTEGER NOT NULL REFERENCES orders(id),
  chat_id TEXT NOT NULL,
  message_id INTEGER NOT NULL
)`);

async function call(method, body) {
  const isForm = body instanceof FormData;
  const res = await fetch(`${API}/${method}`, {
    method: 'POST',
    headers: isForm ? undefined : { 'content-type': 'application/json' },
    body: isForm ? body : JSON.stringify(body),
  });
  const data = await res.json().catch(() => ({}));
  if (!data.ok) throw new Error(`Telegram ${method}: ${data.description || res.status}`);
  return data.result;
}

function caption(order, item, footer = '') {
  return [
    `<b>${t('tg_new_payment')}</b>`,
    '',
    `💰 <b>${money(order.amount, t)}</b>`,
    `🎁 ${esc(item.title)}`,
    `👤 ${esc(order.buyer_name)}, ${esc(order.buyer_phone)}`,
    `💳 ${t('payer_card_short', { last4: esc(order.payer_card) })}`,
    `🕒 ${date(order.sent_at, LANG)}`,
    footer ? `\n${footer}` : `\n${t('tg_check_bank')}`,
  ].join('\n');
}

const buttons = (orderId) => ({
  inline_keyboard: [[
    { text: `✅ ${t('money_arrived')}`, callback_data: `confirm:${orderId}` },
    { text: `❌ ${t('not_received')}`, callback_data: `unpaid:${orderId}` },
  ]],
});

// Called when a buyer presses "I've sent the money".
async function notifyPayment(order) {
  if (!enabled) return;
  const item = shop.getItem(order.item_id);
  const photoPath = path.join(uploadDir, path.basename(item.photo));
  for (const chatId of ADMIN_IDS) {
    try {
      const form = new FormData();
      form.append('chat_id', chatId);
      form.append('caption', caption(order, item));
      form.append('parse_mode', 'HTML');
      form.append('reply_markup', JSON.stringify(buttons(order.id)));
      let msg;
      if (fs.existsSync(photoPath)) {
        form.append('photo', new Blob([fs.readFileSync(photoPath)]), path.basename(photoPath));
        msg = await call('sendPhoto', form);
      } else {
        msg = await call('sendMessage', {
          chat_id: chatId, text: caption(order, item), parse_mode: 'HTML', reply_markup: buttons(order.id),
        });
      }
      db.prepare('INSERT INTO telegram_messages (order_id, chat_id, message_id) VALUES (?, ?, ?)')
        .run(order.id, String(chatId), msg.message_id);
    } catch (err) {
      console.error('Telegram notify failed:', err.message);
    }
  }
}

// After a payment is confirmed or rejected (in Telegram or on the website),
// replace the buttons in every admin's message with the result.
async function markHandled(orderId, byName = '') {
  if (!enabled) return;
  const order = shop.getOrder(orderId);
  const item = shop.getItem(order.item_id);
  const result = order.status === 'paid' || order.status === 'picked_up'
    ? `✅ <b>${t('tg_confirmed', { code: esc(order.pickup_code) })}</b>`
    : `❌ <b>${t('tg_rejected')}</b>`;
  const footer = byName ? `${result}\n${t('tg_by', { name: esc(byName) })}` : result;
  const rows = db.prepare('SELECT * FROM telegram_messages WHERE order_id = ?').all(orderId);
  for (const row of rows) {
    const params = {
      chat_id: row.chat_id, message_id: row.message_id, parse_mode: 'HTML',
      reply_markup: { inline_keyboard: [] },
    };
    // Messages are photos with a caption, or plain text when the photo was missing.
    await call('editMessageCaption', { ...params, caption: caption(order, item, footer) })
      .catch(() => call('editMessageText', { ...params, text: caption(order, item, footer) }))
      .catch((err) => console.error('Telegram edit failed:', err.message));
  }
}

async function handleCallback(cb) {
  const fromId = String(cb.from?.id);
  if (!ADMIN_IDS.includes(fromId)) {
    return call('answerCallbackQuery', { callback_query_id: cb.id, text: t('tg_not_admin'), show_alert: true });
  }
  const [action, id] = String(cb.data || '').split(':');
  const order = shop.getOrder(Number(id));
  if (!order) return call('answerCallbackQuery', { callback_query_id: cb.id, text: t('order_not_found') });
  if (order.status !== 'awaiting') {
    await call('answerCallbackQuery', { callback_query_id: cb.id, text: t('tg_already') });
    return markHandled(order.id);
  }
  if (action === 'confirm') shop.markPaid(order.id, `card-${order.payer_card}`);
  else if (action === 'unpaid') shop.cancelOrder(order.id, 'unpaid');
  else return call('answerCallbackQuery', { callback_query_id: cb.id });
  await call('answerCallbackQuery', { callback_query_id: cb.id, text: action === 'confirm' ? '✅' : '❌' });
  const name = [cb.from.first_name, cb.from.last_name].filter(Boolean).join(' ');
  return markHandled(order.id, name);
}

// Any message to the bot: tell the sender their ID, so it can go in TELEGRAM_ADMIN_IDS.
function handleMessage(msg) {
  const chatId = String(msg.chat.id);
  const text = ADMIN_IDS.includes(chatId) ? t('tg_hello_admin') : t('tg_hello_other', { id: chatId });
  return call('sendMessage', { chat_id: chatId, text, parse_mode: 'HTML' });
}

async function poll() {
  let offset = 0;
  for (;;) {
    try {
      const updates = await call('getUpdates', { offset, timeout: 30, allowed_updates: ['message', 'callback_query'] });
      for (const u of updates) {
        offset = u.update_id + 1;
        try {
          if (u.callback_query) await handleCallback(u.callback_query);
          else if (u.message?.chat) await handleMessage(u.message);
        } catch (err) {
          console.error('Telegram update failed:', err.message);
        }
      }
    } catch (err) {
      console.error('Telegram polling error:', err.message);
      await new Promise((r) => setTimeout(r, 5000));
    }
  }
}

function start() {
  if (!enabled) return;
  if (!ADMIN_IDS.length) console.warn('TELEGRAM_ADMIN_IDS is empty: send any message to the bot to get your ID.');
  console.log('Telegram bot is on');
  poll();
}

const WEBSITE = t('tg_website');

module.exports = { enabled, start, notifyPayment, markHandled, WEBSITE };
