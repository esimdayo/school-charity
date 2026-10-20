CREATE TABLE IF NOT EXISTS items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  description TEXT NOT NULL DEFAULT '',
  price INTEGER NOT NULL,
  category TEXT NOT NULL DEFAULT 'other',
  photo TEXT NOT NULL,
  donor_name TEXT NOT NULL,
  donor_role TEXT NOT NULL,
  donor_class TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL DEFAULT 'pending',
  created_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  item_id INTEGER NOT NULL REFERENCES items(id),
  amount INTEGER NOT NULL,
  buyer_name TEXT NOT NULL,
  buyer_phone TEXT NOT NULL,
  provider TEXT NOT NULL,
  token TEXT NOT NULL UNIQUE,
  pickup_code TEXT,
  payer_card TEXT,
  receipt TEXT,
  status TEXT NOT NULL DEFAULT 'pending',
  created_at INTEGER NOT NULL,
  sent_at INTEGER,
  paid_at INTEGER,
  picked_up_at INTEGER
);

CREATE TABLE IF NOT EXISTS telegram_messages (
  order_id INTEGER NOT NULL REFERENCES orders(id),
  chat_id TEXT NOT NULL,
  message_id INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS telegram_item_messages (
  item_id INTEGER NOT NULL REFERENCES items(id),
  chat_id TEXT NOT NULL,
  message_id INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS settings (
  name TEXT PRIMARY KEY,
  value TEXT NOT NULL
);
