-- 在 orders 表中添加支付方式字段
ALTER TABLE orders 
ADD COLUMN payment_method ENUM('cash', 'credit_card', 'online_banking') NOT NULL DEFAULT 'cash',
ADD COLUMN payment_status ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending';