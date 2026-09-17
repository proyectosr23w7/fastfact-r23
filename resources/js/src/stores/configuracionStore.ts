import { ApiError } from '@/src/services/apiClient';
import { configuracionService } from '@/src/services/configuracionService';
import { reactive } from 'vue';

export interface ConfiguracionForm {
    [key: string]: unknown;
    facturacion_habilitada: boolean;
    tipo_facturacion: number;
    ambiente_facturacion: string;
    token_siat: string;
    token_siat_configurado: boolean;
    codigo_sistema: string;
    token_siat_piloto: string;
    token_siat_piloto_configurado: boolean;
    token_siat_piloto_vigencia: string;
    token_siat_produccion: string;
    token_siat_produccion_configurado: boolean;
    token_siat_produccion_vigencia: string;
    firma_digital_nombre: string;
    firma_digital_configurada: boolean;
    firma_digital_archivo: File | null;
    firma_digital_password: string;
    metodo_salida: string;
    metodo_costos: string;
    multiples_precios: boolean;
    precios_por_cantidad: boolean;
    mostrar_descuento_detalle_factura: boolean;
    productos_categorias_habilitadas: boolean;
    productos_marcas_habilitadas: boolean;
    productos_busqueda_avanzada_habilitada: boolean;
    productos_codigo_barras_habilitado: boolean;
    pagos_credito_habilitados: boolean;
    confirmacion_rapida_ventas: boolean;
    facturacion_obligatoria_ventas: boolean;
    estado: boolean;
}

const initialForm = (): ConfiguracionForm => ({
    facturacion_habilitada: false,
    tipo_facturacion: 0,
    ambiente_facturacion: 'piloto',
    token_siat: '',
    token_siat_configurado: false,
    codigo_sistema: '',
    token_siat_piloto: '',
    token_siat_piloto_configurado: false,
    token_siat_piloto_vigencia: '',
    token_siat_produccion: '',
    token_siat_produccion_configurado: false,
    token_siat_produccion_vigencia: '',
    firma_digital_nombre: '',
    firma_digital_configurada: false,
    firma_digital_archivo: null,
    firma_digital_password: '',
    metodo_salida: 'peps',
    metodo_costos: 'promedio_ponderado',
    multiples_precios: false,
    precios_por_cantidad: false,
    mostrar_descuento_detalle_factura: true,
    productos_categorias_habilitadas: true,
    productos_marcas_habilitadas: false,
    productos_busqueda_avanzada_habilitada: false,
    productos_codigo_barras_habilitado: true,
    pagos_credito_habilitados: false,
    confirmacion_rapida_ventas: false,
    facturacion_obligatoria_ventas: false,
    estado: true,
});

export const useConfiguracionStore = () => {
    const state = reactive({
        items: [] as Record<string, unknown>[],
        form: initialForm(),
        loading: false,
        saving: false,
        editingId: null as number | null,
        errors: {} as Record<string, string[]>,
        successMessage: '',
        generalError: '',
    });

    const startCreate = () => {
        state.editingId = null;
        state.errors = {};
        state.form = initialForm();
    };

    const startEdit = (item: Record<string, unknown>) => {
        state.editingId = Number(item.id);
        state.errors = {};
        state.form = {
            ...initialForm(),
            ...item,
            token_siat: '',
            token_siat_piloto: '',
            token_siat_produccion: '',
            firma_digital_archivo: null,
            firma_digital_password: '',
        } as ConfiguracionForm;
    };

    const load = async () => {
        state.loading = true;
        state.generalError = '';

        try {
            const response = await configuracionService.list();
            state.items = response.data;

            if (state.items[0]) {
                startEdit(state.items[0]);
            } else {
                startCreate();
            }
        } catch (error) {
            state.generalError =
                error instanceof Error
                    ? error.message
                    : 'No se pudo cargar la configuración.';
        } finally {
            state.loading = false;
        }
    };

    const save = async (payload: Record<string, unknown>): Promise<boolean> => {
        state.saving = true;
        state.errors = {};
        state.successMessage = '';
        state.generalError = '';

        try {
            const response = state.editingId
                ? await configuracionService.update(state.editingId, payload)
                : await configuracionService.create(payload);

            state.items = [response.data];
            startEdit(response.data);
            state.successMessage =
                response.message ?? 'Configuración guardada correctamente.';

            return true;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.errors = error.errors;
                state.generalError = error.message;
            } else {
                state.generalError =
                    error instanceof Error
                        ? error.message
                        : 'No se pudo guardar la configuración.';
            }

            return false;
        } finally {
            state.saving = false;
        }
    };

    const clearMessages = () => {
        state.successMessage = '';
        state.generalError = '';
    };

    return {
        state,
        load,
        save,
        startCreate,
        startEdit,
        clearMessages,
    };
};
