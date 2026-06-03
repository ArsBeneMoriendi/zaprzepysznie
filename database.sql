CREATE DATABASE IF NOT EXISTS zaprzepysznie
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_polish_ci;

USE zaprzepysznie;

DROP TABLE IF EXISTS recipes;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(30) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

CREATE TABLE recipes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    category ENUM('śniadanie', 'obiad', 'kolacja', 'wypieki i desery', 'przekąski', 'szybkie') NOT NULL,
    ingredients TEXT NOT NULL,
    instructions TEXT NOT NULL,
    image_path VARCHAR(255) NULL,
    source_url VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_recipes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_recipes_user (user_id),
    INDEX idx_recipes_title (title),
    INDEX idx_recipes_category (category),
    INDEX idx_recipes_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- Domyślne konto administratora:
-- login: admin
-- hasło: admin123
-- Po uruchomieniu projektu zmień hasło albo utwórz własnego administratora.
INSERT INTO users (username, password_hash, is_admin)
VALUES ('admin', '$2y$12$usYW5clDDLwdmryaR97qEucVHVB0CwTs23WssZgqbAfTTeFg9WA.e', 1);
