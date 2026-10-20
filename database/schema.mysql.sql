-- Import once into an empty MySQL database (phpMyAdmin → Import).
CREATE TABLE IF NOT EXISTS items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(80) NOT NULL,
  description TEXT NOT NULL,
  price INT NOT NULL,
  photo VARCHAR(64) NOT NULL,
  donor_name VARCHAR(80) NOT NULL,
  donor_role VARCHAR(16) NOT NULL,
  donor_class VARCHAR(20) NOT NULL DEFAULT '',
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  created_at BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  amount INT NOT NULL,
  buyer_name VARCHAR(80) NOT NULL,
  buyer_phone VARCHAR(20) NOT NULL,
  provider VARCHAR(16) NOT NULL,
  token CHAR(32) NOT NULL UNIQUE,
  pickup_code CHAR(6) NULL,
  payer_card CHAR(4) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  created_at BIGINT NOT NULL,
  sent_at BIGINT NULL,
  paid_at BIGINT NULL,
  picked_up_at BIGINT NULL,
  FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS telegram_messages (
  order_id INT NOT NULL,
  chat_id VARCHAR(32) NOT NULL,
  message_id BIGINT NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS telegram_item_messages (
  item_id INT NOT NULL,
  chat_id VARCHAR(32) NOT NULL,
  message_id BIGINT NOT NULL,
  FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
