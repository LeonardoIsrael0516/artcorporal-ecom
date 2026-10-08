<script setup>
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import SectionRenderer from '@/components/storefront/sections/SectionRenderer.vue';

const props = defineProps({
    sections: {
        type: Array,
        default: () => [],
    },
    sectionData: {
        type: Object,
        default: () => ({}),
    },
    storeName: {
        type: String,
        default: 'Loja',
    },
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
        <Head :title="storeName" />
        <SectionRenderer
            v-for="section in sections"
            :key="section.id || section.type"
            :section="section"
            :section-data="sectionData"
            @buy="onBuy"
        />
    </StorefrontLayout>
</template>
