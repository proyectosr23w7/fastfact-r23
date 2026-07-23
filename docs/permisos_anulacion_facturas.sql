START TRANSACTION;

SET @admin_role_id := (
    SELECT `id`
    FROM `roles`
    WHERE `slug` = 'administrador'
    LIMIT 1
);

INSERT INTO `permisos` (`nombre`, `slug`, `descripcion`, `modulo`, `estado`, `created_at`, `updated_at`)
SELECT 'Anular facturas',
       'facturacion.facturas.anular',
       'Permite ejecutar la anulacion de facturas aceptadas u observadas.',
       'facturacion',
       1,
       NOW(),
       NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM `permisos`
    WHERE `slug` = 'facturacion.facturas.anular'
);

UPDATE `permisos`
SET `nombre` = 'Anular facturas',
    `descripcion` = 'Permite ejecutar la anulacion de facturas aceptadas u observadas.',
    `modulo` = 'facturacion',
    `estado` = 1,
    `updated_at` = NOW()
WHERE `slug` = 'facturacion.facturas.anular';

INSERT INTO `permisos` (`nombre`, `slug`, `descripcion`, `modulo`, `estado`, `created_at`, `updated_at`)
SELECT 'Revertir anulacion de facturas',
       'facturacion.facturas.revertir_anulacion',
       'Permite ejecutar la reversion de anulacion de facturas aceptadas por SIAT.',
       'facturacion',
       1,
       NOW(),
       NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM `permisos`
    WHERE `slug` = 'facturacion.facturas.revertir_anulacion'
);

UPDATE `permisos`
SET `nombre` = 'Revertir anulacion de facturas',
    `descripcion` = 'Permite ejecutar la reversion de anulacion de facturas aceptadas por SIAT.',
    `modulo` = 'facturacion',
    `estado` = 1,
    `updated_at` = NOW()
WHERE `slug` = 'facturacion.facturas.revertir_anulacion';

INSERT INTO `permission_role` (`role_id`, `permission_id`, `created_at`, `updated_at`)
SELECT @admin_role_id, `p`.`id`, NOW(), NOW()
FROM `permisos` AS `p`
WHERE @admin_role_id IS NOT NULL
  AND `p`.`slug` IN (
      'facturacion.facturas.anular',
      'facturacion.facturas.revertir_anulacion'
  )
  AND NOT EXISTS (
      SELECT 1
      FROM `permission_role` AS `pr`
      WHERE `pr`.`role_id` = @admin_role_id
        AND `pr`.`permission_id` = `p`.`id`
  );

COMMIT;
