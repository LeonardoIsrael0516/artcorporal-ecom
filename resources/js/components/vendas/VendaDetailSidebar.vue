<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { X, ExternalLink, LoaderCircle, Copy, Check } from 'lucide-vue-next';
import PluginSlotHost from '@/components/plugins/PluginSlotHost.vue';
import PluginRenderZone from '@/components/plugins/PluginRenderZone.vue';
import HorizontalScrollTabs from '@/components/ui/HorizontalScrollTabs.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    venda: { type: Object, default: null },
    plugin_order_detail_panels: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'updated']);

const activeTab = ref('venda');
const shippingTracking = ref('');
const fulfillmentStatus = ref('preparing');
const shippingSaving = ref(false);
const shippingMessage = ref('');
const shippingError = ref('');
const copiedKey = ref('');
let copyTimer = null;

function checkoutSessionFromVenda(v) {
    if (!v) return null;
    return v.checkout_session ?? v.checkoutSession ?? null;
}

function metadataFromVenda(v) {
    if (!v || v.metadata == null) return null;
    return typeof v.metadata === 'object' ? v.metadata : null;
}

const hasShipping = computed(() => {
    const v = props.venda;
    if (!v) return false;
    const meta = metadataFromVenda(v);
    return !!(
        v.shipping_address
        || v.shipping_service
        || v.shipping_tracking
        || meta?.storefront_checkout
        || meta?.commerce_multi_line
    );
});

const shippingAddress = computed(() => {
    const a = props.venda?.shipping_address;
    return a && typeof a === 'object' ? a : {};
});

const formattedCep = computed(() => {
    const digits = String(shippingAddress.value.cep || '').replace(/\D/g, '');
    if (digits.length === 8) return `${digits.slice(0, 5)}-${digits.slice(5)}`;
    return digits || '';
});

const cityState = computed(() => {
    const a = shippingAddress.value;
    if (a.city && a.state) return `${a.city} - ${a.state}`;
    return a.city || a.state || '';
});

const recipientName = computed(() => {
    const a = shippingAddress.value;
    return (a.name || a.recipient_name || props.venda?.user?.name || props.venda?.metadata?.customer_name || '').trim();
});

const recipientPhone = computed(() => {
    const a = shippingAddress.value;
    return String(a.phone || props.venda?.phone || '').trim();
});

const recipientCpf = computed(() => {
    const a = shippingAddress.value;
    const raw = String(a.cpf || props.venda?.cpf || '').replace(/\D/g, '');
    if (raw.length === 11) {
        return raw.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    }
    if (raw.length === 14) {
        return raw.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
    }
    return raw;
});

const shippingAddressText = computed(() => {
    const a = shippingAddress.value;
    if (!a || Object.keys(a).length === 0) return '';
    return [
        a.street,
        a.number,
        a.complement,
        a.district,
        cityState.value || null,
        formattedCep.value ? `CEP ${formattedCep.value}` : null,
    ].filter(Boolean).join(', ');
});

const trackingEvents = computed(() => {
    const meta = metadataFromVenda(props.venda);
    return Array.isArray(meta?.shipping_tracking_events) ? meta.shipping_tracking_events : [];
});

const trackingUrl = computed(() => {
    const meta = metadataFromVenda(props.venda);
    return meta?.shipping_tracking_url || null;
});

watch(
    () => props.venda,
    (v) => {
        shippingTracking.value = v?.shipping_tracking || '';
        const meta = metadataFromVenda(v);
        fulfillmentStatus.value = meta?.fulfillment_status || (v?.shipping_tracking ? 'shipped' : 'preparing');
        shippingMessage.value = '';
        shippingError.value = '';
        copiedKey.value = '';
    },
    { immediate: true }
);

async function copyText(key, value) {
    const text = String(value ?? '').trim();
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
        copiedKey.value = key;
        if (copyTimer) clearTimeout(copyTimer);
        copyTimer = setTimeout(() => { copiedKey.value = ''; }, 1800);
    } catch {
        copiedKey.value = '';
    }
}

function isCopied(key) {
    return copiedKey.value === key;
}

async function saveShipping({ syncFrenet = true } = {}) {
    if (!props.venda?.id || shippingSaving.value) return;
    shippingSaving.value = true;
    shippingMessage.value = '';
    shippingError.value = '';
    try {
        const { data } = await axios.patch(`/vendas/${props.venda.id}/shipping`, {
            shipping_tracking: shippingTracking.value || null,
            fulfillment_status: fulfillmentStatus.value,
            sync_frenet: syncFrenet,
        });
        if (!data?.success) {
            shippingError.value = data?.message || 'Não foi possível salvar.';
            return;
        }
        shippingMessage.value = data.message || 'Salvo.';
        emit('updated', {
            ...props.venda,
            shipping_tracking: data.order?.shipping_tracking ?? shippingTracking.value,
            shipping_service: data.order?.shipping_service ?? props.venda.shipping_service,
            shipping_provider: data.order?.shipping_provider ?? props.venda.shipping_provider,
            shipping_address: data.order?.shipping_address ?? props.venda.shipping_address,
            metadata: data.order?.metadata ?? props.venda.metadata,
        });
    } catch (e) {
        shippingError.value = e?.response?.data?.message || e?.message || 'Erro ao salvar rastreio.';
    } finally {
        shippingSaving.value = false;
    }
}

const utmSource = computed(() => {
    const v = props.venda;
    if (!v) return '';
    const cs = checkoutSessionFromVenda(v);
    const meta = metadataFromVenda(v);
    return (cs?.utm_source || meta?.utm_source || '').trim();
});
const utmCampaign = computed(() => {
    const v = props.venda;
    if (!v) return '';
    const cs = checkoutSessionFromVenda(v);
    const meta = metadataFromVenda(v);
    return (cs?.utm_campaign || meta?.utm_campaign || '').trim();
});
const utmMedium = computed(() => {
    const v = props.venda;
    if (!v) return '';
    const cs = checkoutSessionFromVenda(v);
    const meta = metadataFromVenda(v);
    return (cs?.utm_medium || meta?.utm_medium || '').trim();
});

function close() {
    emit('close');
}

function formatMoney(value, currency = 'BRL') {
    const code = typeof currency === 'string' && currency.trim() ? currency.trim().toUpperCase() : 'BRL';
    const locale = code === 'BRL' ? 'pt-BR' : code === 'EUR' ? 'de-DE' : 'en-US';
    return new Intl.NumberFormat(locale, { style: 'currency', currency: code }).format(value ?? 0);
}

function formatBRL(value) {
    return formatMoney(value, 'BRL');
}

function vendaDisplayAmount(v) {
    if (v?.display_amount_is_producer_share && v.display_amount != null) {
        return v.display_amount;
    }
    return v?.amount_total ?? v?.amount ?? 0;
}

function vendaGrossAmount(v) {
    return v?.gross_amount ?? v?.amount_total ?? v?.amount ?? 0;
}

function formatDate(value) {
    if (!value) return '–';
    const d = new Date(value);
    return d.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function statusLabel(status) {
    const map = {
        completed: 'Pago',
        pending: 'Pendente',
        disputed: 'MED',
        cancelled: 'Cancelado',
        refunded: 'Reembolsado',
    };
    return map[status] ?? status ?? '–';
}

function itemLabel(item) {
    const isBump = Number(item?.position ?? 0) > 0;
    const baseName =
        item?.product?.name ??
        item?.product_offer?.name ??
        item?.subscription_plan?.name ??
        'Item';
    return isBump ? `${baseName} (Bump)` : baseName;
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[100000] flex justify-end"
            aria-modal="true"
            role="dialog"
        >
            <div
                class="fixed inset-0 bg-zinc-900/50 dark:bg-zinc-950/60"
                aria-hidden="true"
                @click="close"
            />
            <aside
                class="relative z-[100001] flex h-full w-full max-w-md flex-col rounded-l-2xl bg-white shadow-2xl dark:bg-zinc-900 sm:w-[420px]"
            >
                <div class="flex items-center justify-between rounded-tl-2xl px-5 py-5">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                        Detalhes da venda
                    </h2>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                        aria-label="Fechar"
                        @click="close"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div v-if="!venda" class="flex flex-1 items-center justify-center p-8">
                    <p class="text-sm text-zinc-500">Nenhuma venda selecionada.</p>
                </div>

                <div v-else class="flex min-w-0 flex-1 flex-col overflow-hidden">
                    <HorizontalScrollTabs
                        aria-label="Abas"
                        :bleed="false"
                        nav-class="gap-1 bg-zinc-50/80 px-4 py-2 dark:bg-zinc-800/50"
                    >
                        <button
                            type="button"
                            :class="[
                                'rounded-lg px-4 py-2.5 text-sm font-medium transition-colors',
                                activeTab === 'venda'
                                    ? 'bg-white text-[var(--color-primary)] shadow-sm dark:bg-zinc-800 dark:text-[var(--color-primary)]'
                                    : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200',
                            ]"
                            @click="activeTab = 'venda'"
                        >
                            Venda
                        </button>
                        <button
                            type="button"
                            :class="[
                                'rounded-lg px-4 py-2.5 text-sm font-medium transition-colors',
                                activeTab === 'cliente'
                                    ? 'bg-white text-[var(--color-primary)] shadow-sm dark:bg-zinc-800 dark:text-[var(--color-primary)]'
                                    : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200',
                            ]"
                            @click="activeTab = 'cliente'"
                        >
                            Cliente
                        </button>
                        <button
                            v-for="panel in plugin_order_detail_panels"
                            :key="panel.id || panel.plugin_slug"
                            type="button"
                            :class="[
                                'rounded-lg px-4 py-2.5 text-sm font-medium transition-colors',
                                activeTab === `plugin-${panel.id}`
                                    ? 'bg-white text-[var(--color-primary)] shadow-sm dark:bg-zinc-800 dark:text-[var(--color-primary)]'
                                    : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200',
                            ]"
                            @click="activeTab = `plugin-${panel.id}`"
                        >
                            {{ panel.label || panel.id }}
                        </button>
                    </HorizontalScrollTabs>

                    <div class="flex-1 overflow-y-auto p-5">
                        <!-- Aba Venda -->
                        <div v-show="activeTab === 'venda'" class="space-y-5">
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">ID da venda</p>
                                <p class="font-mono text-sm text-zinc-700 dark:text-zinc-300">{{ String(venda.id) }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Status</p>
                                <p class="text-sm text-zinc-900 dark:text-white">{{ statusLabel(venda.status) }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Tipo</p>
                                <p class="text-sm text-zinc-900 dark:text-white">{{ venda.payment_type_label ?? 'Pagamento único' }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Valor bruto
                                </p>
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ formatMoney(vendaGrossAmount(venda), venda.currency) }}
                                </p>
                            </div>
                            <div
                                v-if="venda.status === 'completed'"
                                class="space-y-2 rounded-lg border border-zinc-200/80 p-3 dark:border-zinc-700/80"
                            >
                                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Financeiro (gateway)
                                </p>
                                <div class="grid grid-cols-3 gap-2 text-sm">
                                    <div>
                                        <p class="text-[11px] text-zinc-500">Bruto</p>
                                        <p class="font-medium text-zinc-900 dark:text-white">{{ formatMoney(venda.gross_amount ?? vendaGrossAmount(venda), venda.currency) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[11px] text-zinc-500">Taxa</p>
                                        <p class="font-medium text-zinc-900 dark:text-white">
                                            {{ formatMoney(venda.gateway_fee ?? 0, venda.currency) }}
                                            <span
                                                v-if="venda.fee_source === 'gateway_webhook' || venda.fee_source === 'cajupay_webhook'"
                                                class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-medium text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"
                                                title="Taxa informada pelo gateway"
                                            >real</span>
                                            <span
                                                v-else-if="venda.fee_source === 'estimated'"
                                                class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-800 dark:bg-amber-950/50 dark:text-amber-300"
                                                title="Taxa estimada conforme configuração do gateway"
                                            >est.</span>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-[11px] text-zinc-500">Líquido</p>
                                        <p class="font-medium text-zinc-900 dark:text-white">{{ formatMoney(venda.net_amount ?? venda.gross_amount ?? vendaGrossAmount(venda), venda.currency) }}</p>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-else
                                class="space-y-2 rounded-lg border border-dashed border-zinc-200/80 p-3 dark:border-zinc-700/80"
                            >
                                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Financeiro estimado
                                </p>
                                <div class="grid grid-cols-3 gap-2 text-sm">
                                    <div>
                                        <p class="text-[11px] text-zinc-500">Bruto</p>
                                        <p class="font-medium text-zinc-900 dark:text-white">{{ formatMoney(vendaGrossAmount(venda), venda.currency) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[11px] text-zinc-500">Taxa</p>
                                        <p class="font-medium text-zinc-900 dark:text-white">
                                            {{ formatMoney(venda.gateway_fee ?? 0, venda.currency) }}
                                            <span class="text-[10px] font-normal text-zinc-500"> (est.)</span>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-[11px] text-zinc-500">Líquido</p>
                                        <p class="font-medium text-zinc-900 dark:text-white">
                                            {{ formatMoney(venda.net_amount ?? Math.max(0, vendaGrossAmount(venda) - Number(venda.gateway_fee ?? 0)), venda.currency) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div v-if="venda.has_partner_split && venda.display_amount_is_producer_share" class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Sua parte
                                    <span v-if="venda.display_amount_is_estimated"> (estimada)</span>
                                </p>
                                <p class="text-sm text-zinc-900 dark:text-white">
                                    {{ formatMoney(vendaDisplayAmount(venda), venda.currency) }}
                                    <span
                                        v-if="venda.display_amount_is_estimated"
                                        class="text-xs font-normal text-zinc-500"
                                        title="Estimativa até confirmação do pagamento e alocação de comissões"
                                    > *</span>
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    Valor que fica com você após taxas do gateway e comissões de afiliados/co-produtores.
                                </p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Produto</p>
                                <p class="text-sm text-zinc-900 dark:text-white">{{ venda.product_display_name ?? venda.product?.name ?? '–' }}</p>
                            </div>
                            <div v-if="venda.is_affiliate_sale" class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Afiliado</p>
                                <p class="text-sm text-zinc-900 dark:text-white">
                                    {{ venda.affiliate?.name ?? '—' }}
                                    <span v-if="venda.affiliate?.code" class="mt-0.5 block font-mono text-xs text-zinc-500">
                                        ref {{ venda.affiliate.code }}
                                    </span>
                                </p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Método de pagamento</p>
                                <p class="text-sm text-zinc-900 dark:text-white">{{ venda.gateway_label ?? '–' }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Parcelas</p>
                                <p class="text-sm text-zinc-900 dark:text-white">1</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Recorrência</p>
                                <p class="text-sm text-zinc-900 dark:text-white">{{ venda.subscription_plan_id ? 'Assinatura' : '–' }}</p>
                            </div>
                            <div class="space-y-2" v-if="(venda.order_items ?? []).length">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Itens da compra</p>
                                <div class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900">
                                    <div
                                        v-for="(item, idx) in (venda.order_items ?? [])"
                                        :key="idx"
                                        class="flex items-center justify-between gap-3 px-4 py-3"
                                    >
                                        <p class="text-sm text-zinc-900 dark:text-white">
                                            {{ itemLabel(item) }}
                                        </p>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-white">
                                            {{ formatMoney(item.amount, venda.currency) }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="hasShipping"
                                class="space-y-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                        Envio / Rastreio
                                    </p>
                                    <button
                                        v-if="shippingAddressText"
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-md border border-zinc-200 px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                        @click="copyText('full_address', shippingAddressText)"
                                    >
                                        <Check v-if="isCopied('full_address')" class="h-3 w-3 text-emerald-600" />
                                        <Copy v-else class="h-3 w-3" />
                                        {{ isCopied('full_address') ? 'Copiado' : 'Copiar endereço' }}
                                    </button>
                                </div>

                                <div v-if="venda.shipping_service" class="space-y-1">
                                    <p class="text-[11px] text-zinc-500">Frete</p>
                                    <p class="text-sm text-zinc-900 dark:text-white">
                                        {{ venda.shipping_service }}
                                        <span v-if="venda.shipping_days" class="text-zinc-500"> · {{ venda.shipping_days }} dia(s)</span>
                                        <span v-if="venda.shipping_amount != null" class="text-zinc-500"> · {{ formatMoney(venda.shipping_amount, venda.currency) }}</span>
                                    </p>
                                </div>

                                <div v-if="recipientName || recipientPhone || recipientCpf" class="grid gap-2 sm:grid-cols-2">
                                    <div v-if="recipientName" class="rounded-lg bg-zinc-50 px-2.5 py-2 dark:bg-zinc-900/60">
                                        <div class="mb-0.5 flex items-center justify-between gap-2">
                                            <p class="text-[11px] text-zinc-500">Destinatário</p>
                                            <button type="button" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200" title="Copiar" @click="copyText('name', recipientName)">
                                                <Check v-if="isCopied('name')" class="h-3.5 w-3.5 text-emerald-600" />
                                                <Copy v-else class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                        <p class="text-sm text-zinc-900 dark:text-white">{{ recipientName }}</p>
                                    </div>
                                    <div v-if="recipientPhone" class="rounded-lg bg-zinc-50 px-2.5 py-2 dark:bg-zinc-900/60">
                                        <div class="mb-0.5 flex items-center justify-between gap-2">
                                            <p class="text-[11px] text-zinc-500">Telefone</p>
                                            <button type="button" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200" title="Copiar" @click="copyText('phone', recipientPhone.replace(/\D/g, '') || recipientPhone)">
                                                <Check v-if="isCopied('phone')" class="h-3.5 w-3.5 text-emerald-600" />
                                                <Copy v-else class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                        <p class="text-sm text-zinc-900 dark:text-white">{{ recipientPhone }}</p>
                                    </div>
                                    <div v-if="recipientCpf" class="rounded-lg bg-zinc-50 px-2.5 py-2 dark:bg-zinc-900/60 sm:col-span-2">
                                        <div class="mb-0.5 flex items-center justify-between gap-2">
                                            <p class="text-[11px] text-zinc-500">CPF/CNPJ</p>
                                            <button type="button" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200" title="Copiar" @click="copyText('cpf', String(shippingAddress.cpf || venda.cpf || '').replace(/\D/g, '') || recipientCpf)">
                                                <Check v-if="isCopied('cpf')" class="h-3.5 w-3.5 text-emerald-600" />
                                                <Copy v-else class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                        <p class="font-mono text-sm text-zinc-900 dark:text-white">{{ recipientCpf }}</p>
                                    </div>
                                </div>

                                <div v-if="formattedCep || shippingAddress.street" class="space-y-2">
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-zinc-500">Endereço</p>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <div v-if="formattedCep" class="rounded-lg bg-zinc-50 px-2.5 py-2 dark:bg-zinc-900/60">
                                            <div class="mb-0.5 flex items-center justify-between gap-2">
                                                <p class="text-[11px] text-zinc-500">CEP</p>
                                                <button type="button" class="inline-flex items-center gap-1 text-[11px] font-medium text-[var(--color-primary)]" @click="copyText('cep', String(shippingAddress.cep || '').replace(/\D/g, '') || formattedCep)">
                                                    <Check v-if="isCopied('cep')" class="h-3.5 w-3.5" />
                                                    <Copy v-else class="h-3.5 w-3.5" />
                                                    {{ isCopied('cep') ? 'Copiado' : 'Copiar' }}
                                                </button>
                                            </div>
                                            <p class="font-mono text-sm font-semibold text-zinc-900 dark:text-white">{{ formattedCep }}</p>
                                        </div>
                                        <div v-if="shippingAddress.number" class="rounded-lg bg-zinc-50 px-2.5 py-2 dark:bg-zinc-900/60">
                                            <div class="mb-0.5 flex items-center justify-between gap-2">
                                                <p class="text-[11px] text-zinc-500">Número</p>
                                                <button type="button" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200" title="Copiar" @click="copyText('number', shippingAddress.number)">
                                                    <Check v-if="isCopied('number')" class="h-3.5 w-3.5 text-emerald-600" />
                                                    <Copy v-else class="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                            <p class="text-sm text-zinc-900 dark:text-white">{{ shippingAddress.number }}</p>
                                        </div>
                                        <div v-if="shippingAddress.street" class="rounded-lg bg-zinc-50 px-2.5 py-2 dark:bg-zinc-900/60 sm:col-span-2">
                                            <div class="mb-0.5 flex items-center justify-between gap-2">
                                                <p class="text-[11px] text-zinc-500">Rua</p>
                                                <button type="button" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200" title="Copiar" @click="copyText('street', shippingAddress.street)">
                                                    <Check v-if="isCopied('street')" class="h-3.5 w-3.5 text-emerald-600" />
                                                    <Copy v-else class="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                            <p class="text-sm text-zinc-900 dark:text-white">{{ shippingAddress.street }}</p>
                                        </div>
                                        <div v-if="shippingAddress.complement" class="rounded-lg bg-zinc-50 px-2.5 py-2 dark:bg-zinc-900/60">
                                            <div class="mb-0.5 flex items-center justify-between gap-2">
                                                <p class="text-[11px] text-zinc-500">Complemento</p>
                                                <button type="button" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200" title="Copiar" @click="copyText('complement', shippingAddress.complement)">
                                                    <Check v-if="isCopied('complement')" class="h-3.5 w-3.5 text-emerald-600" />
                                                    <Copy v-else class="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                            <p class="text-sm text-zinc-900 dark:text-white">{{ shippingAddress.complement }}</p>
                                        </div>
                                        <div v-if="shippingAddress.district" class="rounded-lg bg-zinc-50 px-2.5 py-2 dark:bg-zinc-900/60">
                                            <div class="mb-0.5 flex items-center justify-between gap-2">
                                                <p class="text-[11px] text-zinc-500">Bairro</p>
                                                <button type="button" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200" title="Copiar" @click="copyText('district', shippingAddress.district)">
                                                    <Check v-if="isCopied('district')" class="h-3.5 w-3.5 text-emerald-600" />
                                                    <Copy v-else class="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                            <p class="text-sm text-zinc-900 dark:text-white">{{ shippingAddress.district }}</p>
                                        </div>
                                        <div v-if="cityState" class="rounded-lg bg-zinc-50 px-2.5 py-2 dark:bg-zinc-900/60 sm:col-span-2">
                                            <div class="mb-0.5 flex items-center justify-between gap-2">
                                                <p class="text-[11px] text-zinc-500">Cidade / UF</p>
                                                <button type="button" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200" title="Copiar" @click="copyText('city_state', cityState)">
                                                    <Check v-if="isCopied('city_state')" class="h-3.5 w-3.5 text-emerald-600" />
                                                    <Copy v-else class="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                            <p class="text-sm text-zinc-900 dark:text-white">{{ cityState }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <label class="text-[11px] text-zinc-500">Status do envio</label>
                                    <select
                                        v-model="fulfillmentStatus"
                                        class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                                    >
                                        <option value="preparing">Preparando envio</option>
                                        <option value="shipped">Enviado</option>
                                        <option value="delivered">Entregue</option>
                                        <option value="returned">Devolvido</option>
                                    </select>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[11px] text-zinc-500">Código de rastreio</label>
                                    <div class="flex gap-2">
                                        <input
                                            v-model="shippingTracking"
                                            type="text"
                                            placeholder="Ex.: AA123456789BR"
                                            class="min-w-0 flex-1 rounded-lg border border-zinc-200 bg-white px-3 py-2 font-mono text-sm dark:border-zinc-700 dark:bg-zinc-900"
                                        />
                                        <button
                                            v-if="shippingTracking"
                                            type="button"
                                            class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-zinc-200 px-3 py-2 text-xs font-medium text-zinc-700 dark:border-zinc-700 dark:text-zinc-200"
                                            @click="copyText('tracking', shippingTracking)"
                                        >
                                            <Check v-if="isCopied('tracking')" class="h-3.5 w-3.5 text-emerald-600" />
                                            <Copy v-else class="h-3.5 w-3.5" />
                                            {{ isCopied('tracking') ? 'Ok' : 'Copiar' }}
                                        </button>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--color-primary)] px-3 py-2 text-xs font-semibold text-white disabled:opacity-50"
                                        :disabled="shippingSaving"
                                        @click="saveShipping({ syncFrenet: true })"
                                    >
                                        <LoaderCircle v-if="shippingSaving" class="h-3.5 w-3.5 animate-spin" />
                                        Salvar e consultar Frenet
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-zinc-200 px-3 py-2 text-xs font-medium text-zinc-700 dark:border-zinc-700 dark:text-zinc-200 disabled:opacity-50"
                                        :disabled="shippingSaving"
                                        @click="saveShipping({ syncFrenet: false })"
                                    >
                                        Só salvar
                                    </button>
                                    <a
                                        v-if="trackingUrl"
                                        :href="trackingUrl"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex items-center gap-1 text-xs text-[var(--color-primary)] hover:underline"
                                    >
                                        Abrir rastreio
                                        <ExternalLink class="h-3 w-3" />
                                    </a>
                                </div>
                                <p v-if="shippingMessage" class="text-xs text-emerald-600 dark:text-emerald-400">{{ shippingMessage }}</p>
                                <p v-if="shippingError" class="text-xs text-red-600 dark:text-red-400">{{ shippingError }}</p>
                                <ul v-if="trackingEvents.length" class="space-y-2 border-t border-zinc-100 pt-2 dark:border-zinc-800">
                                    <li
                                        v-for="(ev, idx) in trackingEvents.slice(0, 8)"
                                        :key="idx"
                                        class="text-xs text-zinc-600 dark:text-zinc-300"
                                    >
                                        <span class="font-medium text-zinc-900 dark:text-white">{{ ev.description || 'Evento' }}</span>
                                        <span v-if="ev.at" class="text-zinc-400"> · {{ ev.at }}</span>
                                        <span v-if="ev.location" class="text-zinc-400"> · {{ ev.location }}</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">URL do Checkout</p>
                                <a
                                    v-if="venda.checkout_url"
                                    :href="venda.checkout_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-start gap-1 break-all text-sm text-[var(--color-primary)] hover:underline"
                                >
                                    {{ venda.checkout_url }}
                                    <ExternalLink class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                </a>
                                <p v-else class="text-sm text-zinc-500">–</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Comprovação
                                    <span
                                        class="ml-1 inline-flex h-4 w-4 items-center justify-center rounded-full border border-zinc-200 text-[10px] text-zinc-500 dark:border-zinc-700 dark:text-zinc-400"
                                        title="Gera um dossiê com dados do comprador + evidências de entrega/atividade (progresso, logs, IP). Útil para comprovar a venda em gateways (MED/chargeback/auditoria)."
                                    >
                                        ?
                                    </span>
                                </p>
                                <a
                                    :href="`/vendas/${venda.id}/comprovacao`"
                                    class="inline-flex items-center gap-1 text-sm text-[var(--color-primary)] hover:underline"
                                    title="Abrir dossiê de comprovação (documento para comprovar a venda e o acesso/atividade do aluno)"
                                >
                                    Abrir dossiê de comprovação
                                    <ExternalLink class="h-3.5 w-3.5 shrink-0" />
                                </a>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">utm_source</p>
                                <p class="text-sm" :class="utmSource ? 'text-zinc-900 dark:text-white' : 'text-zinc-500'">
                                    {{ utmSource || 'Não informado' }}
                                </p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">utm_campaign</p>
                                <p class="text-sm" :class="utmCampaign ? 'text-zinc-900 dark:text-white' : 'text-zinc-500'">
                                    {{ utmCampaign || 'Não informado' }}
                                </p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">utm_medium</p>
                                <p class="text-sm" :class="utmMedium ? 'text-zinc-900 dark:text-white' : 'text-zinc-500'">
                                    {{ utmMedium || 'Não informado' }}
                                </p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Data de criação</p>
                                <p class="text-sm text-zinc-900 dark:text-white">{{ formatDate(venda.created_at) }}</p>
                            </div>
                        </div>

                        <div
                            v-for="panel in plugin_order_detail_panels"
                            :key="`panel-${panel.id}`"
                            v-show="activeTab === `plugin-${panel.id}`"
                        >
                            <PluginSlotHost
                                layout="stack"
                                :items="[panel]"
                                :context="{ venda, order: venda }"
                            />
                            <PluginRenderZone
                                zone="vendas.detail.after_panel"
                                :context="{ venda, order: venda }"
                            />
                        </div>

                        <!-- Aba Cliente -->
                        <div v-show="activeTab === 'cliente'" class="space-y-5">
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Nome</p>
                                <p class="text-sm text-zinc-900 dark:text-white">{{ venda.user?.name ?? venda.email ?? '–' }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">E-mail</p>
                                <p class="text-sm text-zinc-900 dark:text-white">{{ venda.email ?? venda.user?.email ?? '–' }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Celular</p>
                                <p class="text-sm text-zinc-900 dark:text-white">{{ venda.phone ?? '–' }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">CPF</p>
                                <p class="text-sm text-zinc-900 dark:text-white">{{ venda.cpf ?? '–' }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">IP</p>
                                <p class="text-sm text-zinc-900 dark:text-white">{{ venda.customer_ip ?? '–' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </Teleport>
</template>
