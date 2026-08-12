import { ApiError } from '@/src/services/apiClient';
import { articuloService } from '@/src/services/articuloService';
import { reactive } from 'vue';

const initialPrice = () => ({
    cantidad_minima: 1,
    precio: '',
    tipo_precio: 'general',
    estado: true,
});

const initialForm = () => ({
    codigo_generico: '',
    codigo_barras: '',
    nombre: '',
    descripcion: '',
    tags: '',
    alias: '',
    atributos: [] as Record<string, unknown>[],
    categoria_id: '',
    marca_id: '',
    unidad_medida_id: '',
    codigo_actividad_economica: '',
    codigo_producto_sin: '',
    codigo_unidad_medida_siat: '',
    stock_minimo: 0,
    costo: 0,
    precio_base: 0,
    estado: true,
    precios: [] as Record<string, unknown>[],
});

const parseTerms = (value: unknown) =>
    String(value ?? '')
        .split(/[\n,;]+/)
        .map((term) => term.trim())
        .filter(Boolean)
        .filter((term, index, terms) => {
            const normalized = term.toLocaleLowerCase('es');
            return (
                terms.findIndex(
                    (candidate) =>
                        candidate.toLocaleLowerCase('es') === normalized,
                ) === index
            );
        });

const formatTerms = (value: unknown) =>
    Array.isArray(value) ? value.join(', ') : String(value ?? '');

const formatAttributes = (value: unknown) =>
    Array.isArray(value)
        ? value.map((item) => ({
              atributo: String(
                  (item as Record<string, unknown>).atributo ?? '',
              ),
              valor: String((item as Record<string, unknown>).valor ?? ''),
          }))
        : [];

const parseAttributes = (value: Record<string, unknown>[]) =>
    value
        .map((item) => ({
            atributo: String(item.atributo ?? '').trim(),
            valor: String(item.valor ?? '').trim(),
        }))
        .filter((item) => item.atributo && item.valor)
        .filter((item, index, items) => {
            const normalized =
                `${item.atributo}|${item.valor}`.toLocaleLowerCase('es');
            return (
                items.findIndex(
                    (candidate) =>
                        `${candidate.atributo}|${candidate.valor}`.toLocaleLowerCase(
                            'es',
                        ) === normalized,
                ) === index
            );
        });

export function useArticuloStore() {
    const state = reactive({
        items: [] as Record<string, unknown>[],
        meta: {} as Record<string, unknown>,
        form: initialForm(),
        loading: false,
        saving: false,
        deleting: false,
        editingId: null as number | null,
        errors: {} as Record<string, string[]>,
        loadError: '',
        actionError: '',
        search: '',
        filters: {
            categoria_id: '',
            marca_id: '',
            stock: '',
        },
    });

    const resetForm = () => {
        state.form = initialForm();
        state.errors = {};
        state.editingId = null;
    };

    const load = async () => {
        state.loading = true;
        state.loadError = '';

        try {
            const response = await articuloService.list({
                search: state.search.trim(),
                ...state.filters,
            });
            state.items = response.data;
            state.meta = response.meta ?? {};
        } catch (error) {
            state.loadError =
                error instanceof Error
                    ? error.message
                    : 'No se pudieron cargar los artículos.';
        } finally {
            state.loading = false;
        }
    };

    const startCreate = () => {
        resetForm();
    };

    const startEdit = (item: Record<string, unknown>) => {
        state.editingId = Number(item.id);
        state.errors = {};
        state.form = {
            codigo_generico: String(item.codigo_generico ?? ''),
            codigo_barras: String(item.codigo_barras ?? ''),
            nombre: String(item.nombre ?? ''),
            descripcion: String(item.descripcion ?? ''),
            tags: formatTerms(item.tags),
            alias: formatTerms(item.alias),
            atributos: formatAttributes(item.atributos),
            categoria_id: String(item.categoria_id ?? ''),
            marca_id: String(item.marca_id ?? ''),
            unidad_medida_id: String(item.unidad_medida_id ?? ''),
            codigo_actividad_economica: String(
                item.codigo_actividad_economica ?? '',
            ),
            codigo_producto_sin: String(item.codigo_producto_sin ?? ''),
            codigo_unidad_medida_siat: String(
                item.codigo_unidad_medida_siat ?? '',
            ),
            stock_minimo: Number(item.stock_minimo ?? 0),
            costo: Number(item.costo ?? 0),
            precio_base: Number(item.precio_base ?? 0),
            estado: Boolean(item.estado ?? true),
            precios: (
                (item.precios as Record<string, unknown>[] | undefined) ?? []
            ).map((precio) => ({
                cantidad_minima: Number(precio.cantidad_minima ?? 1),
                precio: Number(precio.precio ?? 0),
                tipo_precio: String(precio.tipo_precio ?? 'general'),
                estado: Boolean(precio.estado ?? true),
            })),
        };
    };

    const save = async (): Promise<boolean> => {
        state.saving = true;
        state.errors = {};
        state.actionError = '';

        try {
            const configuracionProductos =
                (state.meta.configuracion_productos as
                    | {
                          categorias_habilitadas?: boolean;
                          marcas_habilitadas?: boolean;
                          busqueda_avanzada_habilitada?: boolean;
                          codigo_barras_habilitado?: boolean;
                      }
                    | undefined) ?? {};
            const categoriasHabilitadas =
                configuracionProductos.categorias_habilitadas !== false;
            const marcasHabilitadas =
                configuracionProductos.marcas_habilitadas === true;
            const busquedaAvanzadaHabilitada =
                configuracionProductos.busqueda_avanzada_habilitada === true;
            const codigoBarrasHabilitado =
                configuracionProductos.codigo_barras_habilitado !== false;

            const payload = {
                ...state.form,
                codigo_barras: codigoBarrasHabilitado
                    ? state.form.codigo_barras || null
                    : null,
                categoria_id:
                    categoriasHabilitadas && state.form.categoria_id
                        ? Number(state.form.categoria_id)
                        : null,
                marca_id:
                    marcasHabilitadas && state.form.marca_id
                        ? Number(state.form.marca_id)
                        : null,
                unidad_medida_id: state.form.unidad_medida_id
                    ? Number(state.form.unidad_medida_id)
                    : null,
                codigo_actividad_economica:
                    state.form.codigo_actividad_economica || null,
                codigo_producto_sin: state.form.codigo_producto_sin || null,
                codigo_unidad_medida_siat:
                    state.form.codigo_unidad_medida_siat || null,
                tags: busquedaAvanzadaHabilitada
                    ? parseTerms(state.form.tags)
                    : [],
                alias: busquedaAvanzadaHabilitada
                    ? parseTerms(state.form.alias)
                    : [],
                atributos: busquedaAvanzadaHabilitada
                    ? parseAttributes(state.form.atributos)
                    : [],
                precios: state.form.precios.map((precio) => ({
                    cantidad_minima: Number(precio.cantidad_minima),
                    precio: Number(precio.precio),
                    tipo_precio: precio.tipo_precio,
                    estado: Boolean(precio.estado),
                })),
            };

            if (state.editingId) {
                await articuloService.update(state.editingId, payload);
            } else {
                await articuloService.create(payload);
            }

            await load();
            resetForm();
            return true;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.errors = error.errors;
            } else {
                state.actionError =
                    error instanceof Error
                        ? error.message
                        : 'No se pudo guardar el artículo.';
            }

            return false;
        } finally {
            state.saving = false;
        }
    };

    const destroy = async (id: number) => {
        state.deleting = true;
        state.actionError = '';

        try {
            await articuloService.remove(id);
            await load();
            return true;
        } catch (error) {
            state.actionError =
                error instanceof Error
                    ? error.message
                    : 'No se pudo eliminar el artículo.';
            return false;
        } finally {
            state.deleting = false;
        }
    };

    const importRows = async (
        rows: Record<string, unknown>[],
        defaults: Record<string, unknown> = {},
    ) => {
        state.actionError = '';

        try {
            return await articuloService.import(rows, defaults);
        } catch (error) {
            state.actionError =
                error instanceof Error
                    ? error.message
                    : 'No se pudo importar el archivo.';
            throw error;
        }
    };

    const toggleEstado = async (item: Record<string, unknown>) => {
        await articuloService.updateEstado(
            Number(item.id),
            !Boolean(item.estado),
        );
        await load();
    };

    const setSearch = (value: string) => {
        state.search = value;
    };

    const clearFilters = () => {
        state.search = '';
        state.filters = {
            categoria_id: '',
            marca_id: '',
            stock: '',
        };
    };

    const addPrice = () => {
        state.form.precios.push(initialPrice());
    };

    const removePrice = (index: number) => {
        state.form.precios.splice(index, 1);
    };

    const addAttribute = () => {
        state.form.atributos.push({ atributo: '', valor: '' });
    };

    const removeAttribute = (index: number) => {
        state.form.atributos.splice(index, 1);
    };

    return {
        state,
        load,
        save,
        destroy,
        importRows,
        resetForm,
        startCreate,
        startEdit,
        toggleEstado,
        setSearch,
        clearFilters,
        addPrice,
        removePrice,
        addAttribute,
        removeAttribute,
    };
}
