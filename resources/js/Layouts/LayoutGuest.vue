<script setup>
</script>

<template>
    <div class="min-h-screen bg-zinc-50 text-zinc-900 transition-colors">
        <header class="sticky top-0 z-10 border-b border-zinc-200 bg-white">
            <div class="mx-auto flex h-14 max-w-7xl items-center justify-between px-4">
                <span class="text-lg font-semibold text-zinc-900">
                    Art Corporal
                </span>
            </div>
        </header>
        <main class="mx-auto max-w-7xl px-4 py-8">
            <slot />
        </main>
    </div>
</template>
