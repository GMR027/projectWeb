-- Editor: fondo personalizado (imagen propia) con sus ajustes (modo, escala, posición, repeticiones, opacidad)
ALTER TABLE editor_graficos ADD COLUMN fondo_imagen VARCHAR(100) DEFAULT NULL AFTER fondo_color;
ALTER TABLE editor_graficos ADD COLUMN fondo_config TEXT AFTER fondo_imagen;
