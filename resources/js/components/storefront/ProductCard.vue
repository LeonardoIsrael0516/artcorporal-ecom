<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, getCurrentInstance } from 'vue';

const page = usePage();
const storeName = computed(() => page.props.storeTheme?.brand?.name || 'Loja');

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['buy']);

const href = computed(() => `/produto/${props.product.slug}`);

const formattedPrice = computed(() => formatMoney(props.product.price));
const formattedPix = computed(() =>
    props.product.pix_price != null ? formatMoney(props.product.pix_price) : null,
);

function formatMoney(value) {
    const n = Number(value ?? 0);
    return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function onBuy() {
    const hasHandler = Boolean(getCurrentInstance()?.vnode?.props?.onBuy);
    if (hasHandler) {
        emit('buy', props.product);
        return;
    }
    router.visit(href.value);
}
</script>

<template>
    <article class="group flex flex-col">
        <Link
            :href="href"
            class="relative block aspect-square overflow-hidden bg-white ring-1 ring-[var(--sf-primary)]/15 transition duration-500 group-hover:ring-[var(--sf-primary)]/50"
        >
            <img
                v-if="product.image_url"
                :src="product.image_url"
                :alt="product.name"
                class="h-full w-full object-cover transition duration-700 group-hover:scale-105"
                loading="lazy"
            />
            <div
                v-else
                class="flex h-full w-full flex-col items-center justify-center gap-2 bg-gradient-to-br from-[var(--sf-secondary)] to-[#1b3a2c]"
            >
                <span class="sf-wordmark sf-gold-text text-sm">{{ storeName }}</span>
                <span class="h-px w-10 bg-[var(--sf-primary)]/60" />
            </div>
        </Link>

        <div class="flex flex-1 flex-col gap-1 px-1 pt-4 pb-4 text-center">
            <Link
                :href="href"
                class="line-clamp-2 text-[13px] leading-snug tracking-wide text-[var(--sf-text)] transition hover:text-[var(--sf-primary)]"
            >
                {{ product.name }}
            </Link>

            <p class="sf-serif mt-1 text-xl text-[var(--sf-secondary)]">
                {{ formattedPrice }}
            </p>

            <p v-if="formattedPix" class="text-xs font-semibold text-[var(--sf-primary)]">
                {{ formattedPix }} no Pix
            </p>

            <p v-if="product.installment_text" class="text-xs text-[var(--sf-text)]/55">
                {{ product.installment_text }}
            </p>

            <div class="mt-auto pt-3">
                <button
                    type="button"
                    class="sf-btn-gold w-full px-4 py-3.5 text-[11px] font-semibold tracking-[0.2em] uppercase"
                    @click="onBuy"
                >
                    Comprar
                </button>
            </div>
        </div>
    </article>
</template>
