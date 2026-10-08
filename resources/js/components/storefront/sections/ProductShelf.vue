<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProductCard from '@/components/storefront/ProductCard.vue';

const props = defineProps({
    section: {
        type: Object,
        default: () => ({}),
    },
    products: {
        type: Array,
        default: null,
    },
    title: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['buy']);

const shelfTitle = computed(
    () => props.title ?? props.section?.title ?? props.section?.props?.title ?? 'Destaques',
);

const items = computed(() => {
    if (Array.isArray(props.products)) return props.products;
    const fromSection = props.section?.products ?? props.section?.props?.products ?? [];
    return Array.isArray(fromSection) ? fromSection : [];
});

const viewAllUrl = computed(
    () => props.section?.view_all_url ?? props.section?.props?.view_all_url ?? null,
);
</script>

<template>
    <section v-if="items.length" class="mx-auto w-full max-w-[1360px] px-4 py-10 md:px-6 lg:px-8">
        <div class="mb-10 flex flex-col items-center text-center">
            <h2 class="text-3xl text-[var(--sf-secondary)] md:text-4xl">
                {{ shelfTitle }}
            </h2>
            <div class="sf-ornament mt-4 w-28" />
            <Link
                v-if="viewAllUrl"
                :href="viewAllUrl"
                class="mt-4 text-[11px] font-semibold tracking-[0.25em] uppercase text-[var(--sf-primary)] transition hover:text-[var(--sf-secondary)]"
            >
                Ver todos
            </Link>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 lg:gap-5">
            <ProductCard
                v-for="product in items"
                :key="product.id || product.slug"
                :product="product"
                @buy="emit('buy', $event)"
            />
        </div>
    </section>
</template>
