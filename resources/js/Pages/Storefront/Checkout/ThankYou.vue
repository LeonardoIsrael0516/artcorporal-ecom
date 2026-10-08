<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2, Package, Truck, MapPin, ExternalLink } from 'lucide-vue-next';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import ConversionPixels from '@/components/checkout/ConversionPixels.vue';
import { firePurchaseWhenReady } from '@/composables/useConversionPurchase';

defineOptions({ layout: null });

const conversionPixelsRef = ref(null);

const props = defineProps({
    conversion_pixels: { type: Object, default: () => ({}) },
    order_id: { type: Number, default: null },
    order_amount: { type: Number, default: 0 },
    order_currency: { type: String, default: 'BRL' },
    meta_purchase_event_id: { type: String, default: '' },
    purchase_contents: { type: Array, default: () => [] },
    order_status: { type: String, default: 'completed' },
    customer_email: { type: String, default: null },
    customer_name: { type: String, default: null },
    lines: { type: Array, default: () => [] },
    shipping_amount: { type: Number, default: 0 },
    subtotal: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
    fulfillment: { type: Object, default: () => ({}) },
    storeTheme: { type: Object, default: null },
    menuCategories: { type: Array, default: () => [] },
    cartCount: { type: Number, default: 0 },
    redirect_url: { type: String, default: '/conta' },
    redirect_label: { type: String, default: 'Acompanhar pedido' },
    home_url: { type: String, default: '/loja' },
});

const fulfillment = computed(() => props.fulfillment || {});
const events = computed(() => Array.isArray(fulfillment.value.events) ? fulfillment.value.events : []);

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

async function onConversionPixelsReady() {
    if (!props.order_id || !(Number(props.order_amount) > 0)) return;
    const api = conversionPixelsRef.value;
    const eid =
        (props.meta_purchase_event_id || '').trim() || `getfy_purchase_${props.order_id}`;
    const cur =
        typeof props.order_currency === 'string' && props.order_currency.trim()
            ? props.order_currency.trim().toUpperCase()
            : 'BRL';
    await firePurchaseWhenReady(api, {
        order_id: props.order_id,
        amount: props.order_amount,
        currency: cur,
        meta_event_id: eid,
        purchase_contents: props.purchase_contents,
    }, { pixels: props.conversion_pixels });
}
</script>

<template>
    <ConversionPixels ref="conversionPixelsRef" :pixels="conversion_pixels" @ready="onConversionPixelsReady" />
    <StorefrontLayout>
        <Head title="Pedido confirmado" />

        <div class="mx-auto max-w-[900px] px-4 py-10 md:px-8 md:py-14">
            <div class="mb-8 flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                <div class="flex h-14 w-14 items-center justify-center bg-[var(--sf-primary)]/15 text-[var(--sf-primary)]">
                    <CheckCircle2 class="h-8 w-8" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--sf-text)]/45">
                        Pedido #{{ order_id }}
                    </p>
                    <h1 class="font-[family-name:var(--sf-font-heading)] text-3xl text-[var(--sf-secondary)] md:text-4xl">
                        Obrigado pela compra
                    </h1>
                    <p class="mt-1 text-sm text-[var(--sf-text)]/65">
                        {{ order_status === 'completed' || order_status === 'paid'
                            ? 'Pagamento confirmado. Vamos preparar o envio.'
                            : 'Pedido registrado. Assim que o pagamento for confirmado, iniciamos a separação.' }}
                    </p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-12">
                <div class="space-y-5 lg:col-span-7">
                    <section class="border border-[var(--sf-primary)]/20 bg-white/75 p-5">
                        <div class="mb-4 flex items-center gap-2 text-[var(--sf-secondary)]">
                            <Truck class="h-5 w-5" />
                            <h2 class="font-[family-name:var(--sf-font-heading)] text-xl">Entrega</h2>
                        </div>

                        <div class="space-y-3 text-sm">
                            <div class="flex items-start justify-between gap-3">
                                <span class="text-[var(--sf-text)]/50">Status</span>
                                <span class="font-medium text-[var(--sf-secondary)]">
                                    {{ fulfillment.status_label || 'Preparando envio' }}
                                </span>
                            </div>
                            <div v-if="fulfillment.service" class="flex items-start justify-between gap-3">
                                <span class="text-[var(--sf-text)]/50">Frete</span>
                                <span class="text-right font-medium">
                                    {{ fulfillment.service }}
                                    <span v-if="fulfillment.days" class="block text-xs font-normal text-[var(--sf-text)]/55">
                                        Prazo estimado: até {{ fulfillment.days }} dia(s) úteis
                                    </span>
                                </span>
                            </div>
                            <div v-if="fulfillment.address_text" class="border-t border-[var(--sf-primary)]/10 pt-3">
                                <p class="mb-1 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-[var(--sf-text)]/45">
                                    <MapPin class="h-3.5 w-3.5" />
                                    Endereço
                                </p>
                                <p class="text-[var(--sf-text)]/80">{{ fulfillment.address_text }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="border border-[var(--sf-primary)]/20 bg-white/75 p-5">
                        <div class="mb-4 flex items-center gap-2 text-[var(--sf-secondary)]">
                            <Package class="h-5 w-5" />
                            <h2 class="font-[family-name:var(--sf-font-heading)] text-xl">Rastreio</h2>
                        </div>

                        <div v-if="fulfillment.tracking" class="space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-[var(--sf-text)]/45">Código</p>
                                    <p class="font-mono text-sm font-medium">{{ fulfillment.tracking }}</p>
                                </div>
                                <a
                                    v-if="fulfillment.tracking_url"
                                    :href="fulfillment.tracking_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1.5 text-sm text-[var(--sf-primary)] hover:underline"
                                >
                                    Rastrear envio
                                    <ExternalLink class="h-3.5 w-3.5" />
                                </a>
                            </div>

                            <ul v-if="events.length" class="space-y-3 border-t border-[var(--sf-primary)]/10 pt-4">
                                <li
                                    v-for="(ev, idx) in events.slice(0, 6)"
                                    :key="idx"
                                    class="border-l-2 border-[var(--sf-primary)]/30 pl-3 text-sm"
                                >
                                    <p class="font-medium text-[var(--sf-text)]">{{ ev.description || 'Atualização' }}</p>
                                    <p class="text-xs text-[var(--sf-text)]/50">
                                        <span v-if="ev.at">{{ ev.at }}</span>
                                        <span v-if="ev.at && ev.location"> · </span>
                                        <span v-if="ev.location">{{ ev.location }}</span>
                                    </p>
                                </li>
                            </ul>
                        </div>

                        <p v-else class="text-sm text-[var(--sf-text)]/65">
                            Assim que o pedido for despachado, o código de rastreio aparece aqui e na sua conta.
                        </p>
                    </section>

                    <div class="flex flex-wrap gap-3">
                        <Link
                            :href="redirect_url"
                            class="sf-btn-gold px-6 py-3.5 text-xs font-semibold uppercase tracking-[0.22em]"
                        >
                            {{ redirect_label }}
                        </Link>
                        <Link
                            :href="home_url"
                            class="border border-[var(--sf-primary)]/30 bg-white/70 px-6 py-3.5 text-xs font-semibold uppercase tracking-[0.22em] text-[var(--sf-secondary)]"
                        >
                            Continuar comprando
                        </Link>
                    </div>
                </div>

                <aside class="lg:col-span-5">
                    <div class="border border-[var(--sf-primary)]/20 bg-white/75 p-5 lg:sticky lg:top-6">
                        <h2 class="mb-4 font-[family-name:var(--sf-font-heading)] text-xl text-[var(--sf-secondary)]">
                            Resumo
                        </h2>
                        <ul class="space-y-3">
                            <li
                                v-for="(line, idx) in lines"
                                :key="idx"
                                class="flex gap-3 border-b border-[var(--sf-primary)]/10 pb-3 text-sm"
                            >
                                <div class="h-14 w-14 shrink-0 overflow-hidden bg-[var(--sf-primary)]/10">
                                    <img
                                        v-if="line.image_url"
                                        :src="line.image_url"
                                        :alt="line.name"
                                        class="h-full w-full object-cover"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium leading-snug">{{ line.name }}</p>
                                    <p class="mt-1 text-[var(--sf-text)]/55">
                                        {{ formatMoney(line.amount) }}
                                    </p>
                                </div>
                            </li>
                        </ul>
                        <div class="mt-4 space-y-1.5 text-sm">
                            <div class="flex justify-between text-[var(--sf-text)]/65">
                                <span>Subtotal</span>
                                <span>{{ formatMoney(subtotal) }}</span>
                            </div>
                            <div class="flex justify-between text-[var(--sf-text)]/65">
                                <span>Frete</span>
                                <span>{{ shipping_amount > 0 ? formatMoney(shipping_amount) : 'Grátis' }}</span>
                            </div>
                            <div class="flex justify-between border-t border-[var(--sf-primary)]/15 pt-2 text-base font-semibold text-[var(--sf-secondary)]">
                                <span>Total</span>
                                <span>{{ formatMoney(total || order_amount) }}</span>
                            </div>
                        </div>
                        <p v-if="customer_email" class="mt-4 text-xs text-[var(--sf-text)]/50">
                            Confirmação enviada para {{ customer_email }}
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </StorefrontLayout>
</template>
