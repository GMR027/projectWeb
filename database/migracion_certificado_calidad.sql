-- Campo de texto «Certificado calidad» en el catálogo de materiales
ALTER TABLE materiales ADD COLUMN certificado_calidad VARCHAR(255) DEFAULT NULL AFTER ficha_tecnica;
