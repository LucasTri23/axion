<?php
return [
    'centro_recorrencias' => "CREATE TABLE IF NOT EXISTS centro_recorrencias (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        descricao VARCHAR(255) NOT NULL,
        fornecedor VARCHAR(255) NOT NULL DEFAULT '',
        valor DECIMAL(12,2) NOT NULL,
        dia TINYINT UNSIGNED NOT NULL,
        inicio CHAR(7) NOT NULL,
        fim CHAR(7) NULL,
        os VARCHAR(20) NOT NULL DEFAULT '',
        centro VARCHAR(100) NOT NULL DEFAULT 'GERAL',
        ativo TINYINT NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'centro_recorrencias_geradas' => "CREATE TABLE IF NOT EXISTS centro_recorrencias_geradas (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        recorrencia_id INT UNSIGNED NOT NULL,
        competencia CHAR(7) NOT NULL,
        lancamento_id INT UNSIGNED NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_recorrencia_mes (recorrencia_id,competencia),
        CONSTRAINT fk_recorrencia_regra FOREIGN KEY (recorrencia_id) REFERENCES centro_recorrencias(id) ON DELETE RESTRICT,
        CONSTRAINT fk_recorrencia_lancamento FOREIGN KEY (lancamento_id) REFERENCES centro_lancamentos(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
