<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Package, TicketPercent } from 'lucide-vue-next';
import HorizontalScrollTabs from '@/components/ui/HorizontalScrollTabs.vue';

const page = usePage();
const isProdutos = computed(() => {
    const url = page.url;
    return url === '/produtos' || url.startsWith('/produtos?') || url.match(/^\/produtos\/\d+/);
});
const isCupons = computed(() => page.url.startsWith('/produtos/cupons'));
</script>

<template>
    <HorizontalScrollTabs aria-label="Abas de produtos">
        <Link
            href="/produtos"
            :class="[
                'flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all duration-200',
                isProdutos
                    ? 'bg-white text-[var(--color-primary)] shadow-sm'
                    : 'text-zinc-600 hover:text-zinc-900',
            ]"
        >
            <Package class="h-4 w-4 shrink-0" aria-hidden="true" />
            Produtos
        </Link>
        <Link
            href="/produtos/cupons"
            :class="[
                'flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all duration-200',
                isCupons
                    ? 'bg-white text-[var(--color-primary)] shadow-sm'
                    : 'text-zinc-600 hover:text-zinc-900',
            ]"
        >
            <TicketPercent class="h-4 w-4 shrink-0" aria-hidden="true" />
            Cupons
        </Link>
    </HorizontalScrollTabs>
</template>
