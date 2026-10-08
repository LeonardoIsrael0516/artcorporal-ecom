<script setup>
import { computed } from 'vue';

const props = defineProps({
    section: {
        type: Object,
        default: () => ({}),
    },
    posts: {
        type: Array,
        default: null,
    },
});

const cfg = computed(() => ({
    ...(props.section?.props || {}),
    ...(props.section || {}),
}));

const items = computed(() => {
    if (Array.isArray(props.posts) && props.posts.length) return props.posts;
    const fromProps = cfg.value.items ?? cfg.value.posts ?? cfg.value.images ?? [];
    return Array.isArray(fromProps) ? fromProps : [];
});

const handle = computed(() => cfg.value.handle || cfg.value.instagram || '@loja');
const profileUrl = computed(
    () => cfg.value.profile_url || `https://instagram.com/${String(handle.value).replace('@', '')}`,
);

function postImage(post) {
    return post?.image || post?.image_url || post?.url_image || '';
}

function postHref(post) {
    return post?.link || post?.url || profileUrl.value;
}

function postAlt(post) {
    return post?.caption || post?.alt || 'Instagram';
}
</script>

<template>
    <section class="mx-auto w-full max-w-[1360px] px-4 py-10 md:px-8">
        <div class="mb-10 flex flex-col items-center text-center">
            <p class="sf-eyebrow">Instagram</p>
            <h2 class="mt-2 text-3xl text-[var(--sf-secondary)] md:text-4xl">
                {{ cfg.title || 'Instagram' }}
            </h2>
            <div class="sf-ornament mt-4 w-28" />
            <a
                :href="profileUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-4 inline-block text-sm tracking-wide text-[var(--sf-primary)] transition hover:text-[var(--sf-secondary)]"
            >
                {{ handle }}
            </a>
        </div>

        <div
            v-if="items.length"
            class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 md:gap-3"
        >
            <a
                v-for="(post, i) in items"
                :key="post.id || postImage(post) || i"
                :href="postHref(post)"
                target="_blank"
                rel="noopener noreferrer"
                class="group relative aspect-square overflow-hidden bg-[var(--sf-secondary)]"
            >
                <img
                    v-if="postImage(post)"
                    :src="postImage(post)"
                    :alt="postAlt(post)"
                    class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                    loading="lazy"
                />
                <div
                    class="absolute inset-0 bg-[var(--sf-secondary)]/0 transition group-hover:bg-[var(--sf-secondary)]/35"
                />
            </a>
        </div>

        <p v-else class="text-center text-sm text-[var(--sf-text)]/55">
            Nenhuma publicação configurada.
        </p>
    </section>
</template>
