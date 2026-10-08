<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    section: {
        type: Object,
        default: () => ({}),
    },
    categories: {
        type: Array,
        default: null,
    },
});

const items = computed(() => {
    if (Array.isArray(props.categories) && props.categories.length) {
        return props.categories;
    }
    const fromSection = props.section?.categories ?? props.section?.props?.categories ?? [];
    return Array.isArray(fromSection) ? fromSection : [];
});

const title = computed(() => props.section?.title ?? props.section?.props?.title ?? 'Categorias');
</script>

<template>
    <section v-if="items.length" class="mx-auto w-full max-w-[1360px] px-4 py-10 md:px-6 lg:px-8">
        <h2 v-if="title" class="mb-8 text-left text-3xl text-[var(--sf-secondary)]">
            {{ title }}
        </h2>

        <div class="flex items-start justify-center gap-4 overflow-x-auto pb-2 md:gap-5 lg:gap-6">
            <Link
                v-for="cat in items"
                :key="cat.slug || cat.id || cat.name"
                :href="`/categoria/${cat.slug}`"
                class="group flex w-[5.25rem] shrink-0 flex-col items-center gap-2.5 text-center sm:w-24 md:w-[6.5rem] lg:w-28"
            >
                <span
                    class="flex h-[5.25rem] w-[5.25rem] items-center justify-center overflow-hidden rounded-full p-[2px] transition duration-500 group-hover:scale-[1.04] sm:h-24 sm:w-24 md:h-[6.5rem] md:w-[6.5rem] lg:h-28 lg:w-28"
                    style="background: var(--sf-gold-gradient)"
                >
                    <span class="flex h-full w-full items-center justify-center overflow-hidden rounded-full border-2 border-[var(--sf-bg)] bg-[var(--sf-secondary)]">
                        <img
                            v-if="cat.image_url"
                            :src="cat.image_url"
                            :alt="cat.name"
                            class="h-full w-full object-cover"
                            loading="lazy"
                        />
                        <span
                            v-else
                            class="sf-wordmark sf-gold-text text-3xl"
                        >
                            {{ (cat.name || '?').charAt(0).toUpperCase() }}
                        </span>
                    </span>
                </span>
                <span class="text-[11px] font-medium tracking-[0.12em] uppercase text-[var(--sf-text)] transition group-hover:text-[var(--sf-primary)]">
                    {{ cat.name }}
                </span>
            </Link>
        </div>
    </section>
</template>
