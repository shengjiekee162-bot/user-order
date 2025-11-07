-- Create database with proper character set
CREATE DATABASE IF NOT EXISTS shopee_clone CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE shopee_clone;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('buyer', 'seller') NOT NULL DEFAULT 'buyer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

-- Create products table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    category_id INT,
    seller_id INT,
    image VARCHAR(1024) DEFAULT '',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_price DECIMAL(12,2) NOT NULL,
    recipient_name VARCHAR(255) NOT NULL,
    recipient_address TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create order_items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- Insert default categories
INSERT INTO categories (name) VALUES 
    ('电子产品'),
    ('配件'),
    ('生活用品');
    
    -- 在 orders 表中添加支付方式字段
ALTER TABLE orders 
ADD COLUMN payment_method ENUM('cash', 'credit_card', 'online_banking') NOT NULL DEFAULT 'cash',
ADD COLUMN payment_status ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending';

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
