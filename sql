-- Create database
CREATE DATABASE IF NOT EXISTS shopee_clone CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE shopee_clone;

-- Users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('buyer', 'seller') NOT NULL DEFAULT 'buyer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

-- Products
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

-- Orders
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_price DECIMAL(12,2) NOT NULL,
    recipient_name VARCHAR(255) NOT NULL,
    recipient_address TEXT NOT NULL,
    payment_method ENUM('cash', 'credit_card', 'online_banking') NOT NULL DEFAULT 'cash',
    payment_status ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Order items
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- Addresses
CREATE TABLE IF NOT EXISTS addresses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  recipient_name VARCHAR(255) NOT NULL,
  recipient_address TEXT NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Migration: add password_resets table (safe to run multiple times)
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(128) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default categories
INSERT INTO categories (name)
SELECT * FROM (SELECT '电子产品') AS tmp
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name='电子产品');
INSERT INTO categories (name)
SELECT * FROM (SELECT '配件') AS tmp
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name='配件');
INSERT INTO categories (name)
SELECT * FROM (SELECT '生活用品') AS tmp
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name='生活用品');
