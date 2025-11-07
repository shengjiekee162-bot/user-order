# Shopee Clone Project

A simple e-commerce web application built with PHP and MySQL.

## Prerequisites

- XAMPP (Apache + MySQL/MariaDB + PHP)
- Web browser
- MySQL root password set to "123qwe" (or update db.php with your credentials)

## Setup Instructions

1. **Start XAMPP Services**
   - Open XAMPP Control Panel
   - Start Apache service
   - Start MySQL service

2. **Database Setup**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import `schema.sql` to create the database and tables
   - Or run the following commands in MySQL:
     ```bash
     mysql -u root -p123qwe < schema.sql
     ```

3. **Application Setup**
   - Rename the folder from "user order" to "user-order" to avoid URL encoding issues
   - Access the application at: http://localhost/user-order/
   - For development with spaces in folder: http://localhost/user%20order/

4. **Test User Flows**
   - Register a seller account
   - Register a buyer account
   - Add products as seller
   - Browse and purchase products as buyer

## Database Structure

- `users`: Store user accounts (buyers and sellers)
- `categories`: Product categories
- `products`: Product listings
- `orders`: Customer orders
- `order_items`: Individual items in each order

## Default Categories

The system comes with three default categories:
- 电子产品 (Electronics)
- 配件 (Accessories)
- 生活用品 (Daily Necessities)

## Troubleshooting

1. **Database Connection Issues**
   - Verify MySQL is running
   - Check credentials in `db.php`
   - Ensure database `shopee_clone` exists

2. **Page Not Found**
   - Verify Apache is running
   - Check folder name in URL (use %20 for spaces)
   - Verify files are in correct location under htdocs