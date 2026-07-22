import * as XLSX from 'xlsx';

const headerAliases: Record<string, string> = {
    actividad: 'codigo_actividad_economica',
    actividad_economica: 'codigo_actividad_economica',
    actividad_siat: 'codigo_actividad_economica',
    celular: 'telefono',
    codigo: 'codigo',
    codigo_actividad: 'codigo_actividad_economica',
    codigo_barras: 'codigo_barras',
    codigo_producto: 'codigo_generico',
    codigo_producto_sin: 'codigo_producto_sin',
    codigo_unidad: 'codigo_unidad_medida_siat',
    codigo_unidad_medida_siat: 'codigo_unidad_medida_siat',
    complemento: 'complemento',
    correo: 'correo',
    descripcion: 'descripcion',
    direccion: 'direccion',
    documento: 'nit_ci',
    email: 'correo',
    estado: 'estado',
    marca: 'marca',
    nit: 'nit_ci',
    nit_ci: 'nit_ci',
    nombre: 'nombre',
    precio: 'precio_base',
    precio_base: 'precio_base',
    producto: 'nombre',
    producto_sin: 'codigo_producto_sin',
    razon_social: 'razon_social',
    stock_minimo: 'stock_minimo',
    tipo_documento: 'tipo_documento_identidad',
    tipo_documento_identidad: 'tipo_documento_identidad',
    unidad: 'codigo_unidad_medida_siat',
    unidad_siat: 'codigo_unidad_medida_siat',
};

const normalizeHeader = (value: unknown) => {
    const key = String(value ?? '')
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLocaleLowerCase('es')
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');

    return headerAliases[key] ?? key;
};

export async function parseExcelRows(
    file: File,
): Promise<Record<string, unknown>[]> {
    const buffer = await file.arrayBuffer();
    const workbook = XLSX.read(buffer, { type: 'array' });
    const sheetName = workbook.SheetNames[0];

    if (!sheetName) {
        return [];
    }

    const rawRows = XLSX.utils.sheet_to_json<Record<string, unknown>>(
        workbook.Sheets[sheetName],
        {
            defval: '',
        },
    );

    return rawRows
        .map((row) =>
            Object.fromEntries(
                Object.entries(row)
                    .map(([key, value]) => [normalizeHeader(key), value])
                    .filter(
                        ([key, value]) =>
                            key !== '' && String(value ?? '').trim() !== '',
                    ),
            ),
        )
        .filter((row) => Object.keys(row).length > 0);
}
