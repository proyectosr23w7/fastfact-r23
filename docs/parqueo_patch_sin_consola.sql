-- Patch sin consola para Parqueo FastFact R23
-- Ejecutar en phpMyAdmin sobre la base de datos del subdominio parqueo.
-- Fecha: 2026-07-07

START TRANSACTION;

-- 1) Agregar contexto operativo al usuario si aun no existe.
SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'punto_venta_id'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE users ADD COLUMN punto_venta_id BIGINT UNSIGNED NULL AFTER sucursal_id',
    'SELECT "users.punto_venta_id ya existe"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND CONSTRAINT_NAME = 'users_punto_venta_id_foreign'
);

SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE users ADD CONSTRAINT users_punto_venta_id_foreign FOREIGN KEY (punto_venta_id) REFERENCES puntos_venta(id) ON DELETE SET NULL',
    'SELECT "FK users_punto_venta_id_foreign ya existe"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Asignar permisos API al rol cajero.
SET @cashier_role_id := (
    SELECT id
    FROM roles
    WHERE slug = 'cajero'
    LIMIT 1
);

INSERT INTO permission_role (role_id, permission_id, created_at, updated_at)
SELECT @cashier_role_id, p.id, NOW(), NOW()
FROM permisos p
WHERE @cashier_role_id IS NOT NULL
  AND p.slug IN (
      'integracion.productos.manage',
      'integracion.clientes.manage',
      'integracion.facturas.emitir',
      'integracion.facturas.anular',
      'integracion.facturas.revertir',
      'integracion.cuis.manage',
      'integracion.cufd.manage'
  )
  AND NOT EXISTS (
      SELECT 1
      FROM permission_role pr
      WHERE pr.role_id = @cashier_role_id
        AND pr.permission_id = p.id
  );

-- 3) Asignar sucursal y punto de venta a los 4 usuarios de prueba.
UPDATE users
SET sucursal_id = 1, punto_venta_id = 1
WHERE email IN ('admin.mall@parqueo.local', 'parqueo@parqueo.local');

UPDATE users
SET sucursal_id = 1, punto_venta_id = 2
WHERE email IN ('admin.disdema@parqueo.local', 'play.up@parqueo.local');

-- 4) Regenerar tokens de prueba. El DELETE evita duplicados si vuelves a ejecutar este SQL.
DELETE FROM integration_api_tokens
WHERE name IN ('API Admin Mall', 'API Admin Disdema', 'API Parqueo', 'API Play Up');

INSERT INTO integration_api_tokens (user_id, name, token_hash, abilities, created_at, updated_at)
SELECT u.id, 'API Admin Mall',
       'a11d637024d8bd10b35ef4ab2a1d129a042c87aaffabdd5af384189d224f4303',
       '["integracion.productos.manage","integracion.clientes.manage","integracion.facturas.emitir","integracion.facturas.anular","integracion.facturas.revertir","integracion.cuis.manage","integracion.cufd.manage"]',
       NOW(), NOW()
FROM users u
WHERE u.email = 'admin.mall@parqueo.local'
LIMIT 1;

INSERT INTO integration_api_tokens (user_id, name, token_hash, abilities, created_at, updated_at)
SELECT u.id, 'API Admin Disdema',
       '12a5704899afdb0fec91def15db35d436d629aa26107a9153edf13091a1f1266',
       '["integracion.productos.manage","integracion.clientes.manage","integracion.facturas.emitir","integracion.facturas.anular","integracion.facturas.revertir","integracion.cuis.manage","integracion.cufd.manage"]',
       NOW(), NOW()
FROM users u
WHERE u.email = 'admin.disdema@parqueo.local'
LIMIT 1;

INSERT INTO integration_api_tokens (user_id, name, token_hash, abilities, created_at, updated_at)
SELECT u.id, 'API Parqueo',
       '1e18a8f5585850e4333df4b22436d5457c0dbc70c93e256e24da6d4d75827fb7',
       '["integracion.productos.manage","integracion.clientes.manage","integracion.facturas.emitir","integracion.facturas.anular","integracion.facturas.revertir","integracion.cuis.manage","integracion.cufd.manage"]',
       NOW(), NOW()
FROM users u
WHERE u.email = 'parqueo@parqueo.local'
LIMIT 1;

INSERT INTO integration_api_tokens (user_id, name, token_hash, abilities, created_at, updated_at)
SELECT u.id, 'API Play Up',
       'bd12e2dfa519f7990f365b0d9f7ba6d9c18654762dac832fa9894454585d8fdb',
       '["integracion.productos.manage","integracion.clientes.manage","integracion.facturas.emitir","integracion.facturas.anular","integracion.facturas.revertir","integracion.cuis.manage","integracion.cufd.manage"]',
       NOW(), NOW()
FROM users u
WHERE u.email = 'play.up@parqueo.local'
LIMIT 1;

-- 5) Registrar migraciones como aplicadas para evitar que se repitan si luego tienes consola.
SET @next_batch := COALESCE((SELECT MAX(batch) + 1 FROM migrations), 1);

INSERT INTO migrations (migration, batch)
SELECT '2026_07_07_000002_add_operational_context_to_users', @next_batch
WHERE NOT EXISTS (
    SELECT 1 FROM migrations
    WHERE migration = '2026_07_07_000002_add_operational_context_to_users'
);

INSERT INTO migrations (migration, batch)
SELECT '2026_07_07_000003_assign_integration_permissions_to_cashiers', @next_batch
WHERE NOT EXISTS (
    SELECT 1 FROM migrations
    WHERE migration = '2026_07_07_000003_assign_integration_permissions_to_cashiers'
);

COMMIT;

-- Verificacion rapida.
SELECT id, name, email, sucursal_id, punto_venta_id
FROM users
WHERE email IN (
    'admin.mall@parqueo.local',
    'admin.disdema@parqueo.local',
    'parqueo@parqueo.local',
    'play.up@parqueo.local'
)
ORDER BY email;

SELECT u.email, t.name, t.created_at, t.revoked_at
FROM integration_api_tokens t
JOIN users u ON u.id = t.user_id
WHERE t.name IN ('API Admin Mall', 'API Admin Disdema', 'API Parqueo', 'API Play Up')
ORDER BY u.email;
