<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import axios from 'axios';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import ProductCard from '@/components/storefront/ProductCard.vue';

const props = defineProps({
    category: {
        type: Object,
        default: () => ({}),
    },
    products: {
        type: [Array, Object],
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
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
        <Head :title="category.name || 'Categoria'" />

        <div class="mx-auto max-w-[1360px] px-4 py-10 md:px-8">
            <nav class="mb-6 text-xs text-zinc-500">
                <Link href="/" class="hover:text-[var(--sf-primary)]">Início</Link>
                <span class="mx-2">/</span>
                <span class="text-[var(--sf-text)]">{{ category.name }}</span>
            </nav>

            <div class="mb-10 flex flex-col items-center text-center">
                <p class="sf-eyebrow">Coleção</p>
                <h1 class="mt-2 text-4xl text-[var(--sf-secondary)] md:text-5xl">
                    {{ category.name }}
                </h1>
                <div class="sf-ornament mt-4 w-28" />
                <p v-if="category.description" class="mt-4 max-w-2xl text-sm text-[var(--sf-text)]/70">
                    {{ category.description }}
                </p>
            </div>

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

            <p v-else class="py-16 text-center text-sm text-zinc-500">
                Nenhum produto encontrado nesta categoria.
            </p>

            <div
                v-if="pagination?.links?.length > 3"
                class="mt-10 flex flex-wrap justify-center gap-2"
            >
                <template v-for="(link, i) in pagination.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="px-3 py-1.5 text-sm border"
                        :class="link.active
                            ? 'border-[var(--sf-secondary)] bg-[var(--sf-secondary)] text-[var(--sf-accent-light)]'
                            : 'border-[var(--sf-primary)]/30 hover:border-[var(--sf-primary)]'"
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="px-3 py-1.5 text-sm text-zinc-400"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </StorefrontLayout>
</template>
