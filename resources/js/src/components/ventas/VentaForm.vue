<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- el formulario edita el estado reactivo central de venta */
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import ClienteSearchSelect from '@/src/components/ventas/ClienteSearchSelect.vue';
import VentaArticuloSearchSelect from '@/src/components/ventas/VentaArticuloSearchSelect.vue';
import VentaDetalleTable from '@/src/components/ventas/VentaDetalleTable.vue';
import { usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Barcode,
    Building2,
    ChevronDown,
    CircleCheck,
    CreditCard,
    MapPin,
    PackagePlus,
    Pencil,
    Plus,
    Printer,
    ReceiptText,
    Save,
    Search,
    ShoppingCart,
    SlidersHorizontal,
    Sparkles,
    Tag,
    UserRound,
    UsersRound,
    X,
} from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';

type FlowNotice = {
    tone: 'error' | 'warning' | 'success';
    title: string;
    message: string;
};

const props = defineProps<{
    form: Record<string, any>;
    meta: Record<string, unknown>;
    errors: Record<string, string[]>;
    isEditing: boolean;
    saving: boolean;
    stockResolver: (articuloId: string) => number;
    puntosVenta: () => Record<string, unknown>[];
    saveClientHandler: () => Promise<Record<string, unknown> | null>;
    clientForm: Record<string, any>;
    clientErrors: Record<string, string[]>;
    clientSaving: boolean;
    clientEditingId?: number | null;
    generalError?: string;
    generalSuccess?: string;
    flowNotice?: FlowNotice | null;
    actionLabels?: {
        draft?: string;
        confirm?: string;
        confirmPrint?: string;
        processing?: string;
    };
    showDraftAction?: boolean;
}>();

const actionLabels = computed(() => ({
    draft: props.actionLabels?.draft ?? 'Guardar borrador',
    confirm: props.actionLabels?.confirm ?? 'Confirmar venta',
    confirmPrint: props.actionLabels?.confirmPrint ?? 'Confirmar e imprimir',
    processing: props.actionLabels?.processing ?? 'Procesando...',
}));
const showDraftAction = computed(() => props.showDraftAction ?? true);
const page = usePage();

const emit = defineEmits<{
    submit: [mode: 'draft' | 'confirm' | 'confirm_print'];
    cancel: [];
    addRow: [];
    removeRow: [index: number];
    recalcRow: [index: number];
    resetClientForm: [];
    editClient: [cliente: Record<string, unknown>];
    dismissNotice: [];
}>();

const clientes = computed(
    () => (props.meta.clientes as Record<string, unknown>[] | undefined) ?? [],
);
const sucursales = computed(
    () =>
        (props.meta.sucursales as Record<string, unknown>[] | undefined) ?? [],
);
const usuarios = computed(
    () => (props.meta.usuarios as Record<string, unknown>[] | undefined) ?? [],
);
const articulos = computed(
    () => (props.meta.articulos as Record<string, unknown>[] | undefined) ?? [],
);
const facturacionActiva = computed(() =>
    Boolean(props.meta.facturacion_activa ?? false),
);
const facturacionObligatoriaVentas = computed(() =>
    Boolean(props.meta.facturacion_obligatoria_ventas ?? false),
);
const mostrarDescuentoDetalle = computed(
    () => props.meta.mostrar_descuento_detalle_factura !== false,
);
const tiposDocumento = computed(() =>
    (
        (props.meta.tipos_documento_venta as
            | Record<string, unknown>[]
            | undefined) ?? []
    ).filter(
        (tipo) =>
            facturacionActiva.value || String(tipo.value ?? '') !== 'factura',
    ),
);
const documentosIdentidad = computed(
    () =>
        (props.meta.documentos_identidad as
            | Record<string, unknown>[]
            | undefined) ?? [],
);
const metodosPagoSiat = computed(
    () =>
        (props.meta.metodos_pago_siat as
            | Record<string, unknown>[]
            | undefined) ?? [],
);
const currentRoles = computed(
    () =>
        ((page.props.auth as Record<string, unknown> | undefined)?.roles as
            | Record<string, unknown>[]
            | undefined) ?? [],
);
const esSuperadmin = computed(() =>
    currentRoles.value.some((role) => String(role.slug ?? '') === 'superadmin'),
);
const puedeCambiarContextoOperativo = computed(
    () =>
        esSuperadmin.value &&
        Boolean(props.meta.usuario_puede_cambiar_contexto_operativo ?? false),
);
const eventosActivos = computed(
    () =>
        (props.meta.eventos_significativos_activos as
            | Record<string, unknown>[]
            | undefined) ?? [],
);
const puntosVenta = computed(() => props.puntosVenta());
const metodoPagoSeleccionado = computed(() =>
    metodosPagoSiat.value.find(
        (metodo) =>
            String(metodo.codigo_clasificador ?? '') ===
            String(props.form.codigo_metodo_pago ?? ''),
    ),
);
const requiereNumeroTarjeta = computed(() =>
    Boolean(metodoPagoSeleccionado.value?.requires_card_number ?? false),
);
const requiereMontoGiftCard = computed(() =>
    Boolean(metodoPagoSeleccionado.value?.requires_gift_card_amount ?? false),
);
const confirmacionRapidaVentas = computed(() =>
    Boolean(props.meta.confirmacion_rapida_ventas ?? false),
);
const eventoActivoContexto = computed(
    () =>
        eventosActivos.value.find(
            (evento) =>
                String(evento.sucursal_id ?? '') ===
                    String(props.form.sucursal_id ?? '') &&
                String(evento.punto_venta_id ?? '') ===
                    String(props.form.punto_venta_id ?? ''),
        ) ?? null,
);
const clienteSeleccionado = computed(
    () =>
        clientes.value.find(
            (cliente) =>
                String(cliente.id ?? '') ===
                String(props.form.cliente_id ?? ''),
        ) ?? null,
);
const isClientDialogOpen = ref(false);
const advancedOpen = ref(false);
const searchAssistantOpen = ref(false);
const quickArticuloId = ref('');
const assistantSearch = ref('');
const activeAssistantTerms = ref<string[]>([]);
const lockedClientDocument = ref('');

const articleById = (id: unknown) =>
    articulos.value.find(
        (articulo) => String(articulo.id ?? '') === String(id ?? ''),
    );

const stockWarnings = computed(
    () =>
        (props.form.detalle ?? [])
            .map((item: Record<string, unknown>, index: number) => {
                const articuloId = String(item.articulo_id ?? '');
                if (!articuloId) return null;

                const disponible = props.stockResolver(articuloId);
                const cantidad = Number(item.cantidad ?? 0);
                if (cantidad <= disponible) return null;

                return {
                    index,
                    articulo: String(
                        articleById(articuloId)?.nombre ??
                            `Artículo ${index + 1}`,
                    ),
                    disponible,
                    cantidad,
                };
            })
            .filter(Boolean) as {
            index: number;
            articulo: string;
            disponible: number;
            cantidad: number;
        }[],
);

const money = (value: unknown) =>
    new Intl.NumberFormat('es-BO', {
        style: 'currency',
        currency: 'BOB',
        minimumFractionDigits: 2,
    })
        .format(Number(value ?? 0))
        .replace('BOB', 'Bs');

const normalize = (value: unknown) =>
    String(value ?? '')
        .toLocaleLowerCase('es')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();

const terms = (value: unknown) =>
    Array.isArray(value)
        ? value.map((item) => String(item ?? '').trim()).filter(Boolean)
        : [];

const attributePairs = (value: unknown) =>
    Array.isArray(value)
        ? value
              .map((item) => item as Record<string, unknown>)
              .filter(
                  (item) =>
                      String(item.atributo ?? '').trim() !== '' &&
                      String(item.valor ?? '').trim() !== '',
              )
        : [];

const categoriaNombre = (articulo: Record<string, unknown>) =>
    String(
        (articulo.categoria as Record<string, unknown> | null)?.nombre ?? '',
    );

const articuloSearchText = (articulo: Record<string, unknown>) =>
    [
        articulo.nombre,
        articulo.descripcion,
        articulo.codigo_generico,
        articulo.codigo_barras,
        (articulo.categoria as Record<string, unknown> | null)?.nombre,
        (articulo.marca as Record<string, unknown> | null)?.nombre,
        ...terms(articulo.tags),
        ...terms(articulo.alias),
        ...attributePairs(articulo.atributos).flatMap((item) => [
            item.atributo,
            item.valor,
        ]),
    ]
        .map(normalize)
        .filter(Boolean)
        .join(' ');

const assistantTermPool = computed(() => {
    const counts = new Map<string, { label: string; count: number }>();

    const add = (value: unknown) => {
        const label = String(value ?? '').trim();
        const key = normalize(label);
        if (!key || label.length < 2) return;
        const current = counts.get(key);
        counts.set(key, {
            label: current?.label ?? label,
            count: (current?.count ?? 0) + 1,
        });
    };

    articulos.value.forEach((articulo) => {
        add((articulo.categoria as Record<string, unknown> | null)?.nombre);
        add((articulo.marca as Record<string, unknown> | null)?.nombre);
        terms(articulo.tags).forEach(add);
        terms(articulo.alias).forEach(add);
        attributePairs(articulo.atributos).forEach((item) => {
            add(item.valor);
            add(`${item.atributo}: ${item.valor}`);
        });
    });

    return [...counts.values()]
        .sort((a, b) => {
            if (a.count !== b.count) return b.count - a.count;
            return normalize(a.label).localeCompare(normalize(b.label), 'es');
        })
        .slice(0, 18);
});

const assistantFiltersLabel = computed(() =>
    activeAssistantTerms.value.length
        ? activeAssistantTerms.value.join(' + ')
        : 'Sin filtros aplicados',
);

const assistantQueryTerms = computed(() => [
    ...normalize(assistantSearch.value)
        .split(/\s+/)
        .map((term) => term.trim())
        .filter((term) => term.length > 1),
    ...activeAssistantTerms.value.map(normalize).filter(Boolean),
]);

const assistantResults = computed(() => {
    const queryTerms = assistantQueryTerms.value;
    const hasSearch = queryTerms.length > 0;

    return articulos.value
        .map((articulo) => {
            const text = articuloSearchText(articulo);
            const matched = queryTerms.filter((term) => text.includes(term));
            const usage = Number(
                articulo.ventas_count ?? articulo.venta_detalles_count ?? 0,
            );
            const stock = Number(articulo.stock_actual ?? 0);
            const score =
                matched.length * 20 + (stock > 0 ? 5 : 0) + Math.min(usage, 10);

            return { articulo, matched, score, stock, usage };
        })
        .filter((item) => !hasSearch || item.matched.length > 0)
        .sort((a, b) => {
            if (a.score !== b.score) return b.score - a.score;
            if (a.stock !== b.stock) return b.stock - a.stock;
            return normalize(a.articulo.nombre).localeCompare(
                normalize(b.articulo.nombre),
                'es',
            );
        })
        .slice(0, 16);
});

const toggleAssistantTerm = (term: string) => {
    const key = normalize(term);
    const exists = activeAssistantTerms.value.some(
        (item) => normalize(item) === key,
    );

    activeAssistantTerms.value = exists
        ? activeAssistantTerms.value.filter((item) => normalize(item) !== key)
        : [...activeAssistantTerms.value, term];
};

const clearAssistantSearch = () => {
    assistantSearch.value = '';
    activeAssistantTerms.value = [];
};

const addAssistantProduct = async (articulo: Record<string, unknown>) => {
    let index = (props.form.detalle ?? []).findIndex(
        (item: Record<string, unknown>) => !String(item.articulo_id ?? ''),
    );

    if (index < 0) {
        emit('addRow');
        await nextTick();
        index = Number(props.form.detalle?.length ?? 1) - 1;
    }

    const item = props.form.detalle[index] as Record<string, any>;
    item.articulo_id = String(articulo.id ?? '');
    item.cantidad = Number(item.cantidad ?? 0) > 0 ? item.cantidad : 1;
    item.precio_unitario = 0;
    item.descuento = Number(item.descuento ?? 0);
    emit('recalcRow', index);
    searchAssistantOpen.value = false;

    await nextTick();
    window.requestAnimationFrame(() =>
        document.getElementById(`venta-cantidad-${index}`)?.focus(),
    );
};

const addQuickProduct = async (articulo: Record<string, unknown>) => {
    await addAssistantProduct(articulo);
    quickArticuloId.value = '';
};

const clienteDocumento = computed(() => {
    if (!clienteSeleccionado.value) return 'Sin documento';
    const base =
        String(clienteSeleccionado.value.nit_ci ?? '').trim() ||
        'Sin documento';
    const complemento = String(
        clienteSeleccionado.value.complemento ?? '',
    ).trim();
    return complemento ? `${base}-${complemento}` : base;
});

const clienteCorreo = computed({
    get: () => String(clienteSeleccionado.value?.correo ?? ''),
    set: (value: string) => {
        if (clienteSeleccionado.value) clienteSeleccionado.value.correo = value;
    },
});

watch(
    () => props.form.sucursal_id,
    () => {
        const currentPoint = puntosVenta.value.find(
            (item) =>
                String(item.id) === String(props.form.punto_venta_id ?? ''),
        );
        if (!currentPoint)
            props.form.punto_venta_id = String(puntosVenta.value[0]?.id ?? '');
    },
);

watch(
    () => props.form.requiere_factura,
    (requiresFactura) => {
        if (facturacionActiva.value && requiresFactura)
            props.form.tipo_documento_venta = 'factura';
    },
);

watch(
    [facturacionActiva, facturacionObligatoriaVentas],
    ([activa, obligatoria]) => {
        if (!activa) {
            props.form.requiere_factura = false;
            if (String(props.form.tipo_documento_venta ?? '') === 'factura')
                props.form.tipo_documento_venta = 'nota_venta';
            return;
        }

        if (obligatoria) {
            props.form.requiere_factura = true;
            props.form.tipo_documento_venta = 'factura';
        }
    },
    { immediate: true },
);

watch(
    () => props.form.codigo_metodo_pago,
    () => {
        if (!requiereNumeroTarjeta.value) {
            props.form.numero_tarjeta_inicio = '';
            props.form.numero_tarjeta_fin = '';
        }
        if (!requiereMontoGiftCard.value) props.form.monto_gift_card = '';
    },
);

watch(isClientDialogOpen, (open) => {
    if (!open) lockedClientDocument.value = '';
});

watch(
    () => props.clientForm.tipo_documento_identidad,
    (tipo) => {
        if (String(tipo ?? '') !== '1') props.clientForm.complemento = '';
    },
);

const inferClientDocumentType = (documento: unknown) => {
    const value = String(documento ?? '').trim();
    if (!/^\d+$/.test(value)) return '';

    if (value.length >= 6 && value.length <= 8) return '1';
    if (value.length >= 9 && value.length <= 11) return '5';

    return '';
};

const applyClientDocumentTypeSuggestion = () => {
    const inferred = inferClientDocumentType(props.clientForm.nit_ci);
    if (!inferred) return;

    props.clientForm.tipo_documento_identidad = inferred;
};

watch(
    () => props.clientForm.nit_ci,
    () => {
        if (!isClientDialogOpen.value || props.clientEditingId) return;
        applyClientDocumentTypeSuggestion();
    },
);

const numericFourDigits = (
    key: 'numero_tarjeta_inicio' | 'numero_tarjeta_fin',
    value: string,
) => {
    props.form[key] = value.replace(/\D+/g, '').slice(0, 4);
};

const openClientDialog = async (documento = '') => {
    emit('resetClientForm');
    lockedClientDocument.value = documento.trim();
    await nextTick();
    props.clientForm.nit_ci = lockedClientDocument.value;
    applyClientDocumentTypeSuggestion();
    isClientDialogOpen.value = true;
};

const openEditClientDialog = async () => {
    if (!clienteSeleccionado.value) return;
    lockedClientDocument.value = '';
    emit('editClient', clienteSeleccionado.value);
    await nextTick();
    isClientDialogOpen.value = true;
};

const focusProduct = () => {
    const detail = (props.form.detalle ?? []) as Record<string, unknown>[];
    const emptyIndex = detail.findIndex(
        (item) => !String(item.articulo_id ?? ''),
    );
    const index = emptyIndex >= 0 ? emptyIndex : 0;
    window.requestAnimationFrame(() => {
        const quickSearch = document.getElementById('venta-busqueda-rapida');
        if (quickSearch) {
            quickSearch.focus();
            return;
        }

        document.getElementById(`venta-articulo-${index}`)?.focus();
    });
};

const addProductRow = async () => {
    const index = Number(props.form.detalle?.length ?? 0);
    emit('addRow');
    await nextTick();
    window.requestAnimationFrame(() =>
        document.getElementById(`venta-articulo-${index}`)?.focus(),
    );
};

const recalculateAfterGlobalDiscount = async () => {
    await nextTick();
    emit('recalcRow', 0);
};

const submitClient = async () => {
    if (lockedClientDocument.value)
        props.clientForm.nit_ci = lockedClientDocument.value;
    const saved = await props.saveClientHandler();
    if (saved) isClientDialogOpen.value = false;
};

const closeClientDialog = () => {
    isClientDialogOpen.value = false;
};

const handleShortcut = (event: KeyboardEvent) => {
    if (
        props.saving ||
        isClientDialogOpen.value ||
        searchAssistantOpen.value ||
        event.altKey ||
        event.ctrlKey ||
        event.metaKey
    )
        return;

    const actions: Record<string, () => void> = {
        F2: focusProduct,
        F3: () => openClientDialog(),
        F4: () => emit('submit', 'draft'),
        F5: () => emit('submit', 'confirm'),
        F6: () => emit('submit', 'confirm_print'),
        F7: () => {
            searchAssistantOpen.value = true;
        },
    };

    const action = actions[event.key];
    if (!action) return;
    event.preventDefault();
    action();
};

onMounted(() => window.addEventListener('keydown', handleShortcut));
onBeforeUnmount(() => window.removeEventListener('keydown', handleShortcut));
</script>

<template>
    <form @submit.prevent="emit('submit', 'draft')">
        <div
            class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_320px] xl:grid-cols-[minmax(0,1fr)_340px]"
        >
            <div class="min-w-0 space-y-4">
                <div
                    v-if="flowNotice"
                    role="alert"
                    aria-live="assertive"
                    :class="[
                        'flex items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-sm',
                        flowNotice.tone === 'error' &&
                            'border-rose-200 bg-rose-50 text-rose-900',
                        flowNotice.tone === 'warning' &&
                            'border-amber-200 bg-amber-50 text-amber-900',
                        flowNotice.tone === 'success' &&
                            'border-[#BFDCC9] bg-[#EAF7EF] text-[#126B3B]',
                    ]"
                >
                    <AlertTriangle
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ flowNotice.title }}</p>
                        <p class="mt-0.5 leading-5">{{ flowNotice.message }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded p-1 hover:bg-black/5 focus-visible:ring-2 focus-visible:ring-current"
                        aria-label="Cerrar aviso"
                        @click="emit('dismissNotice')"
                    >
                        <X class="size-4" aria-hidden="true" />
                    </button>
                </div>

                <div
                    v-if="generalSuccess"
                    role="status"
                    class="rounded-xl border border-[#BFDCC9] bg-[#EAF7EF] px-4 py-3 text-sm text-[#126B3B]"
                >
                    {{ generalSuccess }}
                </div>

                <section
                    class="rounded-xl border border-[#DDE7E0] bg-white p-4 shadow-sm sm:p-5"
                    aria-labelledby="cliente-heading"
                >
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <h2 id="cliente-heading" class="font-semibold">
                                Cliente
                            </h2>
                            <p class="mt-0.5 text-xs text-[#68766D]">
                                Busca por NIT, CI o razón social.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                v-if="clienteSeleccionado"
                                type="button"
                                variant="outline"
                                class="h-10 border-[#D6E2DB] text-[#405047] hover:bg-[#F5F8F6]"
                                @click="openEditClientDialog"
                            >
                                <Pencil
                                    class="mr-2 size-4"
                                    aria-hidden="true"
                                />
                                Editar
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                class="h-10 border-[#A8D2B7] text-[#126B3B] hover:bg-[#EAF7EF]"
                                aria-keyshortcuts="F3"
                                @click="openClientDialog()"
                            >
                                <Plus class="mr-2 size-4" aria-hidden="true" />
                                Nuevo cliente
                            </Button>
                        </div>
                    </div>
                    <ClienteSearchSelect
                        v-model="form.cliente_id"
                        input-id="cliente_id"
                        :clientes="clientes"
                        @select="focusProduct"
                        @not-found="openClientDialog"
                    />
                    <InputError :message="errors.cliente_id?.[0]" />
                </section>

                <section
                    class="overflow-hidden rounded-xl border border-[#DDE7E0] bg-white shadow-sm"
                    aria-labelledby="detalle-heading"
                >
                    <div class="border-b border-[#E4ECE7] p-3">
                        <div
                            class="mb-3 flex flex-wrap items-center justify-between gap-3"
                        >
                            <div>
                                <h2
                                    id="detalle-heading"
                                    class="text-base font-semibold"
                                >
                                    Detalle de productos y servicios
                                </h2>
                                <p class="mt-0.5 text-xs text-[#68766D]">
                                    Busca por teclado, alias, nombre, categoria,
                                    codigo interno o barras opcionales.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                class="h-10 border-[#A8D2B7] text-[#126B3B] hover:bg-[#EAF7EF]"
                                @click="addProductRow"
                            >
                                Linea manual
                            </Button>
                        </div>
                        <div
                            class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_136px_136px]"
                        >
                            <div
                                class="flex h-14 min-w-0 items-center gap-3 rounded-lg border-2 border-[#A8D2B7] bg-white px-3 shadow-[0_0_0_4px_rgba(22,132,71,0.08)] transition focus-within:border-[#168447] focus-within:ring-2 focus-within:ring-[#168447]/20"
                            >
                                <span
                                    class="flex h-9 min-w-10 items-center justify-center rounded-md bg-[#EAF7EF] px-2 text-sm font-bold text-[#168447]"
                                >
                                    F2
                                </span>
                                <VentaArticuloSearchSelect
                                    v-model="quickArticuloId"
                                    :articulos="articulos"
                                    input-id="venta-busqueda-rapida"
                                    class="min-w-0 flex-1"
                                    placeholder="Buscar producto o servicio"
                                    input-class="h-12 w-full min-w-0 border-0 bg-transparent pr-2 pl-9 text-base font-semibold text-[#101713] transition outline-none placeholder:text-[#68766D] focus:ring-0 sm:text-lg"
                                    icon-class="pointer-events-none absolute top-4 left-3 size-4 text-[#68766D]"
                                    aria-keyshortcuts="F2"
                                    @select="addQuickProduct"
                                />
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                class="h-14 border-[#A8D2B7] text-[#126B3B] hover:bg-[#EAF7EF]"
                                aria-keyshortcuts="F7"
                                @click="searchAssistantOpen = true"
                            >
                                Frecuentes
                            </Button>
                            <Button
                                type="button"
                                class="h-14 bg-[#168447] text-white hover:bg-[#126B3B]"
                                @click="addProductRow"
                            >
                                Agregar
                            </Button>
                        </div>
                        <div class="mt-3 grid gap-2 sm:grid-cols-3">
                            <button
                                v-for="item in assistantResults.slice(0, 3)"
                                :key="String(item.articulo.id)"
                                type="button"
                                class="min-h-20 rounded-lg border border-[#DDE7E0] bg-white p-3 text-left transition first:border-[#168447] first:bg-[#F1F8F4] hover:border-[#168447]"
                                @click="addAssistantProduct(item.articulo)"
                            >
                                <span
                                    class="block truncate text-sm font-semibold text-[#101713]"
                                >
                                    {{ String(item.articulo.nombre ?? '') }}
                                </span>
                                <span
                                    class="mt-1 block truncate text-xs text-[#68766D]"
                                >
                                    {{
                                        String(
                                            item.articulo.codigo_generico ??
                                                item.articulo.codigo_barras ??
                                                'Servicio o producto',
                                        )
                                    }}
                                    . Bs
                                    {{
                                        Number(
                                            item.articulo.precio_base ?? 0,
                                        ).toFixed(2)
                                    }}
                                </span>
                            </button>
                        </div>
                    </div>
                    <VentaDetalleTable
                        :detail="form.detalle as Record<string, unknown>[]"
                        :articulos="articulos"
                        :errors="errors"
                        :mostrar-descuento-detalle="mostrarDescuentoDetalle"
                        :stock-resolver="stockResolver"
                        @add="addProductRow"
                        @remove="emit('removeRow', $event)"
                        @recalc="emit('recalcRow', $event)"
                    />
                </section>

                <div
                    v-if="stockWarnings.length"
                    role="alert"
                    class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                >
                    <div class="flex items-start gap-3">
                        <AlertTriangle
                            class="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <div>
                            <p class="font-semibold">
                                Revisa el stock antes de confirmar
                            </p>
                            <p class="mt-1">
                                {{
                                    stockWarnings
                                        .map(
                                            (warning) =>
                                                `${warning.articulo}: quedan ${warning.disponible.toFixed(2)}`,
                                        )
                                        .join(' · ')
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="eventoActivoContexto"
                    role="status"
                    class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                >
                    <div class="flex items-start gap-3">
                        <AlertTriangle
                            class="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <div>
                            <p class="font-semibold">Contingencia activa</p>
                            <p class="mt-1">
                                Evento {{ eventoActivoContexto.codigo_evento }}.
                                Las facturas se registrarán fuera de línea y
                                quedarán pendientes de envío al SIAT.
                            </p>
                        </div>
                    </div>
                </div>

                <section
                    class="rounded-xl border border-[#DDE7E0] bg-white shadow-sm"
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-4 p-4 text-left focus-visible:ring-2 focus-visible:ring-[#168447] focus-visible:outline-none focus-visible:ring-inset"
                        :aria-expanded="advancedOpen"
                        aria-controls="venta-datos-adicionales"
                        @click="advancedOpen = !advancedOpen"
                    >
                        <span>
                            <span class="block text-sm font-semibold"
                                >Datos adicionales</span
                            >
                            <span class="mt-0.5 block text-xs text-[#68766D]"
                                >Responsable y fecha de la operacion.</span
                            >
                        </span>
                        <ChevronDown
                            :class="[
                                'size-5 text-[#68766D] transition-transform',
                                advancedOpen && 'rotate-180',
                            ]"
                            aria-hidden="true"
                        />
                    </button>
                    <div
                        v-show="advancedOpen"
                        id="venta-datos-adicionales"
                        class="grid gap-4 border-t border-[#E4ECE7] p-4 sm:grid-cols-2"
                    >
                        <div class="space-y-2">
                            <Label for="user_id">Responsable</Label>
                            <select
                                id="user_id"
                                v-model="form.user_id"
                                class="company-select"
                            >
                                <option value="">Seleccione un usuario</option>
                                <option
                                    v-for="usuario in usuarios"
                                    :key="String(usuario.id)"
                                    :value="String(usuario.id)"
                                >
                                    {{ usuario.name }}
                                </option>
                            </select>
                            <InputError :message="errors.user_id?.[0]" />
                        </div>
                        <div class="space-y-2">
                            <Label for="fecha_venta">Fecha de venta</Label>
                            <Input
                                id="fecha_venta"
                                v-model="form.fecha_venta"
                                type="date"
                                class="company-input"
                            />
                            <InputError :message="errors.fecha_venta?.[0]" />
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-xl border border-[#CFE3D6] bg-[#F1F8F4] p-4"
                    aria-labelledby="shortcuts-heading"
                >
                    <h2
                        id="shortcuts-heading"
                        class="mb-3 text-sm font-semibold"
                    >
                        Atajos de teclado
                    </h2>
                    <div class="grid grid-cols-2 gap-3 text-xs sm:grid-cols-6">
                        <div
                            v-for="shortcut in [
                                ['F2', 'Producto o servicio'],
                                ['F3', 'Nuevo cliente'],
                                ['F4', 'Guardar borrador'],
                                ['F5', 'Confirmar venta'],
                                ['F6', 'Confirmar e imprimir'],
                                ['F7', 'Frecuentes'],
                            ]"
                            :key="shortcut[0]"
                            class="flex items-center gap-2"
                        >
                            <kbd
                                class="inline-flex min-w-10 justify-center rounded-md border border-[#A8D2B7] bg-white px-2 py-1.5 text-sm font-semibold text-[#126B3B]"
                                >{{ shortcut[0] }}</kbd
                            >
                            <span class="text-[#435248]">{{
                                shortcut[1]
                            }}</span>
                        </div>
                    </div>
                </section>
            </div>

            <aside
                class="min-w-0 lg:sticky lg:top-4"
                aria-labelledby="resumen-heading"
            >
                <div
                    class="rounded-xl border border-[#DDE7E0] bg-white p-3 shadow-sm sm:p-4"
                >
                    <div class="mb-3 flex items-center gap-2">
                        <ShoppingCart
                            class="size-4 text-[#101713]"
                            aria-hidden="true"
                        />
                        <h2 id="resumen-heading" class="text-base font-bold">
                            Resumen de venta
                        </h2>
                    </div>

                    <section
                        class="mb-2 rounded-lg border border-[#E0E8E2] bg-[#FAFCFB] p-2.5"
                        aria-label="Contexto de emision"
                    >
                        <div
                            class="grid gap-2 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2"
                        >
                            <div class="space-y-1">
                                <Label
                                    for="sucursal_id"
                                    class="text-xs font-semibold"
                                    >Sucursal</Label
                                >
                                <div class="relative">
                                    <MapPin
                                        class="pointer-events-none absolute top-2.5 left-3 size-4 text-[#168447]"
                                        aria-hidden="true"
                                    />
                                    <select
                                        id="sucursal_id"
                                        v-model="form.sucursal_id"
                                        :disabled="
                                            !puedeCambiarContextoOperativo
                                        "
                                        class="h-9 w-full rounded-lg border border-[#CDD9D1] bg-white pr-3 pl-9 text-sm outline-none focus:border-[#168447] focus:ring-2 focus:ring-[#168447]/20 disabled:cursor-not-allowed disabled:bg-[#F3F6F4] disabled:text-[#68766D]"
                                    >
                                        <option value="">
                                            Seleccione una sucursal
                                        </option>
                                        <option
                                            v-for="sucursal in sucursales"
                                            :key="String(sucursal.id)"
                                            :value="String(sucursal.id)"
                                        >
                                            {{ sucursal.nombre }}
                                        </option>
                                    </select>
                                </div>
                                <InputError
                                    :message="errors.sucursal_id?.[0]"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label
                                    for="punto_venta_id"
                                    class="text-xs font-semibold"
                                    >Punto de venta</Label
                                >
                                <div class="relative">
                                    <Building2
                                        class="pointer-events-none absolute top-2.5 left-3 size-4 text-[#168447]"
                                        aria-hidden="true"
                                    />
                                    <select
                                        id="punto_venta_id"
                                        v-model="form.punto_venta_id"
                                        :disabled="
                                            !puedeCambiarContextoOperativo
                                        "
                                        class="h-9 w-full rounded-lg border border-[#CDD9D1] bg-white pr-3 pl-9 text-sm outline-none focus:border-[#168447] focus:ring-2 focus:ring-[#168447]/20 disabled:cursor-not-allowed disabled:bg-[#F3F6F4] disabled:text-[#68766D]"
                                    >
                                        <option value="">
                                            Seleccione un punto
                                        </option>
                                        <option
                                            v-for="puntoVenta in puntosVenta"
                                            :key="String(puntoVenta.id)"
                                            :value="String(puntoVenta.id)"
                                        >
                                            {{ puntoVenta.nombre }}
                                        </option>
                                    </select>
                                </div>
                                <InputError
                                    :message="errors.punto_venta_id?.[0]"
                                />
                            </div>
                        </div>
                    </section>

                    <section
                        class="mb-2 rounded-lg border border-[#E0E8E2] bg-[#FAFCFB] p-2.5"
                        aria-label="Pago"
                    >
                        <div class="space-y-1">
                            <Label
                                for="codigo_metodo_pago"
                                class="text-xs font-semibold"
                                >Metodo de pago</Label
                            >
                            <div class="relative">
                                <CreditCard
                                    class="pointer-events-none absolute top-2.5 left-3 size-4 text-[#168447]"
                                    aria-hidden="true"
                                />
                                <select
                                    id="codigo_metodo_pago"
                                    v-model="form.codigo_metodo_pago"
                                    class="h-9 w-full rounded-lg border border-[#CDD9D1] bg-white pr-3 pl-9 text-sm outline-none focus:border-[#168447] focus:ring-2 focus:ring-[#168447]/20"
                                >
                                    <option value="">
                                        Seleccione un metodo
                                    </option>
                                    <option
                                        v-for="metodo in metodosPagoSiat"
                                        :key="
                                            String(metodo.codigo_clasificador)
                                        "
                                        :value="
                                            String(metodo.codigo_clasificador)
                                        "
                                    >
                                        {{ metodo.descripcion }}
                                    </option>
                                </select>
                            </div>
                            <InputError
                                :message="errors.codigo_metodo_pago?.[0]"
                            />
                        </div>

                        <div
                            v-if="requiereNumeroTarjeta"
                            class="mt-2 space-y-1.5"
                        >
                            <Label class="text-xs font-semibold">
                                Numero de tarjeta para SIAT
                            </Label>
                            <div
                                class="grid gap-2 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2"
                            >
                                <Input
                                    id="numero_tarjeta_inicio"
                                    :model-value="
                                        String(form.numero_tarjeta_inicio ?? '')
                                    "
                                    inputmode="numeric"
                                    maxlength="4"
                                    class="company-input h-9 text-center font-mono"
                                    placeholder="1234"
                                    @update:model-value="
                                        numericFourDigits(
                                            'numero_tarjeta_inicio',
                                            String($event ?? ''),
                                        )
                                    "
                                />
                                <Input
                                    id="numero_tarjeta_fin"
                                    :model-value="
                                        String(form.numero_tarjeta_fin ?? '')
                                    "
                                    inputmode="numeric"
                                    maxlength="4"
                                    class="company-input h-9 text-center font-mono"
                                    placeholder="9876"
                                    @update:model-value="
                                        numericFourDigits(
                                            'numero_tarjeta_fin',
                                            String($event ?? ''),
                                        )
                                    "
                                />
                            </div>
                            <div
                                class="rounded-lg border border-[#CFE3D6] bg-[#F1F8F4] px-3 py-1.5 text-center font-mono text-xs font-semibold text-[#126B3B]"
                            >
                                {{
                                    form.numero_tarjeta_inicio || '####'
                                }}-xxxx-xxxx-{{
                                    form.numero_tarjeta_fin || '####'
                                }}
                            </div>
                            <p class="text-[11px] leading-4 text-[#68766D]">
                                Registra solo los 4 primeros y los 4 ultimos
                                digitos.
                            </p>
                            <InputError :message="errors.numero_tarjeta?.[0]" />
                        </div>

                        <div
                            v-if="requiereMontoGiftCard"
                            class="mt-2 space-y-1"
                        >
                            <Label
                                for="monto_gift_card"
                                class="text-xs font-semibold"
                                >Monto gift card</Label
                            >
                            <Input
                                id="monto_gift_card"
                                v-model="form.monto_gift_card"
                                type="number"
                                min="0.01"
                                step="0.01"
                                class="company-input h-9 text-right"
                                placeholder="0.00"
                            />
                            <InputError
                                :message="errors.monto_gift_card?.[0]"
                            />
                        </div>
                    </section>

                    <dl class="space-y-1.5 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <dt>Subtotal</dt>
                            <dd class="font-semibold">
                                {{ money(form.subtotal) }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt>
                                <Label
                                    for="descuento_global"
                                    class="font-normal"
                                    >Descuento global</Label
                                >
                            </dt>
                            <dd class="flex items-center gap-2">
                                <Input
                                    id="descuento_global"
                                    v-model="form.descuento_global"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="h-8 w-20 text-right"
                                    @update:model-value="
                                        recalculateAfterGlobalDiscount
                                    "
                                />
                                <span
                                    class="min-w-16 text-right font-semibold"
                                    >{{ money(form.descuento_global) }}</span
                                >
                            </dd>
                        </div>
                        <div
                            v-if="Number(form.descuento ?? 0) > 0"
                            class="flex items-center justify-between gap-4"
                        >
                            <dt>Descuento en articulos</dt>
                            <dd class="font-semibold">
                                - {{ money(form.descuento) }}
                            </dd>
                        </div>
                    </dl>
                    <InputError :message="errors.descuento_global?.[0]" />

                    <div class="my-2 h-px bg-[#E4ECE7]" />
                    <div class="flex items-end justify-between gap-4">
                        <span class="text-base font-bold">Total</span>
                        <span
                            class="text-2xl font-bold tracking-tight text-[#168447]"
                            >{{ money(form.total) }}</span
                        >
                    </div>

                    <div class="mt-3 space-y-2.5">
                        <div>
                            <p class="mb-1.5 text-sm font-semibold">Cliente</p>
                            <div
                                class="rounded-lg border border-[#E0E8E2] bg-[#FAFCFB] p-2.5"
                            >
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-2 text-sm font-semibold text-[#126B3B]"
                                    >
                                        <UserRound
                                            class="size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span class="truncate">{{
                                            clienteSeleccionado?.razon_social ??
                                            'Cliente no seleccionado'
                                        }}</span>
                                    </div>
                                    <Button
                                        v-if="clienteSeleccionado"
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-7 shrink-0 text-[#126B3B] hover:bg-[#EAF7EF]"
                                        aria-label="Editar cliente"
                                        @click="openEditClientDialog"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                </div>
                                <div
                                    class="mt-1.5 flex items-center gap-3 text-xs text-[#526057]"
                                >
                                    <span>NIT / CI</span
                                    ><span class="font-medium text-[#26342B]">{{
                                        clienteDocumento
                                    }}</span>
                                </div>
                                <div v-if="clienteSeleccionado" class="mt-2">
                                    <Label
                                        for="cliente_correo_resumen"
                                        class="mb-1 block text-xs text-[#526057]"
                                        >Correo de envio</Label
                                    >
                                    <Input
                                        id="cliente_correo_resumen"
                                        v-model="clienteCorreo"
                                        type="email"
                                        class="company-input h-9"
                                        placeholder="cliente@correo.com"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <Label for="tipo_documento_venta">Documento</Label>
                            <div
                                v-if="facturacionObligatoriaVentas"
                                class="flex h-9 items-center gap-2 rounded-lg border border-[#CDD9D1] bg-[#F5F8F6] px-3 text-sm"
                            >
                                <ReceiptText
                                    class="size-4 text-[#168447]"
                                    aria-hidden="true"
                                />
                                Factura
                            </div>
                            <select
                                v-else
                                id="tipo_documento_venta"
                                v-model="form.tipo_documento_venta"
                                class="company-select"
                            >
                                <option value="">Seleccione un tipo</option>
                                <option
                                    v-for="tipo in tiposDocumento"
                                    :key="String(tipo.value)"
                                    :value="String(tipo.value)"
                                >
                                    {{ tipo.label }}
                                </option>
                            </select>
                            <InputError
                                :message="errors.tipo_documento_venta?.[0]"
                            />
                        </div>

                        <div
                            v-if="confirmacionRapidaVentas"
                            class="rounded-lg border border-[#CFE3D6] bg-[#F1F8F4] p-2.5 text-xs leading-4 text-[#435248]"
                        >
                            <CircleCheck
                                class="mr-1 inline size-4 text-[#168447]"
                                aria-hidden="true"
                            />
                            La confirmacion afectara inventario y kardex
                            inmediatamente.
                        </div>
                    </div>
                    <div
                        class="sticky bottom-0 z-20 -mx-3 mt-3 space-y-1.5 border-t border-[#E4ECE7] bg-white/95 px-3 pt-3 pb-1 backdrop-blur sm:-mx-4 sm:px-4 lg:static lg:mx-0 lg:border-0 lg:bg-transparent lg:p-0 lg:pt-3"
                    >
                        <Button
                            v-if="showDraftAction"
                            type="button"
                            variant="outline"
                            class="h-10 w-full border-[#168447] text-[#101713] hover:bg-[#EAF7EF]"
                            :disabled="saving"
                            aria-keyshortcuts="F4"
                            @click="emit('submit', 'draft')"
                        >
                            <Save class="mr-2 size-4" aria-hidden="true" />{{
                                saving
                                    ? actionLabels.processing
                                    : actionLabels.draft
                            }}
                        </Button>
                        <Button
                            type="button"
                            class="h-11 w-full bg-[#168447] text-sm font-semibold text-white hover:bg-[#126B3B]"
                            :disabled="saving || stockWarnings.length > 0"
                            aria-keyshortcuts="F5"
                            @click="emit('submit', 'confirm')"
                        >
                            <CircleCheck
                                class="mr-2 size-5"
                                aria-hidden="true"
                            />{{
                                saving
                                    ? actionLabels.processing
                                    : actionLabels.confirm
                            }}
                        </Button>
                        <Button
                            type="button"
                            class="h-11 w-full bg-[#101713] text-sm font-semibold text-white hover:bg-[#19221D]"
                            :disabled="saving || stockWarnings.length > 0"
                            aria-keyshortcuts="F6"
                            @click="emit('submit', 'confirm_print')"
                        >
                            <Printer class="mr-2 size-5" aria-hidden="true" />{{
                                saving
                                    ? actionLabels.processing
                                    : actionLabels.confirmPrint
                            }}
                        </Button>
                    </div>

                    <div class="mt-3 space-y-1 border-t border-[#E4ECE7] pt-3">
                        <Label for="observacion">Observaciones</Label>
                        <textarea
                            id="observacion"
                            v-model="form.observacion"
                            class="min-h-11 w-full resize-y rounded-lg border border-[#CDD9D1] bg-white px-3 py-2 text-sm outline-none placeholder:text-[#8A978F] focus:border-[#168447] focus:ring-2 focus:ring-[#168447]/20"
                            placeholder="Agregar observacion (opcional)"
                        />
                        <InputError :message="errors.observacion?.[0]" />
                    </div>
                </div>
            </aside>
        </div>
    </form>

    <Sheet v-model:open="searchAssistantOpen">
        <SheetContent
            side="right"
            class="w-full gap-0 overflow-hidden border-[#DDE7E0] p-0 sm:max-w-xl"
        >
            <div
                class="border-b border-[#DDE7E0] bg-[#101713] px-5 py-5 text-white"
            >
                <SheetHeader>
                    <div
                        class="mb-2 flex size-10 items-center justify-center rounded-xl bg-[#20A85B]/20 text-[#72E39A]"
                    >
                        <Sparkles class="size-5" aria-hidden="true" />
                    </div>
                    <SheetTitle class="text-left text-xl text-white">
                        Asistente de busqueda
                    </SheetTitle>
                    <SheetDescription class="text-left text-[#CFE3D6]">
                        Usa las categorias, marcas, alias y atributos que ya
                        existen en este inventario.
                    </SheetDescription>
                </SheetHeader>
            </div>

            <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                <div
                    class="space-y-4 border-b border-[#E4ECE7] bg-[#FAFCFB] p-5"
                >
                    <div class="space-y-2">
                        <Label for="venta-asistente-busqueda">
                            ?Qu? est? buscando el cliente?
                        </Label>
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-3 left-3 size-4 text-[#68766D]"
                                aria-hidden="true"
                            />
                            <Input
                                id="venta-asistente-busqueda"
                                v-model="assistantSearch"
                                class="h-11 border-[#CDD9D1] bg-white pl-9"
                                placeholder="Ej.: dolor cabeza, pintura agua, terror..."
                            />
                        </div>
                    </div>

                    <div v-if="assistantTermPool.length" class="space-y-2">
                        <div
                            class="flex items-center gap-2 text-xs font-semibold text-[#435248]"
                        >
                            <SlidersHorizontal
                                class="size-4 text-[#168447]"
                                aria-hidden="true"
                            />
                            Sugerencias segun tu inventario
                        </div>
                        <div
                            class="flex max-h-28 flex-wrap gap-2 overflow-y-auto pr-1"
                        >
                            <button
                                v-for="term in assistantTermPool"
                                :key="term.label"
                                type="button"
                                :class="[
                                    'rounded-full border px-3 py-1.5 text-xs font-semibold transition',
                                    activeAssistantTerms.some(
                                        (item) =>
                                            normalize(item) ===
                                            normalize(term.label),
                                    )
                                        ? 'border-[#168447] bg-[#EAF7EF] text-[#126B3B]'
                                        : 'border-[#DDE7E0] bg-white text-[#435248] hover:border-[#168447]',
                                ]"
                                @click="toggleAssistantTerm(term.label)"
                            >
                                {{ term.label }}
                            </button>
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-between gap-3 rounded-lg border border-[#DDE7E0] bg-white px-3 py-2 text-xs text-[#526057]"
                    >
                        <span class="min-w-0 truncate">
                            Filtros: {{ assistantFiltersLabel }}
                        </span>
                        <button
                            type="button"
                            class="shrink-0 font-semibold text-[#126B3B] hover:text-[#168447]"
                            @click="clearAssistantSearch"
                        >
                            Limpiar
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-5">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold">
                                Productos encontrados
                            </p>
                            <p class="text-xs text-[#68766D]">
                                {{ assistantResults.length }} coincidencias
                                visibles
                            </p>
                        </div>
                        <Tag class="size-4 text-[#168447]" aria-hidden="true" />
                    </div>

                    <div class="space-y-3">
                        <article
                            v-for="item in assistantResults"
                            :key="String(item.articulo.id)"
                            class="rounded-xl border border-[#DDE7E0] bg-white p-3 shadow-sm"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-[#EAF7EF] text-[#168447]"
                                >
                                    <Barcode
                                        class="size-5"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3
                                        class="truncate text-sm font-semibold text-[#101713]"
                                    >
                                        {{ String(item.articulo.nombre ?? '') }}
                                    </h3>
                                    <p
                                        class="mt-0.5 truncate text-xs text-[#68766D]"
                                    >
                                        {{
                                            String(
                                                item.articulo.codigo_generico ??
                                                    '',
                                            )
                                        }}
                                        <span
                                            v-if="item.articulo.codigo_barras"
                                        >
                                            /
                                            {{
                                                String(
                                                    item.articulo.codigo_barras,
                                                )
                                            }}
                                        </span>
                                    </p>
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        <span
                                            v-if="
                                                categoriaNombre(item.articulo)
                                            "
                                            class="rounded-full bg-[#F1F8F4] px-2 py-1 text-[11px] font-medium text-[#126B3B]"
                                        >
                                            {{ categoriaNombre(item.articulo) }}
                                        </span>
                                        <span
                                            v-for="attr in attributePairs(
                                                item.articulo.atributos,
                                            ).slice(0, 2)"
                                            :key="`${String(attr.atributo)}-${String(attr.valor)}`"
                                            class="rounded-full bg-[#F5F8F6] px-2 py-1 text-[11px] font-medium text-[#435248]"
                                        >
                                            {{ attr.atributo }}:
                                            {{ attr.valor }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="mt-3 flex items-center justify-between gap-3"
                            >
                                <div class="text-xs text-[#526057]">
                                    <span class="font-semibold text-[#168447]">
                                        Stock
                                        {{ Number(item.stock).toFixed(2) }}
                                    </span>
                                    <span class="mx-1">/</span>
                                    Bs
                                    {{
                                        Number(
                                            item.articulo.precio_base ?? 0,
                                        ).toFixed(2)
                                    }}
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    class="bg-[#168447] text-white hover:bg-[#126B3B]"
                                    @click="addAssistantProduct(item.articulo)"
                                >
                                    <PackagePlus
                                        class="mr-2 size-4"
                                        aria-hidden="true"
                                    />
                                    Agregar
                                </Button>
                            </div>
                        </article>

                        <div
                            v-if="assistantResults.length === 0"
                            class="rounded-xl border border-dashed border-[#CDD9D1] bg-[#FAFCFB] px-4 py-8 text-center text-sm text-[#68766D]"
                        >
                            No hay coincidencias con esos terminos. Prueba con
                            otro alias, atributo o categoria del producto.
                        </div>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>

    <Dialog v-model:open="isClientDialogOpen">
        <DialogContent
            class="max-h-[90vh] max-w-2xl overflow-y-auto border-[#DDE7E0] p-0"
        >
            <div
                class="border-b border-[#DDE7E0] bg-[#101713] px-5 py-5 text-white"
            >
                <DialogHeader>
                    <div
                        class="mb-2 flex size-10 items-center justify-center rounded-xl bg-[#20A85B]/20 text-[#72E39A]"
                    >
                        <UsersRound class="size-5" aria-hidden="true" />
                    </div>
                    <DialogTitle class="text-left text-xl text-white">{{
                        clientEditingId ? 'Editar cliente' : 'Nuevo cliente'
                    }}</DialogTitle>
                    <DialogDescription class="text-left text-[#CFE3D6]">{{
                        clientEditingId
                            ? 'Actualiza los datos esenciales sin salir de la venta.'
                            : 'Registra los datos esenciales sin salir de la venta.'
                    }}</DialogDescription>
                </DialogHeader>
            </div>

            <form class="space-y-5 p-5" @submit.prevent="submitClient">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2 sm:col-span-2">
                        <Label for="cliente_razon_social">Razón social</Label>
                        <Input
                            id="cliente_razon_social"
                            v-model="clientForm.razon_social"
                            class="company-input"
                            autofocus
                        />
                        <InputError :message="clientErrors.razon_social?.[0]" />
                    </div>
                    <div class="space-y-2">
                        <Label for="cliente_tipo_documento"
                            >Tipo de documento SIAT</Label
                        >
                        <select
                            id="cliente_tipo_documento"
                            v-model="clientForm.tipo_documento_identidad"
                            class="company-select"
                        >
                            <option value="">Seleccione un tipo</option>
                            <option
                                v-for="documento in documentosIdentidad"
                                :key="String(documento.codigo_clasificador)"
                                :value="String(documento.codigo_clasificador)"
                            >
                                {{ documento.descripcion }}
                            </option>
                        </select>
                        <InputError
                            :message="
                                clientErrors.tipo_documento_identidad?.[0]
                            "
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="cliente_nit_ci">NIT / CI</Label>
                        <Input
                            id="cliente_nit_ci"
                            v-model="clientForm.nit_ci"
                            class="company-input"
                            :readonly="Boolean(lockedClientDocument)"
                        />
                        <InputError :message="clientErrors.nit_ci?.[0]" />
                    </div>
                    <div
                        v-if="
                            String(
                                clientForm.tipo_documento_identidad ?? '',
                            ) === '1'
                        "
                        class="space-y-2"
                    >
                        <Label for="cliente_complemento">Complemento</Label>
                        <Input
                            id="cliente_complemento"
                            v-model="clientForm.complemento"
                            class="company-input"
                        />
                        <InputError :message="clientErrors.complemento?.[0]" />
                    </div>
                    <div class="space-y-2">
                        <Label for="cliente_telefono">Teléfono</Label>
                        <Input
                            id="cliente_telefono"
                            v-model="clientForm.telefono"
                            class="company-input"
                        />
                        <InputError :message="clientErrors.telefono?.[0]" />
                    </div>
                    <div class="space-y-2">
                        <Label for="cliente_correo">Correo</Label>
                        <Input
                            id="cliente_correo"
                            v-model="clientForm.correo"
                            type="email"
                            class="company-input"
                        />
                        <InputError :message="clientErrors.correo?.[0]" />
                    </div>
                </div>

                <div
                    class="flex flex-col-reverse gap-2 border-t border-[#E4ECE7] pt-4 sm:flex-row sm:justify-end"
                >
                    <Button
                        type="button"
                        variant="outline"
                        @click="closeClientDialog"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        class="bg-[#168447] text-white hover:bg-[#126B3B]"
                        :disabled="clientSaving"
                    >
                        {{
                            clientSaving
                                ? 'Guardando...'
                                : clientEditingId
                                  ? 'Actualizar cliente'
                                  : 'Guardar cliente'
                        }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
