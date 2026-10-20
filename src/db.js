const path = require('node:path');
const fs = require('node:fs');
const { DatabaseSync } = require('node:sqlite');

const dataDir = process.env.DATA_DIR || path.join(__dirname, '..', 'data');
fs.mkdirSync(dataDir, { recursive: true });

const db = new DatabaseSync(path.join(dataDir, 'shop.db'));
db.exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON;');

db.exec(`
CREATE TABLE IF NOT EXISTS items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  description TEXT NOT NULL DEFAULT '',
  price INTEGER NOT NULL,              -- in so'm
  photo TEXT NOT NULL,
  donor_name TEXT NOT NULL,
  donor_role TEXT NOT NULL,            -- student | teacher | other
  donor_class TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL DEFAULT 'pending', -- pending | available | reserved | sold | rejected
  created_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  item_id INTEGER NOT NULL REFERENCES items(id),
  amount INTEGER NOT NULL,             -- in so'm
  buyer_name TEXT NOT NULL,
  buyer_phone TEXT NOT NULL,
  provider TEXT NOT NULL,
  token TEXT NOT NULL UNIQUE,          -- secret used in the buyer's order link
  pickup_code TEXT,
  status TEXT NOT NULL DEFAULT 'pending', -- pending | awaiting | paid | picked_up | cancelled | unpaid | refunded
  provider_tx TEXT,
  created_at INTEGER NOT NULL,
  paid_at INTEGER,
  picked_up_at INTEGER
);

CREATE TABLE IF NOT EXISTS payme_transactions (
  id TEXT PRIMARY KEY,                 -- Payme's transaction id
  order_id INTEGER NOT NULL REFERENCES orders(id),
  amount INTEGER NOT NULL,             -- in tiyin
  state INTEGER NOT NULL,              -- 1 created, 2 performed, -1 cancelled, -2 cancelled after perform
  payme_time INTEGER NOT NULL,
  create_time INTEGER NOT NULL,
  perform_time INTEGER NOT NULL DEFAULT 0,
  cancel_time INTEGER NOT NULL DEFAULT 0,
  reason INTEGER
);
`);

// Columns added after the first version.
const orderColumns = db.prepare('PRAGMA table_info(orders)').all().map((c) => c.name);
if (!orderColumns.includes('payer_card')) db.exec('ALTER TABLE orders ADD COLUMN payer_card TEXT');
if (!orderColumns.includes('sent_at')) db.exec('ALTER TABLE orders ADD COLUMN sent_at INTEGER');

module.exports = db;
