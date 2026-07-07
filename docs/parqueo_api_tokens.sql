-- Tokens de prueba API - Parqueo FastFact R23
-- Ejecutar una sola vez en phpMyAdmin despues de actualizar el codigo
-- y correr: php artisan migrate --force

UPDATE users SET sucursal_id = 1, punto_venta_id = 1
WHERE email IN ('admin.mall@parqueo.local', 'parqueo@parqueo.local');

UPDATE users SET sucursal_id = 1, punto_venta_id = 2
WHERE email IN ('admin.disdema@parqueo.local', 'play.up@parqueo.local');

DELETE FROM integration_api_tokens
WHERE name IN ('API Admin Mall', 'API Admin Disdema', 'API Parqueo', 'API Play Up');

INSERT INTO integration_api_tokens (user_id, name, token_hash, abilities, created_at, updated_at)
SELECT u.id, 'API Admin Mall',
       'a11d637024d8bd10b35ef4ab2a1d129a042c87aaffabdd5af384189d224f4303',
       '["integracion.productos.manage","integracion.clientes.manage","integracion.facturas.emitir","integracion.facturas.anular","integracion.facturas.revertir","integracion.cuis.manage","integracion.cufd.manage"]',
       NOW(), NOW()
FROM users u WHERE u.email = 'admin.mall@parqueo.local' LIMIT 1;

INSERT INTO integration_api_tokens (user_id, name, token_hash, abilities, created_at, updated_at)
SELECT u.id, 'API Admin Disdema',
       '12a5704899afdb0fec91def15db35d436d629aa26107a9153edf13091a1f1266',
       '["integracion.productos.manage","integracion.clientes.manage","integracion.facturas.emitir","integracion.facturas.anular","integracion.facturas.revertir","integracion.cuis.manage","integracion.cufd.manage"]',
       NOW(), NOW()
FROM users u WHERE u.email = 'admin.disdema@parqueo.local' LIMIT 1;

INSERT INTO integration_api_tokens (user_id, name, token_hash, abilities, created_at, updated_at)
SELECT u.id, 'API Parqueo',
       '1e18a8f5585850e4333df4b22436d5457c0dbc70c93e256e24da6d4d75827fb7',
       '["integracion.productos.manage","integracion.clientes.manage","integracion.facturas.emitir","integracion.facturas.anular","integracion.facturas.revertir","integracion.cuis.manage","integracion.cufd.manage"]',
       NOW(), NOW()
FROM users u WHERE u.email = 'parqueo@parqueo.local' LIMIT 1;

INSERT INTO integration_api_tokens (user_id, name, token_hash, abilities, created_at, updated_at)
SELECT u.id, 'API Play Up',
       'bd12e2dfa519f7990f365b0d9f7ba6d9c18654762dac832fa9894454585d8fdb',
       '["integracion.productos.manage","integracion.clientes.manage","integracion.facturas.emitir","integracion.facturas.anular","integracion.facturas.revertir","integracion.cuis.manage","integracion.cufd.manage"]',
       NOW(), NOW()
FROM users u WHERE u.email = 'play.up@parqueo.local' LIMIT 1;
