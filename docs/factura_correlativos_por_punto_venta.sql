-- Patch sin consola: correlativos de factura por punto de venta
-- Ejecutar en phpMyAdmin sobre la base de datos del subdominio.
-- Fecha: 2026-07-07

START TRANSACTION;

CREATE TABLE IF NOT EXISTS factura_correlativos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sucursal_id BIGINT UNSIGNED NOT NULL,
    punto_venta_id BIGINT UNSIGNED NOT NULL,
    ambiente_facturacion VARCHAR(20) NOT NULL DEFAULT 'piloto',
    tipo_facturacion TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ultimo_numero BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY factura_correlativos_contexto_unique (
        sucursal_id,
        punto_venta_id,
        ambiente_facturacion,
        tipo_facturacion
    ),
    CONSTRAINT factura_correlativos_sucursal_id_foreign
        FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE CASCADE,
    CONSTRAINT factura_correlativos_punto_venta_id_foreign
        FOREIGN KEY (punto_venta_id) REFERENCES puntos_venta(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inicializa cada contexto con el mayor numero ya emitido.
-- Si en pruebas anteriores PV 0 llego a 5 y PV 1 llego a 6,
-- los siguientes seran 6 y 7 respectivamente, evitando reutilizar numeros.
INSERT INTO factura_correlativos (
    sucursal_id,
    punto_venta_id,
    ambiente_facturacion,
    tipo_facturacion,
    ultimo_numero,
    created_at,
    updated_at
)
SELECT
    f.sucursal_id,
    f.punto_venta_id,
    COALESCE(f.ambiente_facturacion, 'piloto') AS ambiente_facturacion,
    COALESCE(f.tipo_facturacion, 0) AS tipo_facturacion,
    MAX(f.numero_factura) AS ultimo_numero,
    NOW(),
    NOW()
FROM facturas f
WHERE f.cafc_id IS NULL
  AND f.numero_factura IS NOT NULL
  AND f.sucursal_id IS NOT NULL
  AND f.punto_venta_id IS NOT NULL
GROUP BY
    f.sucursal_id,
    f.punto_venta_id,
    COALESCE(f.ambiente_facturacion, 'piloto'),
    COALESCE(f.tipo_facturacion, 0)
ON DUPLICATE KEY UPDATE
    ultimo_numero = GREATEST(factura_correlativos.ultimo_numero, VALUES(ultimo_numero)),
    updated_at = NOW();

-- Marca la migracion como aplicada por si luego se habilita consola.
SET @next_batch := COALESCE((SELECT MAX(batch) + 1 FROM migrations), 1);

INSERT INTO migrations (migration, batch)
SELECT '2026_07_07_000004_create_factura_correlativos_table', @next_batch
WHERE NOT EXISTS (
    SELECT 1
    FROM migrations
    WHERE migration = '2026_07_07_000004_create_factura_correlativos_table'
);

COMMIT;

-- Verificacion
SELECT
    fc.sucursal_id,
    s.nombre AS sucursal,
    fc.punto_venta_id,
    pv.codigo AS punto_venta_codigo,
    pv.nombre AS punto_venta,
    fc.ambiente_facturacion,
    fc.tipo_facturacion,
    fc.ultimo_numero
FROM factura_correlativos fc
JOIN sucursales s ON s.id = fc.sucursal_id
JOIN puntos_venta pv ON pv.id = fc.punto_venta_id
ORDER BY fc.sucursal_id, pv.codigo, fc.ambiente_facturacion, fc.tipo_facturacion;
