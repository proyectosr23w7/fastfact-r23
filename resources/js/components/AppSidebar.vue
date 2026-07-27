<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Sidebar, useSidebar } from '@/components/ui/sidebar';
import { performLogout } from '@/lib/logout';
import { urlIsActive } from '@/lib/utils';
import { dashboard } from '@/routes';
import { usePermissionStore } from '@/src/stores/permissionStore';
import type { SharedData } from '@/types';
import { type NavGroup, type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    BarChart3,
    BookOpen,
    Building2,
    Cable,
    CloudCog,
    Cog,
    FileBadge2,
    LayoutGrid,
    LockKeyhole,
    LogOut,
    Package,
    ReceiptText,
    Store,
    Users,
    WalletCards,
} from 'lucide-vue-next';
import { computed } from 'vue';

const permissionStore = usePermissionStore();
const page = usePage<SharedData>();
const { isMobile, setOpenMobile } = useSidebar();

const handleNavClick = () => {
    if (isMobile.value) {
        setOpenMobile(false);
    }
};

const handleLogout = () => performLogout(handleNavClick);

const navGroups: NavGroup[] = [
    {
        title: 'Principal',
        items: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
        ],
    },
    {
        title: 'Administracion',
        items: [
            {
                title: 'Empresa',
                href: '/configuracion/empresa',
                icon: Building2,
                permission: 'configuracion.empresa.view',
            },
            {
                title: 'Parametros',
                href: '/configuracion/configuracion',
                icon: Cog,
                permission: 'configuracion.general.manage',
            },
            {
                title: 'Sucursales y puntos de venta',
                href: '/configuracion/sucursales',
                icon: Store,
                permission: 'configuracion.sucursales.manage',
            },
        ],
    },
    {
        title: 'Facturacion',
        feature: 'facturacionActiva',
        items: [
            {
                title: 'Facturas',
                href: '/facturacion/facturas',
                icon: ReceiptText,
                permission: 'facturacion.facturas.view',
            },
            {
                title: 'Productos',
                href: '/facturacion/productos',
                icon: Package,
                permission: 'facturacion.productos.manage',
            },
            {
                title: 'Clientes',
                href: '/facturacion/clientes',
                icon: Users,
                permission: 'ventas.clientes.manage',
            },
            {
                title: 'Catalogos SIAT',
                href: '/facturacion/catalogos-siat',
                icon: WalletCards,
                permission: 'facturacion.catalogos.manage',
            },
            {
                title: 'CUIS',
                href: '/facturacion/cuis',
                icon: BadgeDollarSign,
                permission: 'facturacion.siat.sync',
            },
            {
                title: 'CUFD y CAFC',
                href: '/facturacion/cufd',
                icon: FileBadge2,
                permission: 'facturacion.siat.sync',
            },
            {
                title: 'Sincronizacion SIAT',
                href: '/facturacion/sincronizaciones-siat',
                icon: CloudCog,
                permission: 'facturacion.siat.sync',
            },
            {
                title: 'Eventos significativos',
                href: '/facturacion/eventos-significativos',
                icon: BookOpen,
                permission: 'facturacion.siat.sync',
            },
        ],
    },
    {
        title: 'Reportes',
        items: [
            {
                title: 'Facturacion',
                href: '/reportes/facturacion',
                icon: BarChart3,
                permission: 'reportes.access',
            },
        ],
    },
    {
        title: 'Accesos',
        items: [
            {
                title: 'Usuarios y roles',
                href: '/seguridad/usuarios',
                icon: LockKeyhole,
                permission: 'seguridad.usuarios.manage',
            },
            {
                title: 'Roles',
                href: '/seguridad/roles',
                icon: Users,
                permission: 'seguridad.roles.manage',
            },
            {
                title: 'Permisos',
                href: '/seguridad/permisos',
                icon: BookOpen,
                permission: 'seguridad.roles.manage',
            },
            {
                title: 'Tokens API',
                href: '/seguridad/tokens-integracion',
                icon: Cable,
                permission: 'seguridad.usuarios.manage',
            },
        ],
    },
];

const visibleNavGroups = computed(() =>
    navGroups
        .filter(
            (group) =>
                !group.feature || Boolean(page.props.appFlags?.[group.feature]),
        )
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item: NavItem) =>
                    (!item.feature ||
                        Boolean(page.props.appFlags?.[item.feature])) &&
                    (!item.permission ||
                        permissionStore.hasPermission(item.permission)),
            ),
        }))
        .filter((group) => group.items.length > 0),
);
</script>

<template>
    <Sidebar
        class="border-r border-white/10 bg-[#071710] text-[#dcece3] shadow-[18px_0_45px_-42px_rgba(7,23,16,0.95)]"
    >
        <div class="flex min-h-0 flex-1 flex-col bg-[#071710] text-[#dcece3]">
            <div class="border-b border-white/10 px-3 py-4">
                <Link
                    :href="dashboard()"
                    class="flex items-center gap-2 rounded-lg px-2 py-2 transition hover:bg-white/6 focus-visible:ring-2 focus-visible:ring-[#35D978] focus-visible:outline-none"
                >
                    <AppLogo />
                </Link>
            </div>

            <div class="px-3 pt-4">
                <div
                    class="rounded-lg border border-white/10 bg-white/[0.06] p-3"
                >
                    <p
                        class="text-[10px] font-black tracking-[0.14em] text-white/45 uppercase"
                    >
                        Operacion
                    </p>
                    <p class="mt-2 truncate text-sm font-black text-white">
                        Central - Punto 0
                    </p>
                    <p class="mt-1 text-xs text-white/55">Produccion SIAT</p>
                </div>
            </div>

            <nav class="min-h-0 flex-1 overflow-y-auto px-3 py-4">
                <div
                    v-for="group in visibleNavGroups"
                    :key="group.title"
                    class="mb-5 last:mb-0"
                >
                    <p
                        class="px-3 pb-2 text-[11px] font-bold tracking-[0.12em] text-white/45 uppercase"
                    >
                        {{ group.title }}
                    </p>

                    <div class="space-y-1">
                        <Link
                            v-for="item in group.items"
                            :key="item.title"
                            :href="item.href"
                            @click="handleNavClick"
                            :class="[
                                'flex min-h-10 items-center gap-3 rounded-md px-3 text-sm transition focus-visible:ring-2 focus-visible:ring-[#35D978] focus-visible:outline-none',
                                urlIsActive(item.href, page.url)
                                    ? 'bg-[#168447] font-bold text-white shadow-[0_10px_24px_-18px_rgba(22,132,71,0.9)]'
                                    : 'font-medium text-white/78 hover:bg-white/[0.08] hover:text-white',
                            ]"
                        >
                            <component
                                :is="item.icon"
                                class="size-4 shrink-0"
                                :class="
                                    urlIsActive(item.href, page.url)
                                        ? 'text-white'
                                        : 'text-white/58'
                                "
                            />
                            <span class="min-w-0 flex-1 truncate">{{
                                item.title
                            }}</span>
                        </Link>
                    </div>
                </div>
            </nav>

            <div class="border-t border-white/10 p-3">
                <button
                    type="button"
                    class="flex min-h-10 w-full items-center gap-3 rounded-md px-3 text-sm font-bold text-red-200 transition hover:bg-red-500/12 hover:text-red-100 focus-visible:ring-2 focus-visible:ring-red-300 focus-visible:outline-none"
                    @click="handleLogout"
                >
                    <LogOut class="size-4 shrink-0" />
                    <span>Cerrar sesion</span>
                </button>
            </div>
        </div>
    </Sidebar>
    <slot />
</template>
