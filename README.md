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


   Prerequisites（运行前准备）

XAMPP（包含 Apache + MySQL + PHP）

浏览器

MySQL root 密码是 "123qwe"（如果你的不一样，需要修改 db.php）

解释：这里是告诉你运行项目需要的环境和账号密码。

Setup Instructions（安装步骤）

Start XAMPP Services（启动 XAMPP 服务）

打开 XAMPP 控制面板

启动 Apache（让网站能运行）

启动 MySQL（让数据库能用）

Database Setup（数据库设置）

打开 phpMyAdmin：http://localhost/phpmyadmin

导入 schema.sql 创建数据库和表

或者命令行导入：

mysql -u root -p123qwe < schema.sql


解释：schema.sql 里面定义了数据库、表和字段，这是项目能用的数据库结构。

Application Setup（项目设置）

把文件夹从 "user order" 改成 "user-order"，避免 URL 空格问题

打开网址：http://localhost/user-order/

如果保留空格，可以用：http://localhost/user%20order/

解释：访问网站时，URL 里的空格会自动转成 %20，改名更安全。

Test User Flows（测试流程）

注册卖家账号

注册买家账号

卖家添加商品

买家浏览和购买商品

解释：这是测试网站功能是否正常。

Database Structure（数据库结构）

users → 用户账号（买家、卖家）

categories → 商品分类

products → 商品列表

orders → 订单

order_items → 每个订单里的商品明细

解释：这些表是网站能管理用户、商品和订单的基础。

Default Categories（默认分类）

电子产品 (Electronics)

配件 (Accessories)

生活用品 (Daily Necessities)

解释：新系统安装后，这三类商品分类会自动存在，方便卖家发布商品。

Troubleshooting（常见问题）

Database Connection Issues（数据库连接问题）

确认 MySQL 在运行

检查 db.php 的账号密码是否正确

确认数据库 shopee_clone 已存在

Page Not Found（页面找不到）

确认 Apache 在运行

检查 URL 和文件夹名（空格用 %20）

确认文件在 htdocs 目录下

解释：这是排错指南，帮助你解决最常遇到的问题。