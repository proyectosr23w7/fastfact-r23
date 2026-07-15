<?php

return [
    'soap_version' => env('SIAT_SOAP_VERSION', 'v2'),

    'qr_urls' => [
        'piloto' => env('SIAT_PILOTO_QR_URL', 'https://pilotosiat.impuestos.gob.bo/consulta/QR'),
        'produccion' => env('SIAT_PRODUCCION_QR_URL', 'https://siat.impuestos.gob.bo/consulta/QR'),
    ],

    'files' => [
        'certificate_p12_path' => env('SIAT_CERTIFICATE_P12_PATH', storage_path('app/siat/certificados/certificado.p12')),
        'certificate_p12_password' => env('SIAT_CERTIFICATE_P12_PASSWORD', ''),
        'xsd' => [
            'factura_electronica_compra_venta' => resource_path('siat/xsd/facturaElectronicaCompraVenta.xsd'),
            'factura_computarizada_compra_venta' => resource_path('siat/xsd/facturaComputarizadaCompraVenta.xsd'),
        ],
    ],

    'environments' => [
        'piloto' => [
            'label' => 'Piloto',
            'codigo_ambiente' => 2,
            'token_header' => 'Authorization',
            'token_prefix' => 'Token',
            'modules' => [
                'codigos' => env('SIAT_PILOTO_CODIGOS_WSDL', 'https://pilotosiatservicios.impuestos.gob.bo/v2/FacturacionCodigos?wsdl'),
                'sincronizacion' => env('SIAT_PILOTO_SINCRONIZACION_WSDL', 'https://pilotosiatservicios.impuestos.gob.bo/v2/FacturacionSincronizacion?wsdl'),
                'operaciones' => env('SIAT_PILOTO_OPERACIONES_WSDL', 'https://pilotosiatservicios.impuestos.gob.bo/v2/FacturacionOperaciones?wsdl'),
                'facturacion_computarizada' => env('SIAT_PILOTO_FACTURACION_COMPUTARIZADA_WSDL', 'https://pilotosiatservicios.impuestos.gob.bo/v2/ServicioFacturacionComputarizada?wsdl'),
                'facturacion_electronica' => env('SIAT_PILOTO_FACTURACION_ELECTRONICA_WSDL', 'https://pilotosiatservicios.impuestos.gob.bo/v2/ServicioFacturacionElectronica?wsdl'),
            ],
        ],
        'produccion' => [
            'label' => 'Produccion',
            'codigo_ambiente' => 1,
            'token_header' => 'Authorization',
            'token_prefix' => 'Token',
            'modules' => [
                'codigos' => env('SIAT_PRODUCCION_CODIGOS_WSDL', 'https://siatrest.impuestos.gob.bo/v2/FacturacionCodigos?wsdl'),
                'sincronizacion' => env('SIAT_PRODUCCION_SINCRONIZACION_WSDL', 'https://siatrest.impuestos.gob.bo/v2/FacturacionSincronizacion?wsdl'),
                'operaciones' => env('SIAT_PRODUCCION_OPERACIONES_WSDL', 'https://siatrest.impuestos.gob.bo/v2/FacturacionOperaciones?wsdl'),
                'facturacion_computarizada' => env('SIAT_PRODUCCION_FACTURACION_COMPUTARIZADA_WSDL', 'https://siatrest.impuestos.gob.bo/v2/ServicioFacturacionComputarizada?wsdl'),
                'facturacion_electronica' => env('SIAT_PRODUCCION_FACTURACION_ELECTRONICA_WSDL', 'https://siatrest.impuestos.gob.bo/v2/ServicioFacturacionElectronica?wsdl'),
            ],
        ],
    ],

    'module_labels' => [
        'codigos' => 'Codigos',
        'sincronizacion' => 'Sincronizacion',
        'operaciones' => 'Operaciones',
        'facturacion_electronica' => 'Facturacion electronica',
        'facturacion_computarizada' => 'Facturacion computarizada',
    ],

    'soap_methods' => [
        'codigos' => [
            'verificar_comunicacion' => 'verificarComunicacion',
            'solicitud_cuis' => ['cuis', 'solicitudCuis'],
            'solicitud_cufd' => ['cufd', 'solicitudCufd'],
        ],
        'sincronizacion' => [
            // Los nombres se dejan centralizados y ajustables porque SIAT versiona y publica
            // sus operaciones por WSDL. Si cambia alguno, basta tocar este archivo.
            'actividades' => 'sincronizarActividades',
            'productos_servicios' => 'sincronizarListaProductosServicios',
            'motivos_anulacion' => 'sincronizarParametricaMotivoAnulacion',
            'leyendas' => ['sincronizarListaLeyendasFactura', 'sincronizarListaLeyendas'],
            'documentos_identidad' => 'sincronizarParametricaTipoDocumentoIdentidad',
            'unidades_medida' => 'sincronizarParametricaUnidadMedida',
            'monedas' => 'sincronizarParametricaTipoMoneda',
            'metodos_pago' => 'sincronizarParametricaTipoMetodoPago',
        ],
        'operaciones' => [
            'registro_evento_significativo' => 'registroEventoSignificativo',
        ],
        'facturacion' => [
            'recepcion_factura' => 'recepcionFactura',
            'recepcion_paquete_factura' => 'recepcionPaqueteFactura',
            'validacion_recepcion_paquete_factura' => 'validacionRecepcionPaqueteFactura',
            'verificacion_estado_factura' => 'verificacionEstadoFactura',
            'anulacion_factura' => 'anulacionFactura',
            'reversion_anulacion_factura' => 'reversionAnulacionFactura',
        ],
    ],

    'catalogs' => [
        'actividades' => [
            'method' => 'actividades',
            'response_keys' => ['listaActividades', 'listaCodigos'],
        ],
        'productos_servicios' => [
            'method' => 'productos_servicios',
            'response_keys' => ['listaCodigos', 'listaProductosServicios'],
        ],
        'motivos_anulacion' => [
            'method' => 'motivos_anulacion',
            'response_keys' => ['listaCodigos', 'listaMotivoAnulacion'],
        ],
        'leyendas' => [
            'method' => 'leyendas',
            'response_keys' => ['listaLeyendasFactura', 'listaLeyendas', 'listaCodigos'],
        ],
        'documentos_identidad' => [
            'method' => 'documentos_identidad',
            'response_keys' => ['listaCodigos', 'listaDocumentosIdentidad'],
        ],
        'unidades_medida' => [
            'method' => 'unidades_medida',
            'response_keys' => ['listaCodigos', 'listaUnidadMedida'],
        ],
        'monedas' => [
            'method' => 'monedas',
            'response_keys' => ['listaCodigos', 'listaMonedas', 'listaTipoMoneda'],
        ],
        'metodos_pago' => [
            'method' => 'metodos_pago',
            'response_keys' => ['listaCodigos', 'listaMetodoPago'],
        ],
    ],
];
