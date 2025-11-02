-- SQL script to add password reset fields to users table
-- Run this in phpMyAdmin or MySQL command line

ALTER TABLE `users` 
ADD COLUMN `reset_token` VARCHAR(100) DEFAULT NULL,
ADD COLUMN `reset_token_expiry` DATETIME DEFAULT NULL;
