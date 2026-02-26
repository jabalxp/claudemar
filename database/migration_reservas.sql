-- ═══════════════════════════════════════════════════════════════
-- MIGRATION: Sistema de Reservas de Professores
-- ═══════════════════════════════════════════════════════════════

USE senai_gestao;

-- Tabela de Reservas (pré-agendamento por Gestor)
CREATE TABLE IF NOT EXISTS reservas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    professor_id INT NOT NULL,
    usuario_id INT NOT NULL,              -- Gestor que reservou
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    dias_semana VARCHAR(20) NOT NULL,     -- Ex: "1,3,5" (Seg,Qua,Sex)
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,
    status ENUM('ativo', 'concluido') NOT NULL DEFAULT 'ativo',
    notas TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Índices para performance
CREATE INDEX idx_reservas_professor ON reservas(professor_id, status);
CREATE INDEX idx_reservas_datas ON reservas(data_inicio, data_fim, status);
CREATE INDEX idx_reservas_usuario ON reservas(usuario_id, status);
