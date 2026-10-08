<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Check, ShoppingBag, Truck, CreditCard } from 'lucide-vue-next';

const props = defineProps({
    current: {
        type: String,
        required: true,
        validator: (v) => ['carrinho', 'entrega', 'pagamento'].includes(v),
    },
});

const steps = [
    { id: 'carrinho', label: 'Carrinho', href: '/carrinho', icon: ShoppingBag },
    { id: 'entrega', label: 'Entrega', href: '/checkout/entrega', icon: Truck },
    { id: 'pagamento', label: 'Pagamento', href: null, icon: CreditCard },
];

const currentIndex = computed(() => steps.findIndex((s) => s.id === props.current));

function statusOf(index) {
    if (index < currentIndex.value) return 'done';
    if (index === currentIndex.value) return 'current';
    return 'upcoming';
}
</script>

<template>
    <nav class="mb-8 border-b border-[var(--sf-primary)]/15 pb-6" aria-label="Progresso do checkout">
        <ol class="mx-auto flex max-w-2xl items-center justify-between gap-2">
            <li
                v-for="(step, index) in steps"
                :key="step.id"
                class="flex flex-1 items-center"
                :class="index < steps.length - 1 ? 'after:mx-2 after:hidden after:h-px after:flex-1 after:bg-[var(--sf-primary)]/25 sm:after:block' : ''"
            >
                <component
                    :is="statusOf(index) === 'done' && step.href ? Link : 'div'"
                    :href="statusOf(index) === 'done' ? step.href : undefined"
                    class="flex flex-col items-center gap-1.5 text-center sm:flex-row sm:gap-2"
                >
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-full border text-xs font-semibold transition"
                        :class="{
                            'border-[var(--sf-primary)] bg-[var(--sf-primary)] text-white': statusOf(index) === 'done',
                            'border-[var(--sf-secondary)] bg-[var(--sf-secondary)] text-[var(--sf-header-text)]': statusOf(index) === 'current',
                            'border-[var(--sf-primary)]/30 bg-white/60 text-[var(--sf-text)]/40': statusOf(index) === 'upcoming',
                        }"
                    >
                        <Check v-if="statusOf(index) === 'done'" class="h-4 w-4" />
                        <component v-else :is="step.icon" class="h-4 w-4" />
                    </span>
                    <span
                        class="text-[10px] font-semibold uppercase tracking-[0.18em] sm:text-[11px]"
                        :class="{
                            'text-[var(--sf-primary)]': statusOf(index) === 'done',
                            'text-[var(--sf-secondary)]': statusOf(index) === 'current',
                            'text-[var(--sf-text)]/40': statusOf(index) === 'upcoming',
                        }"
                    >
                        {{ step.label }}
                    </span>
                </component>
            </li>
        </ol>
    </nav>
</template>
