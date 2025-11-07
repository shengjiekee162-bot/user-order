-- First, make sure we're using the right database
USE shopee_clone;

-- Insert categories if they don't exist
INSERT IGNORE INTO categories (name) VALUES 
    ('电子产品'),  -- ID: 1
    ('配件'),     -- ID: 2
    ('生活用品');  -- ID: 3

-- Show the categories to verify
SELECT id, name FROM categories;