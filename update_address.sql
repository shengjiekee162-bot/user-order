-- 用户地址表
CREATE TABLE IF NOT EXISTS addresses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  recipient_name VARCHAR(255) NOT NULL,
  recipient_address TEXT NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 用户表增加默认地址字段（可选，推荐直接查 addresses 表 is_default=1）
-- ALTER TABLE users ADD COLUMN default_address_id INT DEFAULT NULL;
