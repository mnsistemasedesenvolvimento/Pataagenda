-- MATEUS PET SUPER V7 — ATUALIZAÇÃO SEGURA: NÃO REMOVE DADOS
-- Selecionar somente o banco marce7856_petcare no phpMyAdmin.
-- Execute uma vez para habilitar "Lembrar login" por 30 dias.
CREATE TABLE IF NOT EXISTS pet_remember_tokens (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 selector CHAR(24) NOT NULL UNIQUE,
 token_hash CHAR(64) NOT NULL,
 expires_at DATETIME NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_remember_user (user_id),
 KEY idx_remember_exp (expires_at),
 CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES pet_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
