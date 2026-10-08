<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { CircleDollarSign } from 'lucide-vue-next';
import HorizontalScrollTabs from '@/components/ui/HorizontalScrollTabs.vue';

const page = usePage();
const isVendas = computed(() => {
    const url = page.url;
    return url === '/vendas' || url.startsWith('/vendas?') || url.startsWith('/vendas/');
});
</script>

<template>
    <HorizontalScrollTabs aria-label="Abas de vendas">
        <Link
            href="/vendas"
            :class="[
                'flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all duration-200',
                isVendas
                    ? 'bg-white text-[var(--color-primary)] shadow-sm'
                    : 'text-zinc-600 hover:text-zinc-900',
            ]"
        >
            <CircleDollarSign class="h-4 w-4 shrink-0" aria-hidden="true" />
            Vendas
        </Link>
    </HorizontalScrollTabs>
</template>
