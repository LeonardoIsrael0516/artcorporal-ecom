<script setup>
import { computed } from 'vue';
import HeroSlider from '@/components/storefront/sections/HeroSlider.vue';
import CategoryCircles from '@/components/storefront/sections/CategoryCircles.vue';
import ProductShelf from '@/components/storefront/sections/ProductShelf.vue';
import TrustBar from '@/components/storefront/sections/TrustBar.vue';
import PromoSplit from '@/components/storefront/sections/PromoSplit.vue';
import InstagramGrid from '@/components/storefront/sections/InstagramGrid.vue';
import NewsletterSection from '@/components/storefront/sections/NewsletterSection.vue';

const props = defineProps({
    section: {
        type: Object,
        required: true,
    },
    sectionData: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['buy']);

const type = computed(() => String(props.section?.type || '').toLowerCase());

const resolved = computed(() => {
    const id = props.section?.id;
    const data = id != null ? props.sectionData?.[id] ?? props.sectionData?.[String(id)] : null;
    return data && typeof data === 'object' ? data : {};
});

const mergedSection = computed(() => ({
    ...props.section,
    props: {
        ...(props.section?.props || {}),
        ...resolved.value,
    },
    ...resolved.value,
}));
</script>

<template>
    <HeroSlider v-if="type === 'hero' || type === 'hero_slider'" :section="mergedSection" />

    <CategoryCircles
        v-else-if="type === 'categories' || type === 'category_circles'"
        :section="mergedSection"
        :categories="resolved.categories || mergedSection.categories"
    />

    <ProductShelf
        v-else-if="type === 'products' || type === 'product_shelf' || type === 'shelf'"
        :section="mergedSection"
        :products="resolved.products || mergedSection.products"
        :title="resolved.title || mergedSection.title"
        @buy="emit('buy', $event)"
    />

    <TrustBar
        v-else-if="type === 'trust' || type === 'trust_bar'"
        :section="mergedSection"
        :items="resolved.items || mergedSection.items"
    />

    <PromoSplit
        v-else-if="type === 'promo' || type === 'promo_split'"
        :section="mergedSection"
    />

    <InstagramGrid
        v-else-if="type === 'instagram' || type === 'instagram_grid'"
        :section="mergedSection"
        :posts="resolved.posts || resolved.items || mergedSection.posts || mergedSection.items || mergedSection.props?.items"
    />

    <NewsletterSection
        v-else-if="type === 'newsletter' || type === 'newsletter_section'"
        :section="mergedSection"
    />

    <div
        v-else-if="type"
        class="mx-auto max-w-[1360px] px-4 py-6 text-center text-sm text-zinc-400"
    >
        Seção desconhecida: {{ type }}
    </div>
</template>
