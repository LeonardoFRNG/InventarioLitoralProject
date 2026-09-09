-- =====================================================================
-- SISTEMA DE INVENTARIO TECNOLÓGICO - Corporación de Educación Superior
-- del Litoral
-- Script de creación de base de datos
-- Motor: MySQL 8.0+
-- =====================================================================

DROP DATABASE IF EXISTS inventario_litoral;
CREATE DATABASE inventario_litoral
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE inventario_litoral;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- 1. CATÁLOGOS
-- =====================================================================

-- ----------------------------------------------------------------
-- Roles del sistema (Administrador, Técnico, Consulta)
-- ----------------------------------------------------------------
CREATE TABLE roles (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(30) NOT NULL,
    descripcion   VARCHAR(150) NULL,
    CONSTRAINT uq_roles_nombre UNIQUE (nombre)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- Tipos de equipo (CPU, Portátil, Monitor, Impresora, Periférico...)
-- ----------------------------------------------------------------
CREATE TABLE tipos_equipo (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(60) NOT NULL,
    CONSTRAINT uq_tipos_equipo_nombre UNIQUE (nombre)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- Categorías (más específico que tipo: Aires acondicionados,
-- Proyectores, Laptops, etc. — según capturas de referencia)
-- ----------------------------------------------------------------
CREATE TABLE categorias (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(60) NOT NULL,
    descripcion   VARCHAR(150) NULL,
    CONSTRAINT uq_categorias_nombre UNIQUE (nombre)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- Estados posibles de un equipo (catálogo cerrado, no texto libre)
-- ----------------------------------------------------------------
CREATE TABLE estados (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(30) NOT NULL,
    color         VARCHAR(20) NOT NULL DEFAULT 'gray',   -- usado por el badge en el frontend
    CONSTRAINT uq_estados_nombre UNIQUE (nombre)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- Ubicaciones / dependencias
-- ----------------------------------------------------------------
CREATE TABLE ubicaciones (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(80) NOT NULL,        -- p.ej. "Sala 03"
    dependencia   VARCHAR(100) NULL,           -- p.ej. "Sistemas de Información"
    CONSTRAINT uq_ubicaciones_nombre_dep UNIQUE (nombre, dependencia)
) ENGINE=InnoDB;

-- =====================================================================
-- 2. USUARIOS
-- =====================================================================
CREATE TABLE usuarios (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    nombre              VARCHAR(100) NOT NULL,
    email               VARCHAR(150) NOT NULL,
    email_recuperacion  VARCHAR(150) NULL,
    password_hash       VARCHAR(255) NOT NULL,
    rol_id              INT NOT NULL,
    activo              TINYINT(1) NOT NULL DEFAULT 1,
    creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_usuarios_email UNIQUE (email),
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id)
        REFERENCES roles(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_usuarios_nombre_no_numerico
        CHECK (nombre REGEXP '[A-Za-zÀ-ÿ]'),
    CONSTRAINT chk_usuarios_email_formato
        CHECK (email REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$')
) ENGINE=InnoDB;

CREATE INDEX idx_usuarios_rol ON usuarios(rol_id);

-- =====================================================================
-- 3. EQUIPOS
-- =====================================================================
CREATE TABLE equipos (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    codigo_inventario   VARCHAR(30) NOT NULL,
    nombre              VARCHAR(100) NOT NULL,
    serial              VARCHAR(80) NULL,
    tipo_id             INT NOT NULL,
    categoria_id        INT NULL,
    ubicacion_id        INT NULL,
    responsable_id      INT NULL,               -- usuario responsable actual
    estado_id           INT NOT NULL,
    caracteristicas     TEXT NULL,
    fecha_registro      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
    eliminado           TINYINT(1) NOT NULL DEFAULT 0,   -- baja lógica

    CONSTRAINT uq_equipos_codigo UNIQUE (codigo_inventario),
    CONSTRAINT uq_equipos_serial UNIQUE (serial),

    CONSTRAINT fk_equipos_tipo FOREIGN KEY (tipo_id)
        REFERENCES tipos_equipo(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_equipos_categoria FOREIGN KEY (categoria_id)
        REFERENCES categorias(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_equipos_ubicacion FOREIGN KEY (ubicacion_id)
        REFERENCES ubicaciones(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_equipos_responsable FOREIGN KEY (responsable_id)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_equipos_estado FOREIGN KEY (estado_id)
        REFERENCES estados(id) ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT chk_equipos_codigo_no_vacio
        CHECK (CHAR_LENGTH(TRIM(codigo_inventario)) > 0)
) ENGINE=InnoDB;

CREATE INDEX idx_equipos_estado ON equipos(estado_id);
CREATE INDEX idx_equipos_categoria ON equipos(categoria_id);
CREATE INDEX idx_equipos_ubicacion ON equipos(ubicacion_id);
CREATE INDEX idx_equipos_codigo ON equipos(codigo_inventario);
CREATE INDEX idx_equipos_serial ON equipos(serial);

-- =====================================================================
-- 4. ASIGNACIONES (equipo <-> usuario responsable, con historial)
-- =====================================================================
CREATE TABLE asignaciones (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    equipo_id           INT NOT NULL,
    usuario_id          INT NOT NULL,
    asignado_por        INT NULL,                -- quién ejecutó la asignación
    fecha_asignacion    DATE NOT NULL,
    fecha_devolucion    DATE NULL,
    activa              TINYINT(1) NOT NULL DEFAULT 1,
    observaciones       VARCHAR(255) NULL,
    creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_asignaciones_equipo FOREIGN KEY (equipo_id)
        REFERENCES equipos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_asignaciones_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_asignaciones_asignado_por FOREIGN KEY (asignado_por)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT chk_asignaciones_fechas
        CHECK (fecha_devolucion IS NULL OR fecha_devolucion >= fecha_asignacion)
) ENGINE=InnoDB;

CREATE INDEX idx_asignaciones_equipo ON asignaciones(equipo_id);
CREATE INDEX idx_asignaciones_usuario ON asignaciones(usuario_id);
CREATE INDEX idx_asignaciones_activa ON asignaciones(activa);

-- =====================================================================
-- 5. MANTENIMIENTOS
-- =====================================================================
CREATE TABLE mantenimientos (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    equipo_id           INT NOT NULL,
    tecnico_id          INT NOT NULL,
    tipo_mantenimiento  ENUM('PREVENTIVO', 'CORRECTIVO') NOT NULL DEFAULT 'PREVENTIVO',
    descripcion         TEXT NOT NULL,
    fecha_inicio        DATE NOT NULL,
    fecha_fin           DATE NULL,
    estado_resultante_id INT NULL,   -- estado del equipo tras el mantenimiento
    creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_mantenimientos_equipo FOREIGN KEY (equipo_id)
        REFERENCES equipos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_mantenimientos_tecnico FOREIGN KEY (tecnico_id)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_mantenimientos_estado FOREIGN KEY (estado_resultante_id)
        REFERENCES estados(id) ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT chk_mantenimientos_fechas
        CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)
) ENGINE=InnoDB;

CREATE INDEX idx_mantenimientos_equipo ON mantenimientos(equipo_id);
CREATE INDEX idx_mantenimientos_tecnico ON mantenimientos(tecnico_id);

-- =====================================================================
-- 6. HISTORIAL DE MOVIMIENTOS (trazabilidad general)
-- =====================================================================
CREATE TABLE historial_movimientos (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    equipo_id           INT NOT NULL,
    usuario_id          INT NULL,                -- quién ejecutó la acción
    accion              VARCHAR(50) NOT NULL,     -- 'CREACION','ASIGNACION','CAMBIO_ESTADO','MANTENIMIENTO','BAJA', etc.
    estado_anterior_id  INT NULL,
    estado_nuevo_id     INT NULL,
    detalle             VARCHAR(255) NULL,
    fecha               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_historial_equipo FOREIGN KEY (equipo_id)
        REFERENCES equipos(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_historial_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_historial_estado_ant FOREIGN KEY (estado_anterior_id)
        REFERENCES estados(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_historial_estado_nvo FOREIGN KEY (estado_nuevo_id)
        REFERENCES estados(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_historial_equipo ON historial_movimientos(equipo_id);
CREATE INDEX idx_historial_fecha ON historial_movimientos(fecha);

-- =====================================================================
-- 7. SESIONES (control de tokens de sesión activos - opcional pero
--    recomendado para poder invalidar sesiones / logout real)
-- =====================================================================
CREATE TABLE sesiones (
    id              VARCHAR(128) PRIMARY KEY,   -- token de sesión (hash)
    usuario_id      INT NOT NULL,
    creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_en       DATETIME NOT NULL,
    CONSTRAINT fk_sesiones_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- DATOS INICIALES (catálogos base)
-- =====================================================================

INSERT INTO roles (nombre, descripcion) VALUES
('Administrador', 'Acceso total: usuarios, equipos, catálogos, reportes'),
('Tecnico',       'Gestiona mantenimientos, asignaciones y estados de equipos'),
('Consulta',      'Solo lectura: dashboard, listados y reportes');

INSERT INTO tipos_equipo (nombre) VALUES
('CPU'), ('Portátil'), ('Monitor'), ('Impresora'), ('Periférico'), ('Otro');

INSERT INTO categorias (nombre, descripcion) VALUES
('Laptops', 'Equipos portátiles'),
('Aires acondicionados', 'Equipos de climatización de salas técnicas'),
('Proyectores', 'Equipos de proyección para aulas y salas'),
('Impresoras', 'Equipos de impresión'),
('Periféricos', 'Mouse, teclados, cámaras, etc.');

INSERT INTO estados (nombre, color) VALUES
('Activo', 'green'),
('Mantenimiento', 'yellow'),
('Dañado', 'orange'),
('Baja', 'red');

INSERT INTO ubicaciones (nombre, dependencia) VALUES
('Sala 03', 'Sistemas de Información'),
('Sala 01', 'Soporte Técnico'),
('Oficina Administrativa', 'Rectoría'),
('Laboratorio 1', 'Sistemas de Información');

-- Usuarios de prueba
-- Password para los 3: Admin123!  (hash real generado con password_hash() PHP/bcrypt)
INSERT INTO usuarios (nombre, email, email_recuperacion, password_hash, rol_id, activo) VALUES
('Admin', 'admin@litoral.com', 'admin@litoral.com',
 '$2y$10$A4er8Ro9SIwvYPHNpvgiX.6MLExLfE67t0oxBV3gZvpEZpsIE/cx2', 1, 1),
('Leonardo Técnico', 'leonardo@litoral.com', 'leonardojim321@gmail.com',
 '$2y$10$A4er8Ro9SIwvYPHNpvgiX.6MLExLfE67t0oxBV3gZvpEZpsIE/cx2', 2, 1),
('Usuario Consulta', 'consulta@litoral.com', 'consulta@litoral.com',
 '$2y$10$A4er8Ro9SIwvYPHNpvgiX.6MLExLfE67t0oxBV3gZvpEZpsIE/cx2', 3, 1);

-- Equipos de prueba (coherentes con las capturas de referencia)
INSERT INTO equipos (codigo_inventario, nombre, serial, tipo_id, categoria_id, ubicacion_id, responsable_id, estado_id, caracteristicas) VALUES
('INV-827',    'Aire acondicionado Sala 03',  'SN-AC-001', 6, 2, 1, 2, 1, '12000 BTU, Split'),
('INV-3232',   'Aire acondicionado Sala 03B', 'SN-AC-002', 6, 2, 1, 2, 2, '12000 BTU, Split'),
('INV-32452',  'Proyector Epson',             'SN-PRY-010', 6, 3, 1, 1, 4, 'Epson PowerLite, HDMI'),
('INV-7654',   'Laptop HP',                   'SN-LHP-045', 2, 1, 3, 3, 2, 'HP ProBook, i5, 8GB RAM'),
('INV-3232755','Aire acondicionado Lab 1',    'SN-AC-003', 6, 2, 4, 2, 1, '18000 BTU, Split');

-- Historial inicial de creación (trazabilidad desde el registro)
INSERT INTO historial_movimientos (equipo_id, usuario_id, accion, estado_nuevo_id, detalle)
SELECT id, 1, 'CREACION', estado_id, CONCAT('Registro inicial del equipo ', codigo_inventario)
FROM equipos;

-- Mantenimiento de ejemplo
INSERT INTO mantenimientos (equipo_id, tecnico_id, tipo_mantenimiento, descripcion, fecha_inicio, fecha_fin, estado_resultante_id)
VALUES (2, 2, 'PREVENTIVO', 'Limpieza de filtros y revisión de gas refrigerante', '2026-08-20', NULL, 2);

-- Asignación de ejemplo
INSERT INTO asignaciones (equipo_id, usuario_id, asignado_por, fecha_asignacion, activa, observaciones)
VALUES (4, 3, 1, '2026-07-01', 1, 'Asignación de laptop para labores administrativas');

-- =====================================================================
-- USUARIO DE APLICACIÓN (buena práctica: la app nunca se conecta como
-- root). Cambia la contraseña antes de llevar esto a producción.
-- Estas credenciales deben coincidir con backend/config/database.php
-- =====================================================================
CREATE USER IF NOT EXISTS 'inventario_app'@'127.0.0.1' IDENTIFIED BY 'InventarioLitoral2026!';
CREATE USER IF NOT EXISTS 'inventario_app'@'localhost'  IDENTIFIED BY 'InventarioLitoral2026!';
GRANT SELECT, INSERT, UPDATE, DELETE ON inventario_litoral.* TO 'inventario_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON inventario_litoral.* TO 'inventario_app'@'localhost';
FLUSH PRIVILEGES;
