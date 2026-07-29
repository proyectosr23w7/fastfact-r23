-- Limpieza puntual para Jadfemar: cliente duplicado con documento 6130451016.
-- Ejecutar en la base de datos del subdominio Jadfemar desde phpMyAdmin.
-- No borra clientes: conserva un cliente principal, mueve sus facturas y desactiva duplicados.

SET @documento := '6130451016';

SELECT
    id,
    codigo,
    razon_social,
    nit_ci,
    tipo_documento_identidad,
    complemento,
    estado,
    created_at,
    updated_at
FROM clientes
WHERE nit_ci = @documento
ORDER BY estado DESC, id ASC;

SET @cliente_principal_id := (
    SELECT id
    FROM clientes
    WHERE nit_ci = @documento
    ORDER BY estado DESC, id ASC
    LIMIT 1
);

UPDATE facturas
SET cliente_id = @cliente_principal_id
WHERE cliente_id IN (
    SELECT id
    FROM (
        SELECT id
        FROM clientes
        WHERE nit_ci = @documento
          AND id <> @cliente_principal_id
    ) AS clientes_duplicados
);

UPDATE clientes
SET estado = 0,
    updated_at = NOW()
WHERE nit_ci = @documento
  AND id <> @cliente_principal_id;

SELECT
    @cliente_principal_id AS cliente_principal_id,
    COUNT(*) AS clientes_con_documento,
    SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) AS clientes_activos
FROM clientes
WHERE nit_ci = @documento;
