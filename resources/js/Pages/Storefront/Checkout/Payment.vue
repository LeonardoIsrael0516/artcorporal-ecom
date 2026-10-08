<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import QrcodeVue from 'qrcode.vue';
import {
    AlertCircle,
    Barcode,
    Check,
    Clock,
    Copy,
    CreditCard,
    QrCode,
    ShieldCheck,
} from 'lucide-vue-next';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import CheckoutSteps from '@/components/storefront/checkout/CheckoutSteps.vue';
import OrderSummary from '@/components/storefront/checkout/OrderSummary.vue';
import ConversionPixels from '@/components/checkout/ConversionPixels.vue';
import CajuPaySdkMount from '@/components/checkout/CajuPaySdkMount.vue';
import { useApiCajuPayCheckout } from '@/composables/useApiCajuPayCheckout.js';
import { isIosDevice } from '@/utils/isIosDevice.js';

defineOptions({ layout: null });

const props = defineProps({
    session_token: { type: String, required: true },
    app_name: { type: String, default: '' },
    conversion_pixels: { type: Object, default: () => ({}) },
    customer_email: { type: String, default: null },
    customer_name: { type: String, default: null },
    customer_cpf: { type: String, default: null },
    customer_phone: { type: String, default: null },
    amount: { type: Number, required: true },
    currency: { type: String, default: 'BRL' },
    available_methods: { type: Array, default: () => [] },
    checkout_payment_methods: { type: Array, default: () => [] },
    commerce_checkout: { type: Boolean, default: true },
    commerce_line_items: { type: Array, default: () => [] },
    order_summary: { type: Object, default: () => ({ lines: [], subtotal: 0, shipping_cost: 0, total: 0 }) },
    shipping_summary: { type: Object, default: () => ({}) },
    card_gateway_slug: { type: String, default: null },
    card_stripe_publishable_key: { type: String, default: '' },
    card_stripe_link_enabled: { type: Boolean, default: true },
    card_payee_code: { type: String, default: '' },
    card_efi_sandbox: { type: Boolean, default: false },
    card_pagarme_public_key: { type: String, default: '' },
    card_pagarme_api_base_url: { type: String, default: 'https://api.pagar.me/core/v5' },
    card_mercadopago_public_key: { type: String, default: '' },
    card_installments_enabled: { type: Boolean, default: false },
    card_max_installments: { type: Number, default: 1 },
    storeTheme: { type: Object, default: null },
    menuCategories: { type: Array, default: () => [] },
    cartCount: { type: Number, default: 0 },
});

const page = usePage();
const error = ref(null);
const selectedMethod = ref(null);
const cardSubmitting = ref(false);
const inlineSubmitting = ref(false);

const cardHolderName = ref('');
const efiCardNumber = ref('');
const efiCardExp = ref('');
const efiCardCvv = ref('');
const selectedInstallments = ref(1);
const stripeCardRef = ref(null);
const stripeInstance = ref(null);
const stripeCardElement = ref(null);

/** @type {import('vue').Ref<null | {
 *   method: 'pix'|'boleto',
 *   token: string,
 *   order_id: number,
 *   qrcode?: string|null,
 *   copy_paste?: string|null,
 *   barcode?: string,
 *   pdf_url?: string|null,
 *   amount_formatted?: string,
 *   created_at?: number,
 *   expiry_seconds?: number,
 *   redirect_after_purchase?: string|null,
 * }>} */
const inlinePayment = ref(null);
const copyFeedback = ref('');
const qrImageFailed = ref(false);
const payStatus = ref('pending');
const timeLeft = ref(900);
let pollInterval = null;
let timerInterval = null;

const payUrl = '/commerce/checkout/pay';

const flashError = computed(() => page.props.flash?.error ?? null);

const shippingAddr = computed(() => props.shipping_summary?.address || {});
const shippingQuoteInfo = computed(() => props.shipping_summary?.quote || null);

const addressText = computed(() => {
    const a = shippingAddr.value;
    if (!a || typeof a !== 'object') return '';
    return [
        a.street,
        a.number,
        a.complement,
        a.district,
        a.city && a.state ? `${a.city} - ${a.state}` : (a.city || a.state),
        a.cep ? `CEP ${String(a.cep).replace(/(\d{5})(\d{3})/, '$1-$2')}` : null,
    ].filter(Boolean).join(', ');
});

const summaryLines = computed(() =>
    props.order_summary?.lines?.length
        ? props.order_summary.lines
        : (props.commerce_line_items || [])
);
const summarySubtotal = computed(() => Number(props.order_summary?.subtotal ?? props.amount ?? 0));
const summaryShipping = computed(() => Number(props.order_summary?.shipping_cost ?? 0));
const summaryTotal = computed(() => Number(props.order_summary?.total ?? props.amount ?? 0));
const summaryShippingLabel = computed(
    () => props.order_summary?.shipping_label || shippingQuoteInfo.value?.name || null
);

const visiblePaymentMethods = computed(() => {
    const list = Array.isArray(props.checkout_payment_methods) && props.checkout_payment_methods.length > 0
        ? props.checkout_payment_methods
        : (props.available_methods || []).map((id) => ({
            id,
            label: id,
            gateway_slug: props.card_gateway_slug,
        }));
    return list.filter((m) => {
        if (m.id === 'apple_pay' && !isIosDevice()) return false;
        if (m.id === 'google_pay' && isIosDevice()) return false;
        if (m.id === 'apple_pay' || m.id === 'google_pay') return false;
        return true;
    });
});

const currentMethod = computed(() =>
    visiblePaymentMethods.value.find((m) => m.id === selectedMethod.value) ?? null
);

const gatewaySlug = computed(() =>
    (currentMethod.value?.gateway_slug || props.card_gateway_slug || '').toLowerCase()
);

const canPayWithStripe = computed(
    () => gatewaySlug.value === 'stripe' && (props.card_stripe_publishable_key || '').trim() !== ''
);
const canPayWithEfi = computed(
    () => gatewaySlug.value === 'efi' && (props.card_payee_code || '').trim() !== ''
);
const canPayWithPagarme = computed(
    () => gatewaySlug.value === 'pagarme' && (props.card_pagarme_public_key || '').trim() !== ''
);
const canPayWithMercadopago = computed(
    () => gatewaySlug.value === 'mercadopago' && (props.card_mercadopago_public_key || '').trim() !== ''
);
const canPayWithCajuPay = computed(() => gatewaySlug.value === 'cajupay');
const canPayWithCajuPaySdk = computed(
    () => canPayWithCajuPay.value && selectedMethod.value === 'card'
);

const cardMaxInstallments = computed(() => Math.min(12, Math.max(1, props.card_max_installments || 1)));
const showCardInstallments = computed(
    () => props.card_installments_enabled && cardMaxInstallments.value > 1 && (canPayWithPagarme.value || canPayWithEfi.value)
);

const paymentLocked = computed(() => !!inlinePayment.value);

function onError(errors) {
    error.value =
        errors?.payment_method?.[0]
        || errors?.payment_token?.[0]
        || errors?.session_token?.[0]
        || errors?.payment?.[0]
        || errors?.cpf?.[0]
        || (typeof errors === 'string' ? errors : null)
        || 'Erro ao processar pagamento.';
}

const cajupay = useApiCajuPayCheckout({
    sessionToken: computed(() => props.session_token),
    paymentMethod: computed(() => selectedMethod.value || 'card'),
    displayCurrency: computed(() => props.currency || 'BRL'),
    customerEmail: computed(() => props.customer_email),
    customerName: computed(() => props.customer_name),
    customerCpf: computed(() => props.customer_cpf),
    customerPhone: computed(() => props.customer_phone),
    onError,
});

const {
    cajupayMountRef,
    cajupaySessionToken,
    cajupayError,
    cajupaySessionLoading,
    cardSubmitting: cajupaySubmitting,
    cajupayPayerReadyForPrime,
    cajupayInitialPayer,
    cajupaySyncPayer,
    submitCajuPaySdkFlow,
    beforeCajuPayWalletPrime,
    onCajuPayWalletPaymentCompleted,
} = cajupay;

const qrcodeSrc = computed(() => {
    const q = (inlinePayment.value?.qrcode || '').trim();
    if (!q || qrImageFailed.value) return '';
    if (q.startsWith('data:') || q.startsWith('http://') || q.startsWith('https://')) return q;
    return `data:image/png;base64,${q}`;
});

const showQrFromCopyPaste = computed(
    () => (!qrcodeSrc.value || qrImageFailed.value) && (inlinePayment.value?.copy_paste || '').trim().length > 0
);

const timerDisplay = computed(() => {
    const left = Math.max(0, timeLeft.value);
    const m = Math.floor(left / 60);
    const s = left % 60;
    return `${m < 10 ? '0' : ''}${m}:${s < 10 ? '0' : ''}${s}`;
});

const placing = computed(() =>
    inlineSubmitting.value || cardSubmitting.value || cajupaySubmitting.value
);

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function methodLabel(m) {
    const labels = {
        pix: 'Pix',
        boleto: 'Boleto bancário',
        card: 'Cartão de crédito',
        paypal: 'PayPal',
    };
    return labels[m.id] || m.label || m.id;
}

function methodIcon(id) {
    if (id === 'pix') return QrCode;
    if (id === 'boleto') return Barcode;
    return CreditCard;
}

function stopPolling() {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
}

function selectMethod(id) {
    if (paymentLocked.value) return;
    selectedMethod.value = id;
    error.value = null;
    if (id === 'card' && canPayWithStripe.value) {
        setTimeout(() => initStripeCard(), 80);
    }
}

async function initStripeCard() {
    if (!props.card_stripe_publishable_key?.trim() || !stripeCardRef.value) return;
    try {
        destroyStripeCard();
        const { loadStripe } = await import('@stripe/stripe-js');
        const stripe = await loadStripe(props.card_stripe_publishable_key.trim());
        if (!stripe) return;
        stripeInstance.value = stripe;
        const elements = stripe.elements();
        const cardElement = elements.create('card', {
            style: { base: { fontSize: '16px', color: '#16241D' } },
            hidePostalCode: true,
            disableLink: !props.card_stripe_link_enabled,
        });
        cardElement.mount(stripeCardRef.value);
        stripeCardElement.value = cardElement;
    } catch (e) {
        console.warn('Stripe init failed', e);
    }
}

function destroyStripeCard() {
    if (stripeCardElement.value) {
        try { stripeCardElement.value.unmount(); } catch (_) {}
        stripeCardElement.value = null;
    }
    stripeInstance.value = null;
}

watch(selectedMethod, (m) => {
    if (m !== 'card') destroyStripeCard();
});

function updateTimer() {
    const pay = inlinePayment.value;
    if (!pay || pay.method !== 'pix') return;
    const end = ((pay.created_at || Math.floor(Date.now() / 1000)) + (pay.expiry_seconds || 900)) * 1000;
    timeLeft.value = Math.max(0, Math.floor((end - Date.now()) / 1000));
    if (timeLeft.value <= 0) stopPolling();
}

async function checkOrderStatus() {
    const pay = inlinePayment.value;
    if (!pay?.token || payStatus.value === 'completed') return;
    try {
        const { data } = await axios.get('/checkout/order-status', { params: { token: pay.token } });
        if (data.status === 'completed') {
            payStatus.value = 'completed';
            stopPolling();
            const url = data.redirect_url || pay.redirect_after_purchase || '/checkout/obrigado';
            if (url.startsWith('http') || url.startsWith('//')) {
                window.location.href = url;
            } else {
                router.visit(url);
            }
        }
    } catch {
        // keep polling
    }
}

function startInlineWatchers() {
    stopPolling();
    payStatus.value = 'pending';
    qrImageFailed.value = false;
    copyFeedback.value = '';
    updateTimer();
    timerInterval = setInterval(updateTimer, 1000);
    pollInterval = setInterval(checkOrderStatus, 5000);
    setTimeout(checkOrderStatus, 1500);
}

async function submitInline(method) {
    if (inlineSubmitting.value || paymentLocked.value) return;
    error.value = null;
    inlineSubmitting.value = true;
    try {
        const { data } = await axios.post(
            payUrl,
            {
                session_token: props.session_token,
                payment_method: method,
                inline: true,
            },
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }
        );
        if (!data?.ok) {
            error.value = data?.message || 'Não foi possível gerar o pagamento.';
            return;
        }
        inlinePayment.value = {
            method: data.method,
            token: data.token,
            order_id: data.order_id,
            qrcode: data.qrcode ?? null,
            copy_paste: data.copy_paste ?? '',
            barcode: data.barcode ?? '',
            pdf_url: data.pdf_url ?? null,
            amount_formatted: data.amount_formatted,
            created_at: data.created_at || Math.floor(Date.now() / 1000),
            expiry_seconds: data.expiry_seconds || 900,
            redirect_after_purchase: data.redirect_after_purchase,
        };
        // Badge do header: carrinho já foi esvaziado no backend após gerar o pagamento.
        try {
            page.props.cartCount = 0;
        } catch (_) {}
        startInlineWatchers();
        setTimeout(() => {
            document.getElementById('inline-payment-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 80);
    } catch (e) {
        const msg =
            e?.response?.data?.message
            || e?.response?.data?.errors?.payment_method?.[0]
            || e?.response?.data?.errors?.session_token?.[0]
            || e?.message
            || 'Não foi possível gerar o pagamento.';
        error.value = typeof msg === 'string' ? msg : 'Não foi possível gerar o pagamento.';
    } finally {
        inlineSubmitting.value = false;
    }
}

function placeOrder() {
    error.value = null;
    if (!selectedMethod.value) {
        error.value = 'Selecione uma forma de pagamento.';
        return;
    }
    if (selectedMethod.value === 'pix' || selectedMethod.value === 'boleto') {
        submitInline(selectedMethod.value);
        return;
    }
    if (selectedMethod.value === 'card') {
        if (canPayWithCajuPaySdk.value) {
            submitCajuPaySdkFlow();
            return;
        }
        submitCard();
    }
}

async function copyPixCode() {
    const code = inlinePayment.value?.copy_paste || '';
    if (!code) return;
    try {
        await navigator.clipboard.writeText(code);
        copyFeedback.value = 'Copiado!';
        setTimeout(() => { copyFeedback.value = ''; }, 2000);
    } catch {
        copyFeedback.value = '';
    }
}

async function copyBarcode() {
    const code = inlinePayment.value?.barcode || '';
    if (!code) return;
    try {
        await navigator.clipboard.writeText(code);
        copyFeedback.value = 'Copiado!';
        setTimeout(() => { copyFeedback.value = ''; }, 2000);
    } catch {
        copyFeedback.value = '';
    }
}

async function submitCard() {
    if (cardSubmitting.value) return;
    error.value = null;

    if (canPayWithCajuPay.value) {
        await submitCajuPaySdkFlow();
        return;
    }

    if (canPayWithMercadopago.value) {
        error.value = 'Para Mercado Pago no checkout da loja, use Stripe, Pagar.me ou Efí, ou pague com Pix.';
        return;
    }

    const name = (cardHolderName.value || '').trim();
    if (!name && (canPayWithStripe.value || canPayWithEfi.value || canPayWithPagarme.value)) {
        error.value = 'Informe o nome impresso no cartão.';
        return;
    }

    cardSubmitting.value = true;
    try {
        if (canPayWithStripe.value) {
            if (!stripeInstance.value || !stripeCardElement.value) {
                error.value = 'Aguarde o formulário do cartão carregar.';
                cardSubmitting.value = false;
                return;
            }
            const { error: stripeError, paymentMethod } = await stripeInstance.value.createPaymentMethod({
                type: 'card',
                card: stripeCardElement.value,
                billing_details: { name },
            });
            if (stripeError) {
                error.value = stripeError.message || 'Erro ao processar o cartão.';
                cardSubmitting.value = false;
                return;
            }
            router.post(payUrl, {
                session_token: props.session_token,
                payment_method: 'card',
                payment_token: paymentMethod.id,
                card_mask: paymentMethod.card?.last4 ? `**** ${paymentMethod.card.last4}` : '',
            }, {
                preserveScroll: true,
                onError,
                onFinish: () => { cardSubmitting.value = false; },
            });
            return;
        }

        if (canPayWithPagarme.value || canPayWithEfi.value) {
            const numberDigits = (efiCardNumber.value || '').replace(/\D/g, '');
            const expDigits = (efiCardExp.value || '').replace(/\D/g, '');
            const month = expDigits.slice(0, 2);
            let year = expDigits.slice(2);
            if (year.length === 2) year = `20${year}`;
            const cvv = (efiCardCvv.value || '').replace(/\D/g, '').slice(0, 4);
            if (numberDigits.length < 13 || month.length !== 2 || year.length !== 4 || cvv.length < 3) {
                error.value = 'Preencha os dados do cartão corretamente.';
                cardSubmitting.value = false;
                return;
            }

            if (canPayWithPagarme.value) {
                const pk = (props.card_pagarme_public_key || '').trim();
                const base = String(props.card_pagarme_api_base_url || 'https://api.pagar.me/core/v5').replace(/\/$/, '');
                const holderName = name
                    .normalize('NFD')
                    .replace(/\p{M}/gu, '')
                    .replace(/[^a-zA-Z\s']/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim() || 'Cliente';
                const res = await fetch(`${base}/tokens?appId=${encodeURIComponent(pk)}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({
                        type: 'card',
                        card: {
                            number: numberDigits,
                            holder_name: holderName,
                            exp_month: parseInt(month, 10),
                            exp_year: parseInt(year, 10),
                            cvv,
                        },
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data?.id) {
                    error.value = data?.message || 'Não foi possível tokenizar o cartão.';
                    cardSubmitting.value = false;
                    return;
                }
                const installments = showCardInstallments.value
                    ? Math.min(cardMaxInstallments.value, Math.max(1, selectedInstallments.value))
                    : 1;
                router.post(payUrl, {
                    session_token: props.session_token,
                    payment_method: 'card',
                    payment_token: JSON.stringify({ card_token: data.id, installments }),
                    card_mask: `**** ${numberDigits.slice(-4)}`,
                }, {
                    preserveScroll: true,
                    onError,
                    onFinish: () => { cardSubmitting.value = false; },
                });
                return;
            }

            const EfiPay = (await import('payment-token-efi')).default;
            const env = props.card_efi_sandbox ? 'sandbox' : 'production';
            const instance = EfiPay.CreditCard.setAccount((props.card_payee_code || '').trim()).setEnvironment(env);
            instance.setCardNumber(numberDigits);
            const brand = await instance.verifyCardBrand();
            if (!brand || brand === 'unsupported') {
                error.value = 'Bandeira do cartão não suportada.';
                cardSubmitting.value = false;
                return;
            }
            instance.setCreditCardData({
                brand,
                number: numberDigits,
                cvv,
                expirationMonth: month,
                expirationYear: year,
                reuse: false,
                holderName: name || undefined,
                holderDocument: (props.customer_cpf || '').replace(/\D/g, '') || undefined,
            });
            const result = await instance.getPaymentToken();
            if (!result?.payment_token) {
                error.value = 'Não foi possível gerar o token do cartão.';
                cardSubmitting.value = false;
                return;
            }
            router.post(payUrl, {
                session_token: props.session_token,
                payment_method: 'card',
                payment_token: result.payment_token,
                card_mask: result.card_mask || `**** ${numberDigits.slice(-4)}`,
            }, {
                preserveScroll: true,
                onError,
                onFinish: () => { cardSubmitting.value = false; },
            });
            return;
        }

        error.value = 'Gateway de cartão não configurado para o checkout da loja.';
        cardSubmitting.value = false;
    } catch (e) {
        error.value = e?.message || 'Erro ao processar o cartão.';
        cardSubmitting.value = false;
    }
}

onMounted(() => {
    if (visiblePaymentMethods.value.length === 1) {
        selectMethod(visiblePaymentMethods.value[0].id);
    }
});

onUnmounted(() => {
    stopPolling();
    destroyStripeCard();
});
</script>

<template>
    <ConversionPixels :pixels="conversion_pixels" />
    <StorefrontLayout>
        <Head :title="app_name ? `${app_name} – Pagamento` : 'Pagamento'" />

        <div class="mx-auto max-w-[1100px] px-4 py-8 md:px-8 md:py-12">
            <CheckoutSteps current="pagamento" />

            <div class="grid gap-8 lg:grid-cols-12 lg:items-start">
                <div class="space-y-6 lg:col-span-7">
                    <div
                        v-if="flashError || error"
                        class="flex items-center gap-3 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                        role="alert"
                    >
                        <AlertCircle class="h-5 w-5 shrink-0" />
                        {{ flashError || error }}
                    </div>

                    <section class="space-y-3 border border-[var(--sf-primary)]/20 bg-white/70 p-5 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-[var(--sf-text)]/45">Contato</p>
                                <p class="mt-0.5 font-medium">{{ customer_email }}</p>
                            </div>
                            <Link
                                v-if="!paymentLocked"
                                href="/checkout/entrega"
                                class="text-xs text-[var(--sf-primary)] hover:underline"
                            >
                                Alterar
                            </Link>
                        </div>
                        <div class="flex items-start justify-between gap-3 border-t border-[var(--sf-primary)]/10 pt-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-[var(--sf-text)]/45">Entrega</p>
                                <p class="mt-0.5 text-[var(--sf-text)]/80">{{ addressText || '—' }}</p>
                            </div>
                            <Link
                                v-if="!paymentLocked"
                                href="/checkout/entrega"
                                class="text-xs text-[var(--sf-primary)] hover:underline"
                            >
                                Alterar
                            </Link>
                        </div>
                        <div class="flex items-start justify-between gap-3 border-t border-[var(--sf-primary)]/10 pt-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-[var(--sf-text)]/45">Frete</p>
                                <p class="mt-0.5 font-medium">{{ summaryShippingLabel || '—' }}</p>
                            </div>
                            <Link
                                v-if="!paymentLocked"
                                href="/checkout/entrega"
                                class="text-xs text-[var(--sf-primary)] hover:underline"
                            >
                                Alterar
                            </Link>
                        </div>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-[family-name:var(--sf-font-heading)] text-2xl text-[var(--sf-secondary)]">
                            Pagamento
                        </h2>

                        <div v-if="!visiblePaymentMethods.length" class="border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            Nenhum método disponível.
                            <Link href="/configuracoes/pagamento" class="underline">Configurar pagamento</Link>
                        </div>

                        <div class="space-y-2">
                            <label
                                v-for="m in visiblePaymentMethods"
                                :key="m.id"
                                class="flex items-center gap-3 border px-4 py-3.5 transition"
                                :class="[
                                    selectedMethod === m.id
                                        ? 'border-[var(--sf-secondary)] bg-[var(--sf-secondary)]/5'
                                        : 'border-[var(--sf-primary)]/20 bg-white/70',
                                    paymentLocked ? 'cursor-default opacity-70' : 'cursor-pointer hover:border-[var(--sf-primary)]/45',
                                ]"
                            >
                                <input
                                    type="radio"
                                    class="sr-only"
                                    name="pay_method"
                                    :checked="selectedMethod === m.id"
                                    :disabled="paymentLocked"
                                    @change="selectMethod(m.id)"
                                />
                                <span
                                    class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border"
                                    :class="selectedMethod === m.id ? 'border-[var(--sf-secondary)]' : 'border-zinc-300'"
                                >
                                    <span
                                        v-if="selectedMethod === m.id"
                                        class="h-2 w-2 rounded-full bg-[var(--sf-secondary)]"
                                    />
                                </span>
                                <component :is="methodIcon(m.id)" class="h-5 w-5 shrink-0 text-[var(--sf-text)]/70" />
                                <span class="flex-1 text-sm font-medium">{{ methodLabel(m) }}</span>
                            </label>
                        </div>

                        <div
                            v-if="selectedMethod === 'card' && !inlinePayment"
                            class="space-y-3 border border-[var(--sf-primary)]/20 bg-white/70 p-4"
                        >
                            <template v-if="canPayWithCajuPaySdk">
                                <p v-if="cajupayError" class="text-sm text-red-700" role="alert">{{ cajupayError }}</p>
                                <div
                                    v-if="!cajupaySessionToken && cajupaySessionLoading"
                                    class="h-32 animate-pulse bg-[var(--sf-primary)]/10"
                                    aria-hidden="true"
                                />
                                <CajuPaySdkMount
                                    ref="cajupayMountRef"
                                    payment-method="card"
                                    :session-token="cajupaySessionToken"
                                    :initial-payer="cajupayInitialPayer"
                                    :sync-payer="cajupaySyncPayer"
                                    container-id="storefront-cajupay-card"
                                    :before-wallet-prime="beforeCajuPayWalletPrime"
                                    :payer-ready-for-prime="cajupayPayerReadyForPrime"
                                    @wallet-payment-completed="onCajuPayWalletPaymentCompleted"
                                />
                            </template>

                            <div v-else-if="canPayWithStripe">
                                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[var(--sf-text)]/55">Nome no cartão</label>
                                <input
                                    v-model="cardHolderName"
                                    type="text"
                                    autocomplete="cc-name"
                                    class="mb-3 w-full border border-[var(--sf-primary)]/25 bg-white px-4 py-3 text-sm outline-none focus:border-[var(--sf-primary)]"
                                />
                                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[var(--sf-text)]/55">Dados do cartão</label>
                                <div ref="stripeCardRef" class="min-h-[3.25rem] border border-[var(--sf-primary)]/25 bg-white px-4 py-3" />
                            </div>

                            <template v-else-if="canPayWithPagarme || canPayWithEfi">
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[var(--sf-text)]/55">Nome no cartão</label>
                                    <input v-model="cardHolderName" type="text" autocomplete="cc-name" class="w-full border border-[var(--sf-primary)]/25 bg-white px-4 py-3 text-sm outline-none focus:border-[var(--sf-primary)]" />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[var(--sf-text)]/55">Número</label>
                                    <input v-model="efiCardNumber" type="text" inputmode="numeric" autocomplete="cc-number" placeholder="0000 0000 0000 0000" class="w-full border border-[var(--sf-primary)]/25 bg-white px-4 py-3 text-sm outline-none focus:border-[var(--sf-primary)]" />
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[var(--sf-text)]/55">Validade</label>
                                        <input v-model="efiCardExp" type="text" inputmode="numeric" autocomplete="cc-exp" placeholder="MM/AAAA" class="w-full border border-[var(--sf-primary)]/25 bg-white px-4 py-3 text-sm outline-none focus:border-[var(--sf-primary)]" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[var(--sf-text)]/55">CVV</label>
                                        <input v-model="efiCardCvv" type="text" inputmode="numeric" autocomplete="cc-csc" maxlength="4" placeholder="123" class="w-full border border-[var(--sf-primary)]/25 bg-white px-4 py-3 text-sm outline-none focus:border-[var(--sf-primary)]" />
                                    </div>
                                </div>
                                <div v-if="showCardInstallments">
                                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[var(--sf-text)]/55">Parcelas</label>
                                    <select v-model.number="selectedInstallments" class="w-full border border-[var(--sf-primary)]/25 bg-white px-4 py-3 text-sm outline-none focus:border-[var(--sf-primary)]">
                                        <option v-for="n in cardMaxInstallments" :key="n" :value="n">
                                            {{ n }}x de {{ formatMoney(amount / n) }}
                                        </option>
                                    </select>
                                </div>
                            </template>

                            <p v-else class="text-sm text-[var(--sf-text)]/60">
                                Gateway de cartão não suportado neste checkout. Use Pix/Boleto ou configure o gateway em
                                <Link href="/configuracoes/pagamento" class="text-[var(--sf-primary)] underline">Pagamento</Link>.
                            </p>
                        </div>

                        <button
                            v-if="selectedMethod && !inlinePayment"
                            type="button"
                            class="sf-btn-gold w-full px-6 py-4 text-xs font-semibold tracking-[0.25em] uppercase disabled:opacity-50"
                            :disabled="placing"
                            @click="placeOrder"
                        >
                            {{ placing ? 'Processando...' : 'Fazer pedido' }}
                        </button>

                        <!-- PIX gerado na mesma página -->
                        <div
                            v-if="inlinePayment?.method === 'pix'"
                            id="inline-payment-panel"
                            class="space-y-5 border border-[var(--sf-secondary)]/30 bg-white/80 p-5"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="font-[family-name:var(--sf-font-heading)] text-xl text-[var(--sf-secondary)]">
                                        Pague com Pix
                                    </p>
                                    <p class="mt-1 text-sm text-[var(--sf-text)]/65">
                                        Escaneie o QR Code ou copie o código. A confirmação é automática.
                                    </p>
                                </div>
                                <div
                                    class="inline-flex items-center gap-2 border border-[var(--sf-primary)]/20 bg-[var(--sf-primary)]/5 px-3 py-1.5 text-sm font-medium"
                                    :class="timeLeft <= 0 ? 'text-red-700' : 'text-[var(--sf-text)]'"
                                >
                                    <Clock class="h-4 w-4" />
                                    {{ timerDisplay }}
                                </div>
                            </div>

                            <p class="text-center text-2xl font-semibold text-[var(--sf-secondary)]">
                                {{ inlinePayment.amount_formatted || formatMoney(amount) }}
                            </p>

                            <div class="flex justify-center">
                                <div class="border border-[var(--sf-primary)]/15 bg-white p-4">
                                    <img
                                        v-if="qrcodeSrc"
                                        :src="qrcodeSrc"
                                        alt="QR Code Pix"
                                        class="h-48 w-48 object-contain"
                                        @error="qrImageFailed = true"
                                    />
                                    <QrcodeVue
                                        v-else-if="showQrFromCopyPaste"
                                        :value="inlinePayment.copy_paste"
                                        :size="192"
                                        level="M"
                                        render-as="canvas"
                                    />
                                    <div
                                        v-else
                                        class="flex h-48 w-48 items-center justify-center text-sm text-[var(--sf-text)]/50"
                                    >
                                        Gerando QR Code…
                                    </div>
                                </div>
                            </div>

                            <div v-if="inlinePayment.copy_paste" class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-[var(--sf-text)]/45">
                                    Pix Copia e Cola
                                </p>
                                <div class="flex gap-2">
                                    <input
                                        type="text"
                                        readonly
                                        :value="inlinePayment.copy_paste"
                                        class="min-w-0 flex-1 truncate border border-[var(--sf-primary)]/20 bg-white px-3 py-2.5 text-xs text-[var(--sf-text)]/80 outline-none"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex shrink-0 items-center gap-1.5 border border-[var(--sf-secondary)] bg-[var(--sf-secondary)] px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-white"
                                        @click="copyPixCode"
                                    >
                                        <Check v-if="copyFeedback" class="h-3.5 w-3.5" />
                                        <Copy v-else class="h-3.5 w-3.5" />
                                        {{ copyFeedback || 'Copiar' }}
                                    </button>
                                </div>
                            </div>

                            <p
                                class="text-center text-sm"
                                :class="payStatus === 'completed' ? 'text-emerald-700' : 'text-[var(--sf-text)]/55'"
                            >
                                {{ payStatus === 'completed' ? 'Pagamento aprovado! Redirecionando…' : 'Aguardando confirmação do pagamento…' }}
                            </p>
                        </div>

                        <!-- Boleto gerado na mesma página -->
                        <div
                            v-if="inlinePayment?.method === 'boleto'"
                            id="inline-payment-panel"
                            class="space-y-5 border border-[var(--sf-secondary)]/30 bg-white/80 p-5"
                        >
                            <div>
                                <p class="font-[family-name:var(--sf-font-heading)] text-xl text-[var(--sf-secondary)]">
                                    Boleto bancário
                                </p>
                                <p class="mt-1 text-sm text-[var(--sf-text)]/65">
                                    Pague até o vencimento. A confirmação pode levar até 3 dias úteis.
                                </p>
                            </div>

                            <p class="text-center text-2xl font-semibold text-[var(--sf-secondary)]">
                                {{ inlinePayment.amount_formatted || formatMoney(amount) }}
                            </p>

                            <div v-if="inlinePayment.barcode" class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-[var(--sf-text)]/45">
                                    Código de barras
                                </p>
                                <div class="flex gap-2">
                                    <input
                                        type="text"
                                        readonly
                                        :value="inlinePayment.barcode"
                                        class="min-w-0 flex-1 truncate border border-[var(--sf-primary)]/20 bg-white px-3 py-2.5 text-xs text-[var(--sf-text)]/80 outline-none"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex shrink-0 items-center gap-1.5 border border-[var(--sf-secondary)] bg-[var(--sf-secondary)] px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-white"
                                        @click="copyBarcode"
                                    >
                                        <Check v-if="copyFeedback" class="h-3.5 w-3.5" />
                                        <Copy v-else class="h-3.5 w-3.5" />
                                        {{ copyFeedback || 'Copiar' }}
                                    </button>
                                </div>
                            </div>

                            <a
                                v-if="inlinePayment.pdf_url"
                                :href="inlinePayment.pdf_url"
                                target="_blank"
                                rel="noopener"
                                class="sf-btn-gold inline-flex w-full items-center justify-center px-6 py-3.5 text-xs font-semibold tracking-[0.25em] uppercase"
                            >
                                Abrir boleto (PDF)
                            </a>

                            <p class="text-center text-sm text-[var(--sf-text)]/55">
                                Aguardando confirmação do pagamento…
                            </p>
                        </div>

                        <p class="flex items-center justify-center gap-2 pt-2 text-xs text-[var(--sf-text)]/45">
                            <ShieldCheck class="h-4 w-4" />
                            Pagamento processado de forma segura
                        </p>
                    </section>
                </div>

                <div class="lg:col-span-5 lg:sticky lg:top-6">
                    <OrderSummary
                        :lines="summaryLines"
                        :subtotal="summarySubtotal"
                        :shipping-cost="summaryShipping"
                        :shipping-label="summaryShippingLabel"
                        :total="summaryTotal"
                    />
                </div>
            </div>
        </div>
    </StorefrontLayout>
</template>
