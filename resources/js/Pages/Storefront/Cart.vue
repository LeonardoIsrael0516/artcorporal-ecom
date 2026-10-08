<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import CheckoutSteps from '@/components/storefront/checkout/CheckoutSteps.vue';
import OrderSummary from '@/components/storefront/checkout/OrderSummary.vue';

const props = defineProps({
    cart: {
        type: Object,
        default: () => ({ lines: [], subtotal: 0, shipping_quote: null, shipping_address: null }),
    },
});

const updating = ref(false);
const lines = computed(() => props.cart?.lines || []);
const subtotal = computed(() => Number(props.cart?.subtotal ?? 0));
const shippingQuote = computed(() => props.cart?.shipping_quote || null);
const shippingCost = computed(() =>
    shippingQuote.value ? Number(shippingQuote.value.price ?? 0) : null
);

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

async function updateQty(line, quantity) {
    const qty = Math.max(1, Number(quantity) || 1);
    updating.value = true;
    try {
        await axios.patch(`/carrinho/lines/${line.id}`, { quantity: qty });
        router.reload({ preserveScroll: true });
    } catch (_) {
        router.reload();
    } finally {
        updating.value = false;
    }
}

async function removeLine(line) {
    updating.value = true;
    try {
        await axios.delete(`/carrinho/lines/${line.id}`);
        router.reload({ preserveScroll: true });
    } catch (_) {
        router.reload();
    } finally {
        updating.value = false;
    }
}
</script>

<template>
    <StorefrontLayout>
        <Head title="Carrinho" />

        <div class="mx-auto max-w-[1100px] px-4 py-8 md:px-8 md:py-12">
            <CheckoutSteps current="carrinho" />

            <div v-if="!lines.length" class="py-16 text-center">
                <p class="text-sm text-[var(--sf-text)]/60">Seu carrinho está vazio.</p>
                <Link
                    href="/loja"
                    class="sf-btn mt-6 px-8 py-3 text-[11px] font-semibold tracking-[0.2em] uppercase"
                >
                    Continuar comprando
                </Link>
            </div>

            <div v-else class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start">
                <div class="space-y-3">
                    <h1 class="mb-4 font-[family-name:var(--sf-font-heading)] text-3xl text-[var(--sf-secondary)] md:text-4xl">
                        Carrinho
                    </h1>

                    <div
                        v-for="line in lines"
                        :key="line.id"
                        class="flex gap-4 border border-[var(--sf-primary)]/20 bg-white/70 p-4"
                    >
                        <Link :href="`/produto/${line.slug}`" class="h-24 w-24 shrink-0 bg-[var(--sf-secondary)]">
                            <img
                                v-if="line.image_url"
                                :src="line.image_url"
                                :alt="line.name"
                                class="h-full w-full object-cover"
                            />
                        </Link>
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <div class="flex items-start justify-between gap-2">
                                <Link
                                    :href="`/produto/${line.slug}`"
                                    class="text-sm font-medium hover:text-[var(--sf-primary)]"
                                >
                                    {{ line.name }}
                                </Link>
                                <button
                                    type="button"
                                    class="text-xs text-[var(--sf-text)]/40 hover:text-red-600"
                                    :disabled="updating"
                                    @click="removeLine(line)"
                                >
                                    Remover
                                </button>
                            </div>
                            <p class="text-sm font-semibold text-[var(--sf-secondary)]">
                                {{ formatMoney(line.unit_amount) }}
                            </p>
                            <div class="flex items-center gap-2">
                                <label class="text-xs text-[var(--sf-text)]/50">Qtd.</label>
                                <input
                                    type="number"
                                    min="1"
                                    :value="line.quantity"
                                    class="w-16 border border-[var(--sf-primary)]/25 bg-white px-2 py-1 text-sm outline-none focus:border-[var(--sf-primary)]"
                                    :disabled="updating"
                                    @change="updateQty(line, $event.target.value)"
                                />
                            </div>
                        </div>
                        <p class="hidden shrink-0 text-sm font-semibold sm:block">
                            {{ formatMoney(line.amount) }}
                        </p>
                    </div>

                    <Link href="/loja" class="inline-block pt-2 text-xs text-[var(--sf-text)]/50 hover:text-[var(--sf-primary)] hover:underline">
                        ← Continuar comprando
                    </Link>
                </div>

                <div class="lg:sticky lg:top-6">
                    <OrderSummary
                        :lines="lines"
                        :subtotal="subtotal"
                        :shipping-cost="shippingCost"
                        :shipping-label="shippingQuote?.name"
                    >
                        <Link
                            href="/checkout/entrega"
                            class="sf-btn-gold flex w-full items-center justify-center px-6 py-4 text-xs font-semibold tracking-[0.25em] uppercase"
                        >
                            Continuar
                        </Link>
                    </OrderSummary>
                </div>
            </div>
        </div>
    </StorefrontLayout>
</template>
