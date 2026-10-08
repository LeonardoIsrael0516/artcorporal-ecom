<script setup>
import { onMounted } from 'vue';

defineProps({
    compact: { type: Boolean, default: false },
});

onMounted(() => {
    document.documentElement.classList.remove('dark');
    try {
        localStorage.setItem('theme', 'light');
    } catch (_) {}
});
</script>

<template>
    <!-- Tema escuro desativado: painel sempre claro -->
</template>
