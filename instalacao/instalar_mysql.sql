-- MATHEUS PET CARE V5 — ESTRUTURA INICIAL MYSQL 5.7 / PHP 8+
-- Execute NO BANCO NOVO, selecionado no phpMyAdmin.
-- NÃO execute no banco antigo financely e NÃO contém DROP/DELETE/TRUNCATE.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS pet_users (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 role ENUM('client','admin') NOT NULL DEFAULT 'client',
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 full_name VARCHAR(120) NOT NULL,
 phone VARCHAR(25) NOT NULL,
 street VARCHAR(160) NOT NULL,
 house_number VARCHAR(25) NOT NULL,
 neighborhood VARCHAR(90) NOT NULL DEFAULT '',
 city VARCHAR(100) NOT NULL,
 reference VARCHAR(180) NOT NULL DEFAULT '',
 notes TEXT NULL,
 favorite TINYINT(1) NOT NULL DEFAULT 0,
 monthly_frequency TINYINT UNSIGNED NOT NULL DEFAULT 0,
 admin_notes TEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_users_role (role),
 KEY idx_users_name (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pet_pets (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 name VARCHAR(100) NOT NULL,
 species VARCHAR(45) NOT NULL,
 breed VARCHAR(90) NOT NULL DEFAULT '',
 care_notes TEXT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_pet_owner FOREIGN KEY (user_id) REFERENCES pet_users(id) ON DELETE RESTRICT,
 INDEX idx_pets_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pet_bookings (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 pet_id INT UNSIGNED NOT NULL,
 visit_date DATE NOT NULL,
 visit_time TIME NOT NULL,
 service VARCHAR(70) NOT NULL,
 details TEXT NULL,
 status ENUM('pending','confirmed','declined','cancelled') NOT NULL DEFAULT 'pending',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_booking_user FOREIGN KEY (user_id) REFERENCES pet_users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_booking_pet FOREIGN KEY (pet_id) REFERENCES pet_pets(id) ON DELETE RESTRICT,
 KEY idx_book_user (user_id, created_at),
 KEY idx_book_date (visit_date, visit_time),
 KEY idx_book_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Esta chave PRIMARY KEY impede duas reservas ou bloqueios no mesmo horário.
CREATE TABLE IF NOT EXISTS pet_slots (
 visit_date DATE NOT NULL,
 visit_time TIME NOT NULL,
 state ENUM('pending','confirmed','blocked') NOT NULL,
 booking_id INT UNSIGNED NULL UNIQUE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (visit_date, visit_time),
 CONSTRAINT fk_slot_book FOREIGN KEY (booking_id) REFERENCES pet_bookings(id) ON DELETE CASCADE,
 INDEX idx_slot_day (visit_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pet_manual_clients (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(120) NOT NULL,
 phone VARCHAR(25) NOT NULL,
 street VARCHAR(160) NOT NULL DEFAULT '',
 house_number VARCHAR(25) NOT NULL DEFAULT '',
 neighborhood VARCHAR(90) NOT NULL DEFAULT '',
 city VARCHAR(100) NOT NULL DEFAULT '',
 reference VARCHAR(180) NOT NULL DEFAULT '',
 notes TEXT NULL,
 favorite TINYINT(1) NOT NULL DEFAULT 0,
 monthly_frequency TINYINT UNSIGNED NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_manual_name (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pet_auth_attempts (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 ip_hash CHAR(64) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_auth_ip (ip_hash,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
