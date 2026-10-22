-- Icono de la actividad: imagen pequeña (subida en el formulario de crear/editar actividad)
-- que se muestra en forma circular sobre la barra de la actividad en la Línea de tiempo activa
-- y en la columna «Icono» del programa. El archivo vive en public/build/img/actividades-iconos/.
ALTER TABLE actividades ADD COLUMN icono VARCHAR(120) NULL;
