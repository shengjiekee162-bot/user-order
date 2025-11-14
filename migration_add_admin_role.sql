-- Migration: add 'admin' to users.role ENUM
-- Run this in MySQL to allow an 'admin' role: 
-- ALTER TABLE users MODIFY role ENUM('buyer','seller','admin') NOT NULL DEFAULT 'buyer';

ALTER TABLE users MODIFY role ENUM('buyer','seller','admin') NOT NULL DEFAULT 'buyer';
