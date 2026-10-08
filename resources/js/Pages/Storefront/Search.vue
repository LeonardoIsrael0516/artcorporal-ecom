<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import axios from 'axios';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import ProductCard from '@/components/storefront/ProductCard.vue';

const props = defineProps({
    query: {
        type: String,
        default: '',
    },
    products: {
        type: [Array, Object],
        default: () => [],
    },
});

const items = computed(() => {
    if (Array.isArray(props.products)) return props.products;
    return props.products?.data || [];
});

const pagination = computed(() => {
    if (Array.isArray(props.products)) return null;
    return props.products?.links ? props.products : null;
});

async function onBuy(product) {
    if (!product?.id) return;
    try {
        await axios.post('/carrinho/add', {
            product_id: product.id,
            quantity: 1,
        });
        window.location.href = '/carrinho';
    } catch (_) {
        window.location.href = `/produto/${product.slug}`;
    }
}
</script>

<template>
    <StorefrontLayout>
        <Head :title="query ? `Busca: ${query}` : 'Busca'" />

        <div class="mx-auto max-w-[1360px] px-4 py-10 md:px-8">
            <h1 class="mb-2 text-2xl font-semibold tracking-wide uppercase">
                Resultados da busca
            </h1>
            <p class="mb-8 text-sm text-zinc-600">
                <template v-if="query">
                    Buscando por “{{ query }}” — {{ items.length }} resultado(s)
                </template>
                <template v-else>
                    Digite um termo na busca acima.
                </template>
            </p>

            <form action="/busca" method="get" class="mb-10 flex max-w-lg">
                <input
                    type="search"
                    name="q"
                    :value="query"
                    placeholder="O que você procura?"
                    class="w-full border border-[var(--sf-primary)]/40 bg-white px-3 py-2.5 text-sm outline-none focus:border-[var(--sf-secondary)]"
                />
                <button type="submit" class="sf-btn px-6 text-[11px] font-semibold tracking-[0.2em] uppercase">
                    Buscar
                </button>
            </form>

            <div
                v-if="items.length"
                class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 lg:gap-6"
            >
                <ProductCard
                    v-for="product in items"
                    :key="product.id || product.slug"
                    :product="product"
                    @buy="onBuy"
                />
            </div>

            <p v-else-if="query" class="py-12 text-center text-sm text-zinc-500">
                Nenhum produto encontrado para “{{ query }}”.
            </p>

            <div
                v-if="pagination?.links?.length > 3"
                class="mt-10 flex flex-wrap justify-center gap-2"
            >
                <template v-for="(link, i) in pagination.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="border px-3 py-1.5 text-sm"
                        :class="link.active ? 'border-[var(--sf-secondary)] bg-[var(--sf-secondary)] text-[var(--sf-accent-light)]' : 'border-[var(--sf-primary)]/30 hover:border-[var(--sf-primary)]'"
                        v-html="link.label"
                    />
                    <span v-else class="px-3 py-1.5 text-sm text-zinc-400" v-html="link.label" />
                </template>
            </div>
        </div>
    </StorefrontLayout>
</template>
