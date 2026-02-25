CREATE DATABASE IF NOT EXISTS senai_gestao;
USE senai_gestao;

-- Tabela de Professores (DOCENTES)
CREATE TABLE IF NOT EXISTS professores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    especialidade VARCHAR(100),              -- Área (ex: TECNOLOGIA DA INFORMAÇÃO)
    carga_horaria_contratual INT DEFAULT 0,  -- Carga Horária Máx
    cor_agenda VARCHAR(7) DEFAULT '#ed1c24',
    email VARCHAR(100),
    cidade VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Ambientes (AMBIENTES / Salas)
CREATE TABLE IF NOT EXISTS salas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    tipo VARCHAR(50) DEFAULT 'Sala',         -- Sala, Laboratório, Oficina, etc.
    area VARCHAR(100) DEFAULT NULL,          -- Área (ex: Informática, Metalmecânica)
    cidade VARCHAR(100) DEFAULT NULL,
    capacidade INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Cursos (CURSOS)
CREATE TABLE IF NOT EXISTS cursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo VARCHAR(50) DEFAULT NULL,           -- FIC, Técnico, etc.
    area VARCHAR(100) DEFAULT NULL,          -- Área (ex: AUTOMOTIVA)
    carga_horaria INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Turmas (TURMAS)
CREATE TABLE IF NOT EXISTS turmas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,              -- SIGLA DA TURMA
    curso_id INT,
    data_inicio DATE,
    data_fim DATE,
    turno VARCHAR(20) DEFAULT NULL,          -- Matutino, Vespertino, Noturno (derivado de horário)
    cidade VARCHAR(100) DEFAULT NULL,
    vagas INT DEFAULT NULL,
    horario VARCHAR(50) DEFAULT NULL,        -- Ex: "08h às 17h"
    dias_semana VARCHAR(100) DEFAULT NULL,   -- Ex: "2ª, 3ª E 4º"
    docente1 VARCHAR(100) DEFAULT NULL,
    docente2 VARCHAR(100) DEFAULT NULL,
    docente3 VARCHAR(100) DEFAULT NULL,
    docente4 VARCHAR(100) DEFAULT NULL,
    ambiente VARCHAR(100) DEFAULT NULL,      -- Ambiente planejado
    local_turma VARCHAR(100) DEFAULT NULL,   -- Ex: "CFP850"
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Agenda (Horários) - O Coração do Sistema
CREATE TABLE IF NOT EXISTS agenda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turma_id INT,
    professor_id INT,
    sala_id INT,
    data DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE CASCADE,
    FOREIGN KEY (sala_id) REFERENCES salas(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─────────────────────────────────────────────
-- MIGRATION: Add new columns to existing tables
-- Run these ALTERs if tables already exist
-- ─────────────────────────────────────────────
-- ALTER TABLE professores ADD COLUMN cidade VARCHAR(100) DEFAULT NULL;
-- ALTER TABLE salas MODIFY COLUMN tipo VARCHAR(50) DEFAULT 'Sala';
-- ALTER TABLE salas ADD COLUMN area VARCHAR(100) DEFAULT NULL;
-- ALTER TABLE salas ADD COLUMN cidade VARCHAR(100) DEFAULT NULL;
-- ALTER TABLE cursos ADD COLUMN tipo VARCHAR(50) DEFAULT NULL;
-- ALTER TABLE cursos ADD COLUMN area VARCHAR(100) DEFAULT NULL;
-- ALTER TABLE turmas MODIFY COLUMN turno VARCHAR(20) DEFAULT NULL;
-- ALTER TABLE turmas MODIFY COLUMN nome VARCHAR(100) NOT NULL;
-- ALTER TABLE turmas ADD COLUMN vagas INT DEFAULT NULL;
-- ALTER TABLE turmas ADD COLUMN horario VARCHAR(50) DEFAULT NULL;
-- ALTER TABLE turmas ADD COLUMN dias_semana VARCHAR(100) DEFAULT NULL;
-- ALTER TABLE turmas ADD COLUMN docente1 VARCHAR(100) DEFAULT NULL;
-- ALTER TABLE turmas ADD COLUMN docente2 VARCHAR(100) DEFAULT NULL;
-- ALTER TABLE turmas ADD COLUMN docente3 VARCHAR(100) DEFAULT NULL;
-- ALTER TABLE turmas ADD COLUMN docente4 VARCHAR(100) DEFAULT NULL;
-- ALTER TABLE turmas ADD COLUMN ambiente VARCHAR(100) DEFAULT NULL;
-- ALTER TABLE turmas ADD COLUMN local_turma VARCHAR(100) DEFAULT NULL;
