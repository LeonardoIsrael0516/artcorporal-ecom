<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    section: {
        type: Object,
        default: () => ({}),
    },
});

const slides = computed(() => {
    const list = props.section?.slides ?? props.section?.props?.slides ?? [];
    if (!Array.isArray(list)) return [];
    return list.map((slide) => ({
        ...slide,
        image_url: slide.image_url || slide.image || '',
        image_mobile_url: slide.image_mobile_url || slide.image_mobile || '',
        cta_label: slide.cta_label || slide.cta || '',
        cta_url: slide.cta_url || slide.link || '',
    }));
});

const index = ref(0);
let timer = null;

const current = computed(() => slides.value[index.value] || null);
const autoplayMs = computed(() => {
    const raw = Number(props.section?.autoplay_ms ?? props.section?.props?.autoplay_ms ?? 4500);
    return Number.isFinite(raw) && raw > 0 ? raw : 4500;
});

function next() {
    if (slides.value.length < 2) return;
    index.value = (index.value + 1) % slides.value.length;
}

function prev() {
    if (slides.value.length < 2) return;
    index.value = (index.value - 1 + slides.value.length) % slides.value.length;
}

function goTo(i) {
    index.value = i;
    startAutoplay();
}

function startAutoplay() {
    stopAutoplay();
    if (slides.value.length < 2) return;
    if (typeof document !== 'undefined' && document.hidden) return;
    timer = window.setInterval(next, Math.max(3000, autoplayMs.value));
}

function stopAutoplay() {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
}

function onVisibility() {
    if (document.hidden) stopAutoplay();
    else startAutoplay();
}

watch(slides, () => {
    if (index.value >= slides.value.length) index.value = 0;
    startAutoplay();
});

onMounted(() => {
    startAutoplay();
    document.addEventListener('visibilitychange', onVisibility);
});

onUnmounted(() => {
    stopAutoplay();
    document.removeEventListener('visibilitychange', onVisibility);
});

function desktopSrc(slide) {
    return slide.image_url || slide.image_mobile_url || '';
}

function mobileSrc(slide) {
    return slide.image_mobile_url || slide.image_url || '';
}
</script>

<template>
    <section
        v-if="slides.length"
        class="relative w-full max-w-[100vw] overflow-hidden bg-[var(--sf-secondary)]"
        @mouseenter="stopAutoplay"
        @mouseleave="startAutoplay"
        @focusin="stopAutoplay"
        @focusout="startAutoplay"
    >
        <div class="relative aspect-[3/4] min-h-[380px] max-h-[78vh] w-full sm:aspect-[4/5] sm:min-h-[480px] md:aspect-[16/7] md:min-h-[360px] md:max-h-none">
            <template v-for="(slide, i) in slides" :key="i">
                <div
                    class="absolute inset-0 transition-opacity duration-700 ease-out"
                    :class="i === index ? 'opacity-100 z-10' : 'opacity-0 z-0 pointer-events-none'"
                    :aria-hidden="i !== index"
                >
                    <picture v-if="desktopSrc(slide) || mobileSrc(slide)">
                        <source
                            v-if="slide.image_mobile_url"
                            media="(max-width: 767px)"
                            :srcset="mobileSrc(slide)"
                        />
                        <img
                            :src="desktopSrc(slide)"
                            :alt="slide.title || 'Banner'"
                            class="h-full w-full object-cover object-center"
                            :loading="i === 0 ? 'eager' : 'lazy'"
                        />
                    </picture>
                    <div
                        v-else
                        class="sf-hero-placeholder absolute inset-0"
                        aria-hidden="true"
                    />

                    <div
                        v-if="slide.title || slide.subtitle || slide.cta_label"
                        class="absolute inset-0 flex items-end md:items-center"
                        :class="
                            desktopSrc(slide) || mobileSrc(slide)
                                ? 'bg-gradient-to-t from-[var(--sf-secondary)]/90 via-[var(--sf-secondary)]/45 to-[var(--sf-secondary)]/10 md:bg-gradient-to-r md:from-[var(--sf-secondary)]/85 md:via-[var(--sf-secondary)]/35 md:to-transparent'
                                : ''
                        "
                    >
                        <div
                            class="box-border w-full max-w-[1360px] px-4 pb-12 pt-8 text-[var(--sf-header-text)] sm:px-6 sm:pb-14 sm:pt-10 md:mx-auto md:px-8 md:py-10"
                            :class="desktopSrc(slide) || mobileSrc(slide) ? '' : 'text-center'"
                        >
                            <p class="sf-eyebrow !text-[var(--sf-accent-light)]">
                                {{ slide.eyebrow || 'Body Piercing' }}
                            </p>
                            <h2
                                v-if="slide.title"
                                class="mt-2 w-full max-w-full break-words text-[1.65rem] leading-[1.15] sm:text-[1.85rem] sm:leading-[1.12] md:mt-3 md:text-6xl"
                                :class="
                                    desktopSrc(slide) || mobileSrc(slide)
                                        ? 'md:max-w-2xl'
                                        : 'sf-gold-text mx-auto md:max-w-3xl'
                                "
                            >
                                {{ slide.title }}
                            </h2>
                            <div
                                class="sf-ornament mt-4 w-24 md:mt-5 md:w-32"
                                :class="desktopSrc(slide) || mobileSrc(slide) ? '!ml-0' : ''"
                                style="--sf-bg: var(--sf-secondary)"
                            />
                            <p
                                v-if="slide.subtitle"
                                class="mt-3 w-full max-w-full text-[13px] leading-relaxed tracking-wide text-[var(--sf-header-text)]/85 sm:text-sm md:mt-5 md:max-w-xl md:text-base"
                                :class="desktopSrc(slide) || mobileSrc(slide) ? '' : 'mx-auto'"
                            >
                                {{ slide.subtitle }}
                            </p>
                            <Link
                                v-if="slide.cta_label && slide.cta_url"
                                :href="slide.cta_url"
                                class="sf-btn-gold mt-6 inline-flex max-w-full px-6 py-3 text-[10px] font-semibold tracking-[0.18em] uppercase sm:px-7 sm:tracking-[0.22em] md:mt-8 md:px-9 md:py-3.5 md:text-[11px] md:tracking-[0.25em]"
                            >
                                {{ slide.cta_label }}
                            </Link>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <button
            v-if="slides.length > 1"
            type="button"
            class="absolute left-2 top-1/2 z-20 flex h-9 w-9 -translate-y-1/2 items-center justify-center border border-[var(--sf-primary)]/50 bg-[var(--sf-secondary)]/60 text-base text-[var(--sf-accent-light)] backdrop-blur transition hover:bg-[var(--sf-secondary)] md:left-3 md:h-10 md:w-10 md:text-lg"
            aria-label="Anterior"
            @click="prev(); startAutoplay()"
        >
            ‹
        </button>
        <button
            v-if="slides.length > 1"
            type="button"
            class="absolute right-2 top-1/2 z-20 flex h-9 w-9 -translate-y-1/2 items-center justify-center border border-[var(--sf-primary)]/50 bg-[var(--sf-secondary)]/60 text-base text-[var(--sf-accent-light)] backdrop-blur transition hover:bg-[var(--sf-secondary)] md:right-3 md:h-10 md:w-10 md:text-lg"
            aria-label="Próximo"
            @click="next(); startAutoplay()"
        >
            ›
        </button>

        <div
            v-if="slides.length > 1"
            class="absolute bottom-3 left-0 right-0 z-20 flex justify-center gap-2 md:bottom-4"
        >
            <button
                v-for="(s, i) in slides"
                :key="i"
                type="button"
                class="h-1.5 transition-all duration-300"
                :class="i === index ? 'w-8 bg-[var(--sf-accent-light)]' : 'w-3 bg-[var(--sf-header-text)]/40'"
                :aria-label="`Slide ${i + 1}`"
                @click="goTo(i)"
            />
        </div>
    </section>
</template>
