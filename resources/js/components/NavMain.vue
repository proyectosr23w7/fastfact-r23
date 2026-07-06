<script setup lang="ts">
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { urlIsActive } from '@/lib/utils';
import { type NavGroup } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

const props = defineProps<{
    groups: NavGroup[];
}>();

const page = usePage();
const openGroups = reactive<Record<string, boolean>>({});

const groupIsActive = (group: NavGroup) =>
    group.items.some((item) => urlIsActive(item.href, page.url));

const syncOpenGroup = () => {
    props.groups.forEach((group) => {
        openGroups[group.title] = groupIsActive(group);
    });
};

const setGroupOpen = (target: NavGroup, open: boolean) => {
    props.groups.forEach((group) => {
        openGroups[group.title] = group.title === target.title ? open : false;
    });
};

watch(
    () => [page.url, props.groups.map((group) => group.title).join('|')],
    syncOpenGroup,
    { immediate: true },
);
</script>

<template>
    <div class="space-y-3 px-2 py-0">
        <Collapsible
            v-for="group in groups"
            :key="group.title"
            v-slot="{ open }"
            :open="openGroups[group.title]"
            class="space-y-1"
            @update:open="setGroupOpen(group, $event)"
        >
            <SidebarGroup class="px-0 py-0">
                <CollapsibleTrigger as-child>
                    <button
                        type="button"
                        class="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-[10px] font-bold tracking-[0.12em] text-[#35D978] uppercase transition hover:bg-white/5 focus-visible:ring-2 focus-visible:ring-[#35D978]"
                    >
                        <span>{{ group.title }}</span>
                        <ChevronRight
                            :class="[
                                'h-4 w-4 transition-transform',
                                open ? 'rotate-90' : '',
                            ]"
                        />
                    </button>
                </CollapsibleTrigger>

                <CollapsibleContent
                    class="overflow-hidden data-[state=closed]:animate-accordion-up data-[state=open]:animate-accordion-down"
                >
                    <SidebarGroupContent class="pt-1">
                        <SidebarMenu>
                            <SidebarMenuItem
                                v-for="item in group.items"
                                :key="item.title"
                            >
                                <SidebarMenuButton
                                    as-child
                                    :is-active="
                                        urlIsActive(item.href, page.url)
                                    "
                                    :tooltip="item.title"
                                >
                                    <Link :href="item.href">
                                        <component :is="item.icon" />
                                        <span>{{ item.title }}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </CollapsibleContent>
            </SidebarGroup>
        </Collapsible>
    </div>
</template>
