import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useMayusculasFormulario() {
    const page = usePage<SharedData>();
    const mayusculasHabilitadas = computed(
        () => page.props.appFlags?.textosMayusculasHabilitados === true,
    );
    const normalizarTexto = (value: string) =>
        mayusculasHabilitadas.value ? value.toLocaleUpperCase('es') : value;

    return { mayusculasHabilitadas, normalizarTexto };
}
