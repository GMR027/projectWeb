-- Clima por proyecto (Open-Meteo): ubicación geocodificada y días de pronóstico / registro real, para anomalías y días no hábiles probables.
CREATE TABLE IF NOT EXISTS clima_proyecto (
    proyecto_id INT PRIMARY KEY,
    lugar VARCHAR(200) NOT NULL DEFAULT '',
    lat DECIMAL(8,5) NOT NULL,
    lon DECIMAL(8,5) NOT NULL,
    actualizado DATETIME NOT NULL,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clima_dias (
    proyecto_id INT NOT NULL,
    fecha DATE NOT NULL,
    precip_mm DECIMAL(6,1) NOT NULL DEFAULT 0,
    prob INT NULL,                                   -- probabilidad de lluvia (solo pronóstico)
    viento_kmh DECIMAL(5,1) NOT NULL DEFAULT 0,
    tmax DECIMAL(4,1) NULL,
    tmin DECIMAL(4,1) NULL,
    tipo ENUM('pronostico','real') NOT NULL,
    actualizado DATETIME NOT NULL,
    PRIMARY KEY (proyecto_id, fecha),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
