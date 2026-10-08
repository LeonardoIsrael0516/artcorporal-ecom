<script setup>
import { computed } from 'vue';

const props = defineProps({
    section: {
        type: Object,
        default: () => ({}),
    },
    items: {
        type: Array,
        default: null,
    },
});

const trustItems = computed(() => {
    if (Array.isArray(props.items) && props.items.length) return props.items;
    const fromSection = props.section?.items ?? props.section?.props?.items ?? [
        { title: 'Frete para todo o Brasil', text: 'Envio rápido e seguro' },
        { title: 'Pagamento seguro', text: 'Pix, cartão e boleto' },
        { title: 'Troca facilitada', text: 'Política clara e transparente' },
        { title: 'Atendimento', text: 'Suporte humanizado' },
    ];
    return Array.isArray(fromSection) ? fromSection : [];
});

const ICONS = ['shield', 'refresh', 'truck', 'whatsapp'];

function iconFor(item) {
    return ICONS.includes(item?.icon) ? item.icon : 'star';
}
</script>

<template>
    <section class="my-6 bg-[var(--sf-secondary)] text-[var(--sf-header-text)]">
        <div class="mx-auto grid max-w-[1360px] grid-cols-2 gap-y-8 px-4 py-10 md:grid-cols-4 md:px-8">
            <div
                v-for="(item, i) in trustItems"
                :key="i"
                class="flex flex-col items-center px-3 text-center md:border-l md:border-[var(--sf-primary)]/20 md:first:border-l-0"
            >
                <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-full border border-[var(--sf-primary)]/50 text-[var(--sf-accent-light)]">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path v-if="iconFor(item) === 'shield'" d="M12 3 5 6v5c0 4.5 3 8.5 7 10 4-1.5 7-5.5 7-10V6l-7-3Zm-3 9 2 2 4-4" />
                        <path v-else-if="iconFor(item) === 'refresh'" d="M20 11a8 8 0 0 0-14.3-4.9L4 8m0-5v5h5M4 13a8 8 0 0 0 14.3 4.9L20 16m0 5v-5h-5" />
                        <path v-else-if="iconFor(item) === 'truck'" d="M3 6h11v10H3zM14 10h4l3 3v3h-7M7.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm10 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" />
                        <path v-else-if="iconFor(item) === 'whatsapp'" d="M4 20l1.3-3.9A8 8 0 1 1 8 19l-4 1Zm5-11c0 3.5 2.5 6 6 6l1-1.5-2-1-1 1c-1-.5-2-1.5-2.5-2.5l1-1-1-2L9 9Z" />
                        <path v-else d="M12 3l2.6 5.6 6 .7-4.5 4.1 1.2 6L12 16.4 6.7 19.4l1.2-6L3.4 9.3l6-.7L12 3Z" />
                    </svg>
                </span>
                <p class="text-[11px] font-semibold tracking-[0.2em] uppercase text-[var(--sf-accent-light)]">
                    {{ item.title }}
                </p>
                <p v-if="item.text" class="mt-1.5 text-xs text-[var(--sf-header-text)]/65">
                    {{ item.text }}
                </p>
            </div>
        </div>
    </section>
</template>
