-- ═══════════════════════════════════════════════════════════════
-- MIGRATION: Sistema de Autenticação e Permissões
-- ═══════════════════════════════════════════════════════════════

USE senai_gestao;

-- Tabela de Usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    role ENUM('admin', 'gestor', 'professor') NOT NULL DEFAULT 'gestor',
    professor_id INT DEFAULT NULL,
    obrigar_troca_senha TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuário admin inicial (senha: senaisp)
INSERT INTO usuarios (nome, email, senha, role, obrigar_troca_senha)
VALUES ('Administrador', 'admin@senai.br', '$2y$10$.tF7NOtHmH6XuzhQO2kY3uKtESvFuBCq1/ceo.VgahDCPbcIz7TSy', 'admin', 1);
