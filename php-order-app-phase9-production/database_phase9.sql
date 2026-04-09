CREATE TABLE tenants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  subdomain VARCHAR(255) UNIQUE NOT NULL,
  plan_code VARCHAR(50) DEFAULT 'free',
  status ENUM('active','suspended') DEFAULT 'active',
  logo VARCHAR(255) DEFAULT NULL,
  theme_json JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) UNIQUE NOT NULL,
  name VARCHAR(50) NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  order_limit INT NOT NULL DEFAULT 100,
  item_limit INT NOT NULL DEFAULT 100,
  api_limit INT NOT NULL DEFAULT 1000,
  features_json JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO plans (code, name, price, order_limit, item_limit, api_limit, features_json) VALUES
('free', 'Free', 0.00, 100, 50, 1000, JSON_OBJECT('branding', false, 'analytics', true)),
('starter', 'Starter', 9.99, 1000, 500, 10000, JSON_OBJECT('branding', true, 'analytics', true)),
('growth', 'Growth', 29.99, 10000, 5000, 100000, JSON_OBJECT('branding', true, 'analytics', true, 'api', true));

CREATE TABLE subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  plan_id INT NOT NULL,
  gateway VARCHAR(50) DEFAULT NULL,
  gateway_ref VARCHAR(255) DEFAULT NULL,
  status ENUM('trial','active','past_due','cancelled') DEFAULT 'active',
  expires_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE RESTRICT
);

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  email VARCHAR(150) NOT NULL,
  username VARCHAR(100) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('client','admin','super_admin') NOT NULL DEFAULT 'client',
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_tenant_email (tenant_id, email),
  UNIQUE KEY unique_tenant_username (tenant_id, username),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  price INT NOT NULL,
  sku VARCHAR(100) DEFAULT NULL,
  stock INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_items_tenant (tenant_id),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  client_name VARCHAR(100) NOT NULL,
  client_email VARCHAR(150) DEFAULT NULL,
  items JSON NOT NULL,
  payment_method VARCHAR(50) DEFAULT 'pay_on_delivery',
  payment_status ENUM('unpaid','paid') DEFAULT 'unpaid',
  status ENUM('pending','packing','shipped','delivered','cancelled') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_orders_tenant_status (tenant_id, status),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  order_id INT DEFAULT NULL,
  gateway VARCHAR(50) NOT NULL,
  transaction_ref VARCHAR(255) NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status VARCHAR(50) DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_payments_tenant_ref (tenant_id, transaction_ref),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);

CREATE TABLE api_keys (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  label VARCHAR(100) NOT NULL,
  api_key VARCHAR(255) NOT NULL UNIQUE,
  permissions_json JSON DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE event_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT DEFAULT NULL,
  event_name VARCHAR(100) NOT NULL,
  payload_json JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_event_logs_tenant_event (tenant_id, event_name)
);

INSERT INTO tenants (name, subdomain, plan_code, status) VALUES
('Demo Store', 'demo', 'free', 'active');

INSERT INTO subscriptions (tenant_id, plan_id, status)
SELECT 1, id, 'active' FROM plans WHERE code='free';

INSERT INTO users (tenant_id, email, username, password, role)
VALUES (
  1,
  'owner@demo.local',
  'owner',
  '$2y$10$CwTycUXWue0Thq9StjUM0uJ8xQ0lG5JX1x0C6Qw7x6YfJOLLm1A2S',
  'super_admin'
);
