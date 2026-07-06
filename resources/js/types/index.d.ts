import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User | null;
    roles: Role[];
    permissions: string[];
    operador: Operador | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    permission?: string;
    feature?: 'facturacionActiva';
}

export interface NavGroup {
    title: string;
    items: NavItem[];
    feature?: 'facturacionActiva';
}

export interface SharedData extends PageProps {
    [key: string]: unknown;
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    appFlags?: {
        empresaConfigurada: boolean;
        facturacionActiva?: boolean;
        facturacionElectronicaActiva?: boolean;
    };
    appContext?: {
        empresa: string;
        sucursal: string;
        puntoVenta?: string | null;
    };
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    appFlags?: {
        empresaConfigurada: boolean;
        facturacionActiva?: boolean;
        facturacionElectronicaActiva?: boolean;
    };
    appContext?: {
        empresa: string;
        sucursal: string;
        puntoVenta?: string | null;
    };
};

export interface User {
    id: number;
    name: string;
    email: string;
    estado?: boolean;
    roles?: Role[];
    permission_slugs?: string[];
    operador?: Operador | null;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface Sucursal {
    id: number;
    codigo_sucursal: number;
    nombre: string;
    es_global: boolean;
    activo: boolean;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface Puesto {
    id: number;
    nombre: string;
    descripcion?: string | null;
    personal_count?: number;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface Personal {
    id: number;
    nombre_completo: string;
    telefono?: string | null;
    numero_documento: string;
    fecha_ingreso?: string | null;
    puesto_id?: number | null;
    activo: boolean;
    puesto?: { id: number; nombre: string } | null;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface Permiso {
    id: number;
    nombre: string;
    slug: string;
    descripcion: string;
    modulo: string;
    estado?: boolean;
    roles_count?: number;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface Role {
    id: number;
    nombre: string;
    slug: string;
    descripcion?: string | null;
    estado?: boolean;
    permissions?: Permiso[];
    permissions_count?: number;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface Operador {
    id: number;
    user_id: number;
    sucursal_id: number;
    punto_venta_id: number;
    estado: boolean;
    sucursal?: {
        id: number;
        codigo: number;
        nombre: string;
    } | null;
    punto_venta?: {
        id: number;
        codigo: number;
        nombre: string;
    } | null;
}
