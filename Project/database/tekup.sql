-- TEK-UP Certified Students database
-- Import in phpMyAdmin: Import tab > Choose file > Go.

CREATE DATABASE IF NOT EXISTS `tekup`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `tekup`;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `email`      VARCHAR(190) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `firstname`  VARCHAR(100) DEFAULT NULL,
  `lastname`   VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed users. Passwords are plain text for simplicity, as agreed.
-- In production replace with password_hash() and verify with password_verify().
INSERT INTO `users` (`email`, `password`, `firstname`, `lastname`) VALUES
  ('user1@tek-up.de', '123456789', 'User', 'One'),
  ('user2@tek-up.de', '123456789', 'User', 'Two');
