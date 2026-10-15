-- Submittal: fecha de respuesta compartida con el MSR
ALTER TABLE submittals ADD COLUMN fecha_respuesta DATE DEFAULT NULL AFTER estatus;
UPDATE submittals s JOIN (SELECT submittal_id, MAX(fecha_respuesta) f FROM msr_items GROUP BY submittal_id) x ON x.submittal_id = s.id SET s.fecha_respuesta = x.f;
-- Estatus del submittal según lo que ya trae el MSR: algún RA → rechazado; todos A → aprobado; si no, enviado
UPDATE submittals s JOIN (
    SELECT submittal_id, SUM(status = 'RA') ra, SUM(status = 'A') a, COUNT(*) n FROM msr_items GROUP BY submittal_id
) x ON x.submittal_id = s.id
SET s.estatus = CASE WHEN x.ra > 0 THEN 'rechazado' WHEN x.a = x.n THEN 'aprobado' ELSE 'enviado' END;

-- Catálogo de especialidades (por proyecto)
CREATE TABLE IF NOT EXISTS especialidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    UNIQUE KEY uq_esp (proyecto_id, nombre),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Materiales sin especialidad: la del submittal donde van, o la sección del MSR
UPDATE materiales m SET m.especialidad = COALESCE(
    (SELECT s.especialidad FROM submittal_materiales sm JOIN submittals s ON s.id = sm.submittal_id WHERE sm.material_id = m.id AND s.especialidad <> '' LIMIT 1),
    (SELECT i.seccion FROM msr_items i WHERE i.material_id = m.id AND i.seccion <> '' LIMIT 1),
    '') WHERE m.especialidad = '';

INSERT IGNORE INTO especialidades (proyecto_id, nombre)
    SELECT proyecto_id, especialidad FROM materiales WHERE especialidad <> ''
    UNION SELECT proyecto_id, especialidad FROM submittals WHERE especialidad <> '';
