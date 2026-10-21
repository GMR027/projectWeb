-- Cuántos registros se muestran por página en las listas del proyecto (Ajustes del proyecto). Por defecto 10.
ALTER TABLE proyectos ADD COLUMN registros_por_pagina SMALLINT NOT NULL DEFAULT 10;
