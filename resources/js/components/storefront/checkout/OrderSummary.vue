<script setup>
import { computed, ref } from 'vue';
import { ChevronDown } from 'lucide-vue-next';

const props = defineProps({
    lines: { type: Array, default: () => [] },
    subtotal: { type: Number, default: 0 },
    shippingCost: { type: Number, default: null },
    shippingLabel: { type: String, default: null },
    total: { type: Number, default: null },
    collapsibleOnMobile: { type: Boolean, default: true },
});

const open = ref(true);

const computedTotal = computed(() => {
    if (props.total != null) return Number(props.total);
    return Number(props.subtotal || 0) + Number(props.shippingCost || 0);
});

const shippingDisplay = computed(() => {
    if (props.shippingCost == null) return 'A calcular';
    return formatMoney(props.shippingCost);
});

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}
</script>

<template>
    <aside class="border border-[var(--sf-primary)]/25 border-t-2 border-t-[var(--sf-primary)] bg-white/80">
        <button
            v-if="collapsibleOnMobile"
            type="button"
            class="flex w-full items-center justify-between px-5 py-4 text-left lg:hidden"
            @click="open = !open"
        >
            <span class="text-sm font-semibold text-[var(--sf-secondary)]">Resumo do pedido</span>
            <span class="flex items-center gap-2 text-sm font-medium">
                {{ formatMoney(computedTotal) }}
                <ChevronDown class="h-4 w-4 transition" :class="open ? 'rotate-180' : ''" />
            </span>
        </button>

        <div :class="collapsibleOnMobile ? (open ? 'block' : 'hidden lg:block') : 'block'">
            <div class="hidden border-b border-[var(--sf-primary)]/10 px-5 py-4 lg:block">
                <h2 class="font-[family-name:var(--sf-font-heading)] text-2xl text-[var(--sf-secondary)]">
                    Seu pedido
                </h2>
            </div>

            <ul class="divide-y divide-[var(--sf-primary)]/10 px-5">
                <li
                    v-for="(line, idx) in lines"
                    :key="line.id || idx"
                    class="flex gap-3 py-4"
                >
                    <div class="relative h-16 w-16 shrink-0 overflow-hidden bg-[var(--sf-secondary)]">
                        <img
                            v-if="line.image_url"
                            :src="line.image_url"
                            :alt="line.name"
                            class="h-full w-full object-cover"
                        />
                        <span
                            class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-[var(--sf-secondary)] px-1 text-[10px] font-semibold text-[var(--sf-header-text)]"
                        >
                            {{ line.quantity }}
                        </span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium leading-snug text-[var(--sf-text)]">
                            {{ line.name }}
                            <span class="text-[var(--sf-text)]/50"> × {{ line.quantity }}</span>
                        </p>
                    </div>
                    <p class="shrink-0 text-sm font-semibold text-[var(--sf-text)]">
                        {{ formatMoney(line.amount ?? (line.unit_amount * line.quantity)) }}
                    </p>
                </li>
            </ul>

            <div class="space-y-2 border-t border-[var(--sf-primary)]/10 px-5 py-4 text-sm">
                <div class="flex justify-between">
                    <span class="text-[var(--sf-text)]/55">Subtotal</span>
                    <span>{{ formatMoney(subtotal) }}</span>
                </div>
                <div class="flex justify-between gap-3">
                    <span class="text-[var(--sf-text)]/55">
                        Frete
                        <span v-if="shippingLabel" class="block text-xs text-[var(--sf-text)]/40">{{ shippingLabel }}</span>
                    </span>
                    <span>{{ shippingDisplay }}</span>
                </div>
                <div class="flex justify-between border-t border-[var(--sf-primary)]/15 pt-3 text-base font-semibold text-[var(--sf-secondary)]">
                    <span>Total</span>
                    <span>{{ formatMoney(computedTotal) }}</span>
                </div>
            </div>

            <div v-if="$slots.default" class="border-t border-[var(--sf-primary)]/10 px-5 py-4">
                <slot />
            </div>
        </div>
    </aside>
</template>
