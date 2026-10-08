<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

defineProps({
    page: {
        type: Object,
        default: () => ({}),
    },
    cmsPage: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <StorefrontLayout>
        <Head :title="(cmsPage || page)?.title || 'Página'" />

        <div class="mx-auto max-w-3xl px-4 py-12 md:px-8">
            <nav class="mb-6 text-xs text-zinc-500">
                <Link href="/" class="hover:text-[var(--sf-primary)]">Início</Link>
                <span class="mx-2">/</span>
                <span class="text-[var(--sf-text)]">{{ (cmsPage || page)?.title }}</span>
            </nav>

            <h1 class="mb-8 text-2xl font-semibold tracking-wide uppercase">
                {{ (cmsPage || page)?.title }}
            </h1>

            <div
                v-if="(cmsPage || page)?.body_html || (cmsPage || page)?.html"
                class="prose prose-sm max-w-none text-zinc-700 prose-headings:text-[var(--sf-text)] prose-a:text-[var(--sf-primary)]"
                v-html="(cmsPage || page)?.body_html || (cmsPage || page)?.html"
            />
            <div
                v-else-if="(cmsPage || page)?.body"
                class="whitespace-pre-line text-sm leading-relaxed text-zinc-700"
            >
                {{ (cmsPage || page).body }}
            </div>
            <p v-else class="text-sm text-zinc-500">
                Conteúdo em breve.
            </p>
        </div>
    </StorefrontLayout>
</template>
