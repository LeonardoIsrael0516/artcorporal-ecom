<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import QrcodeVue from 'qrcode.vue';
import { Clock, Copy, Check, Building2, QrCode, CircleDollarSign } from 'lucide-vue-next';
import confetti from 'canvas-confetti';
import ConversionPixels from '@/components/checkout/ConversionPixels.vue';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { firePurchaseWhenReady, redirectAfterPurchaseReady } from '@/composables/useConversionPurchase';
import {
    shouldFirePurchaseOnPixGeneration,
} from '@/lib/pixelPlatforms';

defineOptions({ layout: null });

const conversionPixelsRef = ref(null);
const pixelsReady = ref(false);
const pixGenerationPurchaseFired = ref(false);
/** Payload de Purchase pendente após aprovação (polling). */
const pendingPurchasePayload = ref(null);
/** URL para redirecionar após disparar Purchase. */
const redirectAfterFiring = ref(null);

const props = defineProps({
    token: { type: String, required: true },
    order_id: { type: Number, required: true },
    qrcode: { type: String, default: null },
    copy_paste: { type: String, default: '' },
    amount_formatted: { type: String, default: 'R$ 0,00' },
    product_name: { type: String, default: '' },
    checkout_slug: { type: String, default: '' },
    redirect_after_purchase: { type: String, default: null },
    customer_name: { type: String, default: null },
    customer_email: { type: String, default: null },
    customer_phone: { type: String, default: null },
    created_at: { type: Number, required: true },
    expiry_seconds: { type: Number, default: 900 },
    amount: { type: Number, default: 0 },
    conversion_pixels: { type: Object, default: () => ({}) },
    meta_purchase_event_id: { type: String, default: '' },
    purchase_contents: { type: Array, default: () => [] },
    storefront_checkout: { type: Boolean, default: false },
    storeTheme: { type: Object, default: null },
    menuCategories: { type: Array, default: () => [] },
    cartCount: { type: Number, default: 0 },
    order_summary: { type: Object, default: null },
});

const qrImageFailed = ref(false);

const qrcodeSrc = computed(() => {
    const q = (props.qrcode || '').trim();
    if (!q || qrImageFailed.value) return '';
    if (q.startsWith('data:') || q.startsWith('http://') || q.startsWith('https://')) {
        return q;
    }
    return `data:image/png;base64,${q}`;
});

const showQrFromCopyPaste = computed(
    () => (!qrcodeSrc.value || qrImageFailed.value) && (props.copy_paste || '').trim().length > 0
);

function onQrImageError() {
    qrImageFailed.value = true;
}

const copyButtonText = ref('Copiar');
const status = ref('pending');
const confirmFeedback = ref('');
const confirmChecking = ref(false);
let pollInterval = null;
let timerInterval = null;

const endTime = computed(() => (props.created_at + props.expiry_seconds) * 1000);
const timeLeft = ref(props.expiry_seconds);

function updateTimer() {
    const now = Date.now();
    const left = Math.max(0, Math.floor((endTime.value - now) / 1000));
    timeLeft.value = left;
    if (left <= 0 && pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
}

const timerDisplay = computed(() => {
    const left = timeLeft.value;
    const m = Math.floor(left / 60);
    const s = left % 60;
    return `${m < 10 ? '0' : ''}${m}:${s < 10 ? '0' : ''}${s}`;
});

const pixelTrackingContext = computed(() => ({
    checkout_slug: props.checkout_slug ?? '',
    product_name: props.product_name ?? '',
    page_path: typeof window !== 'undefined' ? window.location.pathname : '',
}));

async function firePixGenerationEvents() {
    if (pixGenerationPurchaseFired.value) return;
    const api = conversionPixelsRef.value;
    if (!api) return;

    api.firePaymentGenerated?.('pix', props.amount, 'BRL', String(props.order_id), pixelTrackingContext.value);

    if (shouldFirePurchaseOnPixGeneration(props.conversion_pixels)) {
        const payload = buildPurchasePayloadFromStatus({});
        await firePurchaseWhenReady(api, payload, {
            maxWaitMs: 3000,
            triggerType: 'pix',
            pixels: props.conversion_pixels,
        });
    }
    pixGenerationPurchaseFired.value = true;
}

const timerExpired = computed(() => timeLeft.value <= 0);
const hasCustomerInfo = computed(
    () => (props.customer_name || '') !== '' || (props.customer_email || '') !== '' || (props.customer_phone || '') !== ''
);

function buildPurchasePayloadFromStatus(data) {
    const oid = data?.order_id ?? props.order_id;
    return {
        order_id: oid,
        amount: typeof data?.amount === 'number' ? data.amount : props.amount,
        currency:
            typeof data?.currency === 'string' && data.currency
                ? data.currency
                : 'BRL',
        meta_event_id:
            typeof data?.meta_purchase_event_id === 'string' && data.meta_purchase_event_id
                ? data.meta_purchase_event_id
                : (props.meta_purchase_event_id || '').trim() || `getfy_purchase_${oid}`,
        purchase_contents:
            Array.isArray(data?.purchase_contents) && data.purchase_contents.length > 0
                ? data.purchase_contents
                : props.purchase_contents,
    };
}

function navigateToUrl(url) {
    if (url.startsWith('http') || url.startsWith('//')) {
        window.location.href = url;
    } else {
        router.visit(url);
    }
}

async function flushPurchaseAndRedirect() {
    const url = redirectAfterFiring.value;
    if (!url) return;
    const payload = pendingPurchasePayload.value;
    redirectAfterFiring.value = null;
    pendingPurchasePayload.value = null;
    const api = conversionPixelsRef.value;
    if (payload && api) {
        await redirectAfterPurchaseReady(api, payload, () => navigateToUrl(url), {
            maxWaitMs: 3000,
            redirectDelayMs: 0,
            triggerType: 'approved',
            pixels: props.conversion_pixels,
        });
        return;
    }
    navigateToUrl(url);
}

async function checkOrderStatus() {
    try {
        const { data } = await axios.get('/checkout/order-status', { params: { token: props.token } });
        if (data.status === 'completed') {
            status.value = 'completed';
            confirmFeedback.value = 'Pagamento aprovado! Redirecionando...';
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
            pendingPurchasePayload.value = buildPurchasePayloadFromStatus(data);
            const url = data.redirect_url || props.redirect_after_purchase || '/meus-produtos';
            redirectAfterFiring.value = url;
            flushPurchaseAndRedirect();
        }
        return data;
    } catch {
        return { status: 'pending' };
    }
}

function onConversionPixelsReady() {
    pixelsReady.value = true;
    const api = conversionPixelsRef.value;
    if (api?.firePageView) {
        api.firePageView(props.amount, 'BRL');
    }
    firePixGenerationEvents();
    if (redirectAfterFiring.value) {
        flushPurchaseAndRedirect();
    }
}

function onConfirmPayment() {
    confirmChecking.value = true;
    confirmFeedback.value = 'Aguardando...';
    checkOrderStatus().then((data) => {
        confirmChecking.value = false;
        if (data.status !== 'completed') {
            confirmFeedback.value = 'Ainda aguardando. O pagamento é confirmado automaticamente.';
        }
    });
}

async function copyPixCode() {
    const code = props.copy_paste || '';
    if (!code) return;
    try {
        await navigator.clipboard.writeText(code);
        copyButtonText.value = 'Copiado!';
        setTimeout(() => (copyButtonText.value = 'Copiar'), 2000);
    } catch {
        copyButtonText.value = 'Copiar';
    }
}

function backToCheckout() {
    if (props.storefront_checkout) {
        router.visit('/checkout/entrega');
        return;
    }
    if (props.checkout_slug) {
        router.visit(`/c/${props.checkout_slug}`);
    } else {
        router.visit('/');
    }
}

const summaryLines = computed(() => {
    if (props.order_summary?.lines?.length) return props.order_summary.lines;
    if (props.product_name) {
        return [{ name: props.product_name, quantity: 1, amount: props.amount, image_url: null }];
    }
    return [];
});

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

onMounted(() => {
    confetti({
        particleCount: 100,
        spread: 70,
        origin: { y: 0.55 },
        colors: ['#B8854A', '#E6BC82', '#10261D', '#F3E7D3'],
    });
    updateTimer();
    timerInterval = setInterval(updateTimer, 1000);
    pollInterval = setInterval(() => {
        if (status.value === 'completed') return;
        checkOrderStatus();
    }, 5000);
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <ConversionPixels
        ref="conversionPixelsRef"
        :pixels="props.conversion_pixels"
        :tracking-context="pixelTrackingContext"
        @ready="onConversionPixelsReady"
    />
    <Head>
        <title>Pagamento PIX</title>
    </Head>

    <!-- Storefront (Art Corporal) -->
    <StorefrontLayout v-if="storefront_checkout">
        <div class="mx-auto max-w-[980px] px-4 py-8 md:px-8 md:py-12">
            <div class="mb-8 text-center">
                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-[var(--sf-primary)]">
                    Quase lá
                </p>
                <h1 class="mt-2 font-[family-name:var(--sf-font-heading)] text-3xl text-[var(--sf-secondary)] md:text-4xl">
                    Pague com Pix
                </h1>
                <div class="sf-ornament mx-auto mt-4 w-20" />
                <p class="mt-4 text-sm text-[var(--sf-text)]/60">
                    Escaneie o QR Code ou copie o código no app do seu banco.
                </p>
            </div>

            <div
                v-if="status === 'completed'"
                class="mb-6 border border-emerald-300/60 bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-800"
            >
                Pagamento aprovado! Redirecionando…
            </div>

            <div class="grid gap-8 lg:grid-cols-12 lg:items-start">
                <div class="space-y-5 lg:col-span-7">
                    <section class="border border-[var(--sf-primary)]/25 border-t-2 border-t-[var(--sf-primary)] bg-white/80 p-6 sm:p-8">
                        <p class="text-center text-sm text-[var(--sf-text)]/55">Valor</p>
                        <p class="mt-1 text-center font-[family-name:var(--sf-font-heading)] text-3xl text-[var(--sf-secondary)] sm:text-4xl">
                            {{ amount_formatted }}
                        </p>

                        <div v-if="qrcodeSrc || showQrFromCopyPaste" class="mt-6 flex justify-center">
                            <div class="border border-[var(--sf-primary)]/30 bg-white p-3 shadow-sm">
                                <img
                                    v-if="qrcodeSrc && !qrImageFailed"
                                    :src="qrcodeSrc"
                                    alt="QR Code PIX"
                                    class="h-48 w-48 object-contain sm:h-52 sm:w-52"
                                    style="image-rendering: pixelated"
                                    @error="onQrImageError"
                                />
                                <QrcodeVue
                                    v-else-if="showQrFromCopyPaste"
                                    :value="copy_paste"
                                    :size="200"
                                    level="M"
                                    class="h-48 w-48 sm:h-52 sm:w-52"
                                />
                            </div>
                        </div>

                        <div class="mt-6">
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-[var(--sf-text)]/50">
                                Pix copia e cola
                            </label>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <input
                                    type="text"
                                    :value="copy_paste"
                                    readonly
                                    class="w-full border border-[var(--sf-primary)]/25 bg-[var(--sf-bg)] px-3 py-3 text-sm text-[var(--sf-text)] outline-none"
                                />
                                <button
                                    type="button"
                                    class="sf-btn-gold inline-flex shrink-0 items-center justify-center gap-2 px-5 py-3 text-[11px] font-semibold tracking-[0.18em] uppercase"
                                    @click="copyPixCode"
                                >
                                    <Copy class="h-4 w-4" />
                                    {{ copyButtonText }}
                                </button>
                            </div>
                        </div>

                        <div
                            class="mt-5 flex items-center justify-between border border-dashed border-[var(--sf-primary)]/30 bg-[var(--sf-bg)]/80 px-4 py-3"
                        >
                            <span class="inline-flex items-center gap-2 text-sm text-[var(--sf-text)]/70">
                                <Clock class="h-4 w-4" />
                                Código expira em
                            </span>
                            <span
                                class="font-mono text-lg font-bold tabular-nums"
                                :class="timerExpired ? 'text-red-600' : 'text-[var(--sf-secondary)]'"
                            >
                                {{ timerDisplay }}
                            </span>
                        </div>

                        <button
                            type="button"
                            class="mt-5 flex w-full items-center justify-center gap-2 border border-[var(--sf-secondary)] bg-[var(--sf-secondary)] py-3.5 text-xs font-semibold tracking-[0.2em] uppercase text-[var(--sf-header-text)] transition hover:opacity-90 disabled:opacity-60"
                            :disabled="confirmChecking || status === 'completed'"
                            @click="onConfirmPayment"
                        >
                            <Check class="h-4 w-4" />
                            {{ confirmChecking ? 'Verificando...' : 'Já paguei' }}
                        </button>
                        <p
                            v-if="confirmFeedback"
                            class="mt-2 text-center text-xs"
                            :class="status === 'completed' ? 'text-emerald-700' : 'text-[var(--sf-text)]/50'"
                        >
                            {{ confirmFeedback }}
                        </p>
                    </section>

                    <section class="border border-[var(--sf-primary)]/20 bg-white/70 p-5 sm:p-6">
                        <h2 class="font-[family-name:var(--sf-font-heading)] text-xl text-[var(--sf-secondary)]">
                            Como pagar
                        </h2>
                        <ol class="mt-4 space-y-4">
                            <li class="flex gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center border border-[var(--sf-primary)]/30 text-xs font-semibold text-[var(--sf-primary)]">1</span>
                                <div>
                                    <p class="text-sm font-medium text-[var(--sf-text)]">Abra o app do banco</p>
                                    <p class="text-xs text-[var(--sf-text)]/55">Acesse a área Pix.</p>
                                </div>
                            </li>
                            <li class="flex gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center border border-[var(--sf-primary)]/30 text-xs font-semibold text-[var(--sf-primary)]">2</span>
                                <div>
                                    <p class="text-sm font-medium text-[var(--sf-text)]">Leia o QR ou cole o código</p>
                                    <p class="text-xs text-[var(--sf-text)]/55">Use “Pix Copia e Cola” ou a câmera.</p>
                                </div>
                            </li>
                            <li class="flex gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center border border-[var(--sf-primary)]/30 text-xs font-semibold text-[var(--sf-primary)]">3</span>
                                <div>
                                    <p class="text-sm font-medium text-[var(--sf-text)]">Confirme o pagamento</p>
                                    <p class="text-xs text-[var(--sf-text)]/55">A aprovação é automática nesta página.</p>
                                </div>
                            </li>
                        </ol>
                    </section>
                </div>

                <aside class="space-y-4 lg:col-span-5 lg:sticky lg:top-6">
                    <div class="border border-[var(--sf-primary)]/25 border-t-2 border-t-[var(--sf-primary)] bg-white/80">
                        <div class="border-b border-[var(--sf-primary)]/10 px-5 py-4">
                            <h2 class="font-[family-name:var(--sf-font-heading)] text-2xl text-[var(--sf-secondary)]">
                                Seu pedido
                            </h2>
                            <span
                                class="mt-2 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                :class="status === 'completed'
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : 'bg-[var(--sf-primary)]/15 text-[var(--sf-secondary)]'"
                            >
                                <Check v-if="status === 'completed'" class="h-3 w-3" />
                                <Clock v-else class="h-3 w-3" />
                                {{ status === 'completed' ? 'Pago' : 'Aguardando Pix' }}
                            </span>
                        </div>
                        <ul class="divide-y divide-[var(--sf-primary)]/10 px-5">
                            <li
                                v-for="(line, idx) in summaryLines"
                                :key="idx"
                                class="flex gap-3 py-4"
                            >
                                <div class="h-14 w-14 shrink-0 bg-[var(--sf-secondary)]">
                                    <img
                                        v-if="line.image_url"
                                        :src="line.image_url"
                                        :alt="line.name"
                                        class="h-full w-full object-cover"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium leading-snug">
                                        {{ line.name }}
                                        <span class="text-[var(--sf-text)]/45"> × {{ line.quantity || 1 }}</span>
                                    </p>
                                </div>
                                <p class="shrink-0 text-sm font-semibold">
                                    {{ formatMoney(line.amount) }}
                                </p>
                            </li>
                        </ul>
                        <div class="space-y-2 border-t border-[var(--sf-primary)]/10 px-5 py-4 text-sm">
                            <div v-if="order_summary?.shipping_cost != null" class="flex justify-between text-[var(--sf-text)]/60">
                                <span>Frete{{ order_summary.shipping_label ? ` · ${order_summary.shipping_label}` : '' }}</span>
                                <span>{{ formatMoney(order_summary.shipping_cost) }}</span>
                            </div>
                            <div class="flex justify-between text-base font-semibold text-[var(--sf-secondary)]">
                                <span>Total</span>
                                <span>{{ amount_formatted }}</span>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="hasCustomerInfo"
                        class="border border-[var(--sf-primary)]/20 bg-white/70 px-5 py-4 text-sm"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-[var(--sf-text)]/45">Cliente</p>
                        <p v-if="customer_name" class="mt-2 font-medium">{{ customer_name }}</p>
                        <p v-if="customer_email" class="text-[var(--sf-text)]/65">{{ customer_email }}</p>
                        <p v-if="customer_phone" class="text-[var(--sf-text)]/65">{{ customer_phone }}</p>
                    </div>

                    <button
                        type="button"
                        class="w-full text-center text-xs text-[var(--sf-text)]/50 underline-offset-2 hover:text-[var(--sf-primary)] hover:underline"
                        @click="backToCheckout"
                    >
                        Voltar à entrega
                    </button>
                </aside>
            </div>
        </div>
    </StorefrontLayout>

    <!-- Checkout legado (produto / API) -->
    <div v-else class="min-h-screen bg-gray-100 px-4 py-6 sm:py-8 pb-12">
        <div class="mx-auto w-full max-w-md">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg">
                <div v-if="qrcodeSrc || showQrFromCopyPaste" class="flex justify-center pt-6 pb-2">
                    <div class="w-40 h-40 sm:w-44 sm:h-44 rounded-2xl border-2 border-dashed border-gray-300 bg-white p-2.5 shadow-sm">
                        <img
                            v-if="qrcodeSrc && !qrImageFailed"
                            :src="qrcodeSrc"
                            alt="QR Code PIX"
                            class="h-full w-full object-contain"
                            style="image-rendering: pixelated"
                            @error="onQrImageError"
                        />
                        <QrcodeVue
                            v-else-if="showQrFromCopyPaste"
                            :value="copy_paste"
                            :size="160"
                            level="M"
                            class="h-full w-full"
                        />
                    </div>
                </div>
                <div class="px-5 sm:px-6 pb-6">
                    <h1 class="mb-1 text-center text-lg font-bold text-gray-900 sm:text-xl">Pague {{ amount_formatted }} via Pix</h1>
                    <p class="mb-5 text-center text-xs text-gray-500 sm:text-sm">Copie o código ou use a câmera para ler o QR Code e realize o pagamento no app do seu banco.</p>

                    <p class="mb-2 text-xs text-gray-500">Pix Copia e Cola</p>
                    <input
                        type="text"
                        :value="copy_paste"
                        readonly
                        class="mb-3 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-base text-gray-800 focus:outline-none"
                    />
                    <button
                        type="button"
                        class="btn-copy mb-4 flex w-full items-center justify-center gap-1.5 rounded-xl bg-gray-900 px-4 py-3 text-sm font-semibold text-white transition-opacity hover:opacity-90"
                        @click="copyPixCode"
                    >
                        <Copy class="h-4 w-4" />
                        {{ copyButtonText }}
                    </button>

                    <div class="mb-4 space-y-3">
                        <button
                            type="button"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-gray-200 bg-white py-3 font-semibold text-gray-900 transition-colors hover:bg-gray-50 disabled:opacity-70"
                            :disabled="confirmChecking || status === 'completed'"
                            @click="onConfirmPayment"
                        >
                            <Check class="h-4 w-4 text-green-600" />
                            {{ confirmChecking ? 'Verificando...' : 'Confirmar pagamento' }}
                        </button>
                        <p v-if="confirmFeedback" class="text-center text-xs text-gray-500" :class="status === 'completed' ? 'text-green-600' : ''">
                            {{ confirmFeedback }}
                        </p>
                    </div>

                    <div class="timer-card mb-4 rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 p-4">
                        <div class="flex items-center justify-center gap-2">
                            <Clock class="h-4 w-4 shrink-0 text-gray-600" />
                            <span class="text-sm font-medium text-gray-700">Código expira em</span>
                            <span
                                class="ml-auto font-mono text-lg font-bold tabular-nums text-gray-900"
                                :class="timerExpired ? 'text-red-600' : ''"
                            >
                                {{ timerDisplay }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg">
                <div class="space-y-4 p-5 sm:p-6">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-gray-100">
                            <Building2 class="h-5 w-5 text-gray-700" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900">Acesse seu banco</h3>
                            <p class="text-xs text-gray-600">Abra o app do seu banco, é rapidinho.</p>
                        </div>
                    </div>
                    <div class="border-t border-dashed border-gray-200"></div>
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-gray-100">
                            <QrCode class="h-5 w-5 text-gray-700" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900">Escolha a opção Pix</h3>
                            <p class="text-xs text-gray-600">Selecione "Pix Copia e Cola" ou "Ler QR code".</p>
                        </div>
                    </div>
                    <div class="border-t border-dashed border-gray-200"></div>
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-gray-100">
                            <CircleDollarSign class="h-5 w-5 text-gray-700" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900">Conclua o pagamento</h3>
                            <p class="text-xs text-gray-600">Cole o código ou leia o QR code, confirme os dados e pronto!</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg">
                <div class="p-5 sm:p-6">
                    <h3 class="mb-4 text-sm font-bold text-gray-900">Resumo da compra</h3>
                    <div class="mb-3 space-y-2">
                        <div v-if="product_name" class="flex items-start justify-between gap-2 text-xs">
                            <span class="text-gray-600 shrink-0">Produto</span>
                            <span class="font-medium text-gray-900 text-right">{{ product_name }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-700">Pagamento Pix</span>
                            <span class="font-medium text-gray-900">{{ amount_formatted }}</span>
                        </div>
                    </div>
                    <div class="mt-3 border-t border-dashed border-gray-200 pt-3">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-bold text-gray-900">Total</span>
                            <span class="font-bold text-gray-900">{{ amount_formatted }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg">
                <div class="p-5 sm:p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-gray-100">
                                <QrCode class="h-5 w-5 text-gray-700" />
                            </div>
                            <span class="text-sm font-medium text-gray-900">Pix</span>
                        </div>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold"
                            :class="status === 'completed'
                                ? 'border-green-200 bg-green-100 text-green-800'
                                : 'border-amber-200 bg-amber-100 text-amber-800'"
                        >
                            <Clock v-if="status === 'pending'" class="h-3.5 w-3.5" />
                            <Check v-else class="h-3.5 w-3.5" />
                            {{ status === 'completed' ? 'Aprovado' : 'Pendente' }}
                        </span>
                    </div>
                    <div v-if="hasCustomerInfo" class="mt-5 border-t border-dashed border-gray-300 pt-5">
                        <h3 class="mb-4 text-sm font-bold text-gray-900">Informações</h3>
                        <div class="space-y-3 text-sm">
                            <div v-if="customer_name" class="flex items-center justify-between">
                                <span class="text-gray-600">Cliente</span>
                                <span class="ml-2 break-all text-right font-medium text-gray-900">{{ customer_name }}</span>
                            </div>
                            <div v-if="customer_email" class="flex items-center justify-between">
                                <span class="text-gray-600">E-mail</span>
                                <span class="ml-2 break-all text-right font-medium text-gray-900">{{ customer_email }}</span>
                            </div>
                            <div v-if="customer_phone" class="flex items-center justify-between">
                                <span class="text-gray-600">Telefone</span>
                                <span class="font-medium text-gray-900">{{ customer_phone }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 text-center">
                <button
                    type="button"
                    class="text-sm font-medium text-gray-600 underline underline-offset-2 hover:text-gray-900"
                    @click="backToCheckout"
                >
                    Voltar ao checkout
                </button>
            </div>
        </div>
    </div>
</template>
