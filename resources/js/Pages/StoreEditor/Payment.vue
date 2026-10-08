<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import GatewaySelect from '@/components/ui/GatewaySelect.vue';
import { CreditCard, Loader2, Save, ExternalLink } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    settings: { type: Object, required: true },
    gateways_by_method: { type: Object, default: () => ({}) },
});

const saving = ref(false);
const pg = props.settings?.payment_gateways ?? {};

const form = reactive({
    payment_gateways: {
        pix: pg.pix ?? '__default__',
        card: pg.card ?? '__default__',
        boleto: pg.boleto ?? '__default__',
        apple_pay: pg.apple_pay ?? '',
        google_pay: pg.google_pay ?? '',
        paypal: pg.paypal ?? '',
        paypal_display_as: pg.paypal_display_as ?? 'paypal',
        paypal_show_wallet: !!pg.paypal_show_wallet,
    },
});

const methods = [
    { key: 'pix', label: 'Pix', hint: 'Pagamento instantâneo' },
    { key: 'card', label: 'Cartão de crédito', hint: 'Parcelamento conforme o gateway' },
    { key: 'boleto', label: 'Boleto bancário', hint: 'Compensação em alguns dias úteis' },
    { key: 'apple_pay', label: 'Apple Pay', hint: 'Opcional — dispositivos Apple' },
    { key: 'google_pay', label: 'Google Pay', hint: 'Opcional' },
    { key: 'paypal', label: 'PayPal', hint: 'Opcional' },
];

function optionsFor(method) {
    const list = props.gateways_by_method?.[method] ?? [];
    return [
        { value: '', label: 'Desativado' },
        { value: '__default__', label: 'Automático (primeiro gateway conectado)' },
        ...list.map((g) => ({ value: g.slug, label: g.name })),
    ];
}

const hasAnyConnected = computed(() =>
    Object.values(props.gateways_by_method || {}).some((list) => Array.isArray(list) && list.length > 0)
);

function submit() {
    saving.value = true;
    router.put(
        '/configuracoes/pagamento',
        { settings: JSON.parse(JSON.stringify(form)) },
        {
            preserveScroll: true,
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="mx-auto max-w-3xl space-y-6">
        <Head title="Pagamento" />

        <div>
            <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                <CreditCard class="h-6 w-6" />
                Pagamento da loja
            </h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Escolha quais métodos aparecem no checkout do carrinho e qual gateway processa cada um.
            </p>
        </div>

        <div
            v-if="!hasAnyConnected"
            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800/50 dark:bg-amber-950/30 dark:text-amber-200"
        >
            Nenhum gateway conectado.
            <Link href="/integracoes?tab=gateways" class="ml-1 font-medium underline">
                Conectar em Integrações
            </Link>
            antes de ativar os métodos.
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="panel-table space-y-5 p-6">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Métodos no checkout</h2>
                    <Link
                        href="/integracoes?tab=gateways"
                        class="inline-flex items-center gap-1 text-xs font-medium text-[var(--color-primary)] hover:underline"
                    >
                        Gerenciar gateways
                        <ExternalLink class="h-3.5 w-3.5" />
                    </Link>
                </div>

                <div
                    v-for="m in methods"
                    :key="m.key"
                    class="grid gap-2 border-b border-zinc-100 pb-5 last:border-0 last:pb-0 dark:border-zinc-800 sm:grid-cols-[180px_1fr] sm:items-start sm:gap-4"
                >
                    <div>
                        <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ m.label }}</p>
                        <p class="text-xs text-zinc-500">{{ m.hint }}</p>
                    </div>
                    <GatewaySelect
                        v-model="form.payment_gateways[m.key]"
                        :options="optionsFor(m.key)"
                        :label="m.label"
                        placeholder="Selecione o gateway"
                    />
                </div>
            </section>

            <div class="flex justify-end">
                <Button type="submit" :disabled="saving">
                    <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                    <Save v-else class="h-4 w-4" />
                    Salvar
                </Button>
            </div>
        </form>
    </div>
</template>
