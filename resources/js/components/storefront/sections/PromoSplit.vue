<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    section: {
        type: Object,
        default: () => ({}),
    },
});

const cfg = computed(() => {
    const merged = {
        ...(props.section?.props || {}),
        ...(props.section || {}),
    };
    return {
        ...merged,
        image_url: merged.image_url || merged.image || '',
        cta_url: merged.cta_url || merged.link || '',
        cta_label: merged.cta_label || merged.cta || '',
    };
});

const imageLeft = computed(() => cfg.value.image_side !== 'right');
</script>

<template>
    <section class="w-full max-w-[100vw] py-6 md:mx-auto md:max-w-[1360px] md:px-8 md:py-10">
        <div
            class="grid overflow-hidden md:grid-cols-2"
            :class="imageLeft ? '' : 'md:[&>*:first-child]:order-2'"
            :style="{ background: cfg.bg || 'var(--sf-secondary)', color: cfg.color || 'var(--sf-header-text)' }"
        >
            <div class="relative min-h-[220px] overflow-hidden sm:min-h-[260px] md:min-h-[300px]">
                <img
                    v-if="cfg.image_url"
                    :src="cfg.image_url"
                    :alt="cfg.title || 'Promoção'"
                    class="absolute inset-0 h-full w-full object-cover"
                />
                <div
                    v-else
                    class="sf-hero-placeholder absolute inset-0 flex items-center justify-center px-4"
                >
                    <span class="sf-wordmark sf-gold-text relative max-w-full break-words px-2 text-center text-base leading-tight tracking-[0.12em] sm:text-xl md:text-2xl">
                        {{ cfg.title || 'Coleção' }}
                    </span>
                </div>
            </div>

            <div class="flex flex-col justify-center gap-4 px-5 py-10 sm:px-8 sm:py-12 md:gap-5 md:px-14 md:py-14">
                <p class="sf-eyebrow !text-[var(--sf-accent-light)]">
                    {{ cfg.eyebrow || 'Seleção especial' }}
                </p>
                <h2 class="max-w-full break-words text-2xl leading-tight sm:text-3xl md:text-5xl">
                    {{ cfg.title || 'Oferta especial' }}
                </h2>
                <div class="sf-ornament !ml-0 w-28" :style="{ '--sf-bg': cfg.bg || 'var(--sf-secondary)' }" />
                <p v-if="cfg.text" class="text-sm leading-relaxed opacity-80">
                    {{ cfg.text }}
                </p>
                <ul v-if="Array.isArray(cfg.bullets) && cfg.bullets.length" class="space-y-2">
                    <li
                        v-for="(b, i) in cfg.bullets"
                        :key="i"
                        class="flex items-center gap-3 text-sm tracking-wide opacity-90"
                    >
                        <span class="h-1.5 w-1.5 shrink-0 rotate-45 bg-[var(--sf-accent-light)]" />
                        {{ b }}
                    </li>
                </ul>
                <div class="pt-2">
                    <Link
                        v-if="cfg.cta_url || cfg.link"
                        :href="cfg.cta_url || cfg.link"
                        class="sf-btn-gold inline-flex max-w-full px-7 py-3 text-[10px] font-semibold tracking-[0.2em] uppercase sm:px-9 sm:py-3.5 sm:text-[11px] sm:tracking-[0.25em]"
                    >
                        {{ cfg.cta_label || cfg.cta || 'Conferir' }}
                    </Link>
                </div>
            </div>
        </div>
    </section>
</template>
