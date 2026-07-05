CREATE DATABASE IF NOT EXISTS help_center CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE help_center;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin', 'superadmin') NOT NULL DEFAULT 'user',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Password for all demo users: Password@123
INSERT INTO users (name, email, password, role, is_active)
VALUES
('User Demo', 'user@example.com', '$2y$10$U/6kz4CCeaKjpLEuSsKuZuft6GTEdgxIr2QkTtdBX1FrRhz4YDz26', 'user', 1),
('Admin Demo', 'admin@example.com', '$2y$10$U/6kz4CCeaKjpLEuSsKuZuft6GTEdgxIr2QkTtdBX1FrRhz4YDz26', 'admin', 1),
('Superadmin Demo', 'superadmin@example.com', '$2y$10$U/6kz4CCeaKjpLEuSsKuZuft6GTEdgxIr2QkTtdBX1FrRhz4YDz26', 'superadmin', 1)
ON DUPLICATE KEY UPDATE email = VALUES(email);
