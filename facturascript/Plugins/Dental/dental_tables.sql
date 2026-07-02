-- Dental Plugin Tables
-- Charset: utf8mb3 / utf8mb3_bin

SET NAMES utf8mb3;
SET FOREIGN_KEY_CHECKS = 0;

-- Tabla: dental_especialidades
CREATE TABLE dental_especialidades (
    id INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    estado VARCHAR(20) DEFAULT 'activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

-- Tabla: dental_gabinetes
CREATE TABLE dental_gabinetes (
    id INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(10) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    ubicacion VARCHAR(100),
    equipamiento TEXT,
    estado VARCHAR(20) DEFAULT 'activo',
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY dental_gabinetes_codigo_unique (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

-- Tabla: dental_especialistas
CREATE TABLE dental_especialistas (
    id INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
    codusuario VARCHAR(50),
    nombre VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    numero_colegiado VARCHAR(50),
    telefono VARCHAR(20),
    email VARCHAR(100),
    color_agenda VARCHAR(7) DEFAULT '#3b82f6',
    estado VARCHAR(20) DEFAULT 'activo',
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

-- Tabla: dental_pacientes
CREATE TABLE dental_pacientes (
    id INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
    codcliente VARCHAR(10) NOT NULL,
    aseguradora VARCHAR(100),
    numero_poliza VARCHAR(50),
    alergias TEXT,
    medicacion TEXT,
    antecedentes_medicos TEXT,
    antecedentes_odontologicos TEXT,
    observaciones TEXT,
    estado VARCHAR(20) DEFAULT 'activo',
    fecha_alta DATE,
    created_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY dental_pacientes_codcliente_unique (codcliente),
    CONSTRAINT ca_dental_pacientes_clientes FOREIGN KEY (codcliente) REFERENCES clientes (codcliente) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

-- Tabla: dental_especialista_especialidad (relación N:M)
CREATE TABLE dental_especialista_especialidad (
    id INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
    idespecialista INTEGER UNSIGNED NOT NULL,
    idespecialidad INTEGER UNSIGNED NOT NULL,
    es_principal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY dental_especialista_especialidad_unique (idespecialista, idespecialidad),
    CONSTRAINT dental_especialista_especialidad_fk_especialista FOREIGN KEY (idespecialista) REFERENCES dental_especialistas(id) ON DELETE CASCADE,
    CONSTRAINT dental_especialista_especialidad_fk_especialidad FOREIGN KEY (idespecialidad) REFERENCES dental_especialidades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

-- Tabla: dental_tratamientos_paciente
CREATE TABLE dental_tratamientos_paciente (
    id INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
    idpaciente INTEGER UNSIGNED NOT NULL,
    referencia_servicio VARCHAR(50),
    idespecialista INTEGER UNSIGNED,
    idpresupuesto INTEGER,
    idfactura INTEGER,
    fecha_inicio DATE,
    fecha_fin DATE,
    estado_clinico VARCHAR(20) DEFAULT 'propuesto',
    estado_economico VARCHAR(20) DEFAULT 'pendiente',
    precio DECIMAL(12,2),
    descuento DECIMAL(5,2) DEFAULT 0,
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT dental_tratamientos_paciente_fk_paciente FOREIGN KEY (idpaciente) REFERENCES dental_pacientes(id) ON DELETE CASCADE,
    CONSTRAINT dental_tratamientos_paciente_fk_especialista FOREIGN KEY (idespecialista) REFERENCES dental_especialistas(id) ON DELETE SET NULL,
    CONSTRAINT dental_tratamientos_paciente_fk_presupuesto FOREIGN KEY (idpresupuesto) REFERENCES presupuestoscli(idpresupuesto) ON DELETE SET NULL,
    CONSTRAINT dental_tratamientos_paciente_fk_factura FOREIGN KEY (idfactura) REFERENCES facturascli(idfactura) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

-- Tabla: dental_citas
CREATE TABLE dental_citas (
    id INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
    idpaciente INTEGER UNSIGNED NOT NULL,
    idespecialista INTEGER UNSIGNED NOT NULL,
    idgabinete INTEGER UNSIGNED NOT NULL,
    idtratamiento INTEGER UNSIGNED,
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    duracion INTEGER DEFAULT 30,
    motivo TEXT,
    estado VARCHAR(20) DEFAULT 'pendiente',
    observaciones TEXT,
    recordatorio_enviado BOOLEAN DEFAULT FALSE,
    confirmada_paciente BOOLEAN DEFAULT FALSE,
    created_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT dental_citas_fk_paciente FOREIGN KEY (idpaciente) REFERENCES dental_pacientes(id),
    CONSTRAINT dental_citas_fk_especialista FOREIGN KEY (idespecialista) REFERENCES dental_especialistas(id),
    CONSTRAINT dental_citas_fk_gabinete FOREIGN KEY (idgabinete) REFERENCES dental_gabinetes(id),
    CONSTRAINT dental_citas_fk_tratamiento FOREIGN KEY (idtratamiento) REFERENCES dental_tratamientos_paciente(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

-- Tabla: dental_historial
CREATE TABLE dental_historial (
    id INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
    idpaciente INTEGER UNSIGNED NOT NULL,
    idespecialista INTEGER UNSIGNED,
    idcita INTEGER UNSIGNED,
    idtratamiento INTEGER UNSIGNED,
    fecha DATE NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    motivo_consulta TEXT,
    diagnostico TEXT,
    tratamiento_recomendado TEXT,
    tratamiento_realizado TEXT,
    medicacion_prescrita TEXT,
    observaciones_clinicas TEXT,
    proxima_revision DATE,
    estado VARCHAR(20) DEFAULT 'activo',
    created_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT dental_historial_fk_paciente FOREIGN KEY (idpaciente) REFERENCES dental_pacientes(id) ON DELETE CASCADE,
    CONSTRAINT dental_historial_fk_especialista FOREIGN KEY (idespecialista) REFERENCES dental_especialistas(id) ON DELETE SET NULL,
    CONSTRAINT dental_historial_fk_cita FOREIGN KEY (idcita) REFERENCES dental_citas(id) ON DELETE SET NULL,
    CONSTRAINT dental_historial_fk_tratamiento FOREIGN KEY (idtratamiento) REFERENCES dental_tratamientos_paciente(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

-- Tabla: dental_bloqueos_agenda
CREATE TABLE dental_bloqueos_agenda (
    id INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
    idespecialista INTEGER UNSIGNED,
    idgabinete INTEGER UNSIGNED,
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    motivo VARCHAR(200) NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    created_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT dental_bloqueos_agenda_fk_especialista FOREIGN KEY (idespecialista) REFERENCES dental_especialistas(id) ON DELETE CASCADE,
    CONSTRAINT dental_bloqueos_agenda_fk_gabinete FOREIGN KEY (idgabinete) REFERENCES dental_gabinetes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

-- Tabla: dental_archivos
CREATE TABLE dental_archivos (
    id INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
    idpaciente INTEGER UNSIGNED NOT NULL,
    idespecialista INTEGER UNSIGNED,
    idcita INTEGER UNSIGNED,
    idtratamiento INTEGER UNSIGNED,
    categoria VARCHAR(50) NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    extension VARCHAR(10) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    tamano INTEGER NOT NULL,
    ruta VARCHAR(500) NOT NULL,
    hash_archivo VARCHAR(64),
    descripcion TEXT,
    estado VARCHAR(20) DEFAULT 'activo',
    created_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT dental_archivos_fk_paciente FOREIGN KEY (idpaciente) REFERENCES dental_pacientes(id) ON DELETE CASCADE,
    CONSTRAINT dental_archivos_fk_especialista FOREIGN KEY (idespecialista) REFERENCES dental_especialistas(id) ON DELETE SET NULL,
    CONSTRAINT dental_archivos_fk_cita FOREIGN KEY (idcita) REFERENCES dental_citas(id) ON DELETE SET NULL,
    CONSTRAINT dental_archivos_fk_tratamiento FOREIGN KEY (idtratamiento) REFERENCES dental_tratamientos_paciente(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

SET FOREIGN_KEY_CHECKS = 1;
