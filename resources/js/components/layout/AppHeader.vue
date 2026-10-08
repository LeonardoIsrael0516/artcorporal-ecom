<script setup>
import { computed, inject } from 'vue';
import { PanelsTopLeft, Bell } from 'lucide-vue-next';
import { useSidebar } from '@/composables/useSidebar';
import UserMenu from '@/components/layout/UserMenu.vue';

defineProps({
    pageTitle: { type: String, default: null },
    pageTitleBadge: { type: String, default: null },
});

const { toggleSidebar, isMobileOpen, isMobile } = useSidebar();

const openNotificationsPanel = inject('openNotificationsPanel', () => {});
const notificationsUnreadCount = inject('notificationsUnreadCount', { value: 0 });
const unreadBadge = computed(() => Math.max(0, notificationsUnreadCount?.value ?? 0));
</script>

<template>
    <header class="z-[99998] flex shrink-0 w-full items-center justify-between gap-4 bg-transparent px-4 py-3 lg:px-6 lg:py-4">
        <div class="flex min-w-0 flex-1 items-center gap-3">
            <button
                v-if="isMobile && !isMobileOpen"
                type="button"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700"
                aria-label="Abrir menu"
                @click="toggleSidebar"
            >
                <PanelsTopLeft class="h-5 w-5" aria-hidden="true" />
            </button>
            <template v-if="pageTitle">
                <h1 class="truncate text-xl font-bold text-zinc-900 md:text-2xl">
                    {{ pageTitle }}
                </h1>
                <span
                    v-if="pageTitleBadge"
                    class="shrink-0 truncate max-w-[160px] md:max-w-[220px] rounded-md bg-[var(--color-primary)]/15 px-2.5 py-0.5 text-xs font-medium text-[var(--color-primary)]"
                    :title="pageTitleBadge"
                >
                    {{ pageTitleBadge }}
                </span>
            </template>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <button
                type="button"
                class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-zinc-200/50 bg-white/50 text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700"
                aria-label="Notificações"
                @click="openNotificationsPanel()"
            >
                <Bell class="h-5 w-5" aria-hidden="true" />
                <span
                    v-if="unreadBadge > 0"
                    class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-[var(--color-primary)] px-1 text-[10px] font-semibold text-white"
                >
                    {{ unreadBadge > 99 ? '99+' : unreadBadge }}
                </span>
            </button>
            <UserMenu />
        </div>
    </header>
</template>
