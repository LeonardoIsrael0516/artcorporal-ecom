<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    section: {
        type: Object,
        default: () => ({}),
    },
});

const cfg = computed(() => ({
    ...(props.section?.props || {}),
    ...(props.section || {}),
}));

const form = useForm({
    email: '',
});

function submit() {
    form.post('/newsletter', {
        preserveScroll: true,
        onSuccess: () => form.reset('email'),
    });
}
</script>

<template>
    <section class="border-y border-[var(--sf-primary)]/20 bg-[var(--sf-primary)]/[0.07]">
        <div class="mx-auto flex max-w-3xl flex-col items-center gap-4 px-4 py-16 text-center md:px-8">
            <p class="sf-eyebrow">Newsletter</p>
            <h2 class="text-3xl text-[var(--sf-secondary)] md:text-4xl">
                {{ cfg.title || 'Newsletter' }}
            </h2>
            <div class="sf-ornament w-28" />
            <p class="text-sm text-[var(--sf-text)]/70">
                {{ cfg.text || cfg.subtitle || 'Receba novidades, lançamentos e promoções exclusivas.' }}
            </p>

            <form class="mt-3 flex w-full max-w-lg flex-col gap-2 sm:flex-row" @submit.prevent="submit">
                <input
                    v-model="form.email"
                    type="email"
                    required
                    placeholder="Seu melhor e-mail"
                    class="flex-1 border border-[var(--sf-primary)]/40 bg-white px-4 py-3 text-sm outline-none transition focus:border-[var(--sf-secondary)]"
                />
                <button
                    type="submit"
                    class="sf-btn px-8 py-3 text-[11px] font-semibold tracking-[0.2em] uppercase disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Enviando...' : (cfg.cta_label || 'Assinar') }}
                </button>
            </form>

            <p v-if="form.errors.email" class="text-xs text-red-600">
                {{ form.errors.email }}
            </p>
        </div>
    </section>
</template>
