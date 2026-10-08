<script setup>
import { reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import Toggle from '@/components/ui/Toggle.vue';
import { Loader2, Save, Truck } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    settings: { type: Object, required: true },
});

const saving = ref(false);

const form = reactive({
    origin_cep: props.settings?.origin_cep ?? '',
    origin_cnpj: props.settings?.origin_cnpj ?? '',
    fallback_weight_g: props.settings?.fallback_weight_g ?? 100,
    fallback_height_cm: props.settings?.fallback_height_cm ?? 2,
    fallback_width_cm: props.settings?.fallback_width_cm ?? 10,
    fallback_length_cm: props.settings?.fallback_length_cm ?? 15,
    markup_percent: props.settings?.markup_percent ?? 0,
    extra_days: props.settings?.extra_days ?? 0,
    free_shipping: {
        enabled: !!props.settings?.free_shipping?.enabled,
        min_amount: props.settings?.free_shipping?.min_amount ?? 199,
    },
    melhor_envio: {
        enabled: !!props.settings?.melhor_envio?.enabled,
        sandbox: props.settings?.melhor_envio?.sandbox !== false,
        token: props.settings?.melhor_envio?.token ?? '',
        services: Array.isArray(props.settings?.melhor_envio?.services)
            ? [...props.settings.melhor_envio.services]
            : [],
    },
    frenet: {
        enabled: !!props.settings?.frenet?.enabled,
        token: props.settings?.frenet?.token ?? '',
        seller_cep: props.settings?.frenet?.seller_cep ?? '',
    },
});

const inputClass =
    'mt-1.5 block w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white';

function submit() {
    saving.value = true;
    router.put(
        '/configuracoes/frete',
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
        <Head title="Frete" />

        <div>
            <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                <Truck class="h-6 w-6" />
                Configurações de frete
            </h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Integrações Melhor Envio e Frenet, origem e regras de cálculo.
            </p>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="panel-table space-y-4 p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Origem e regras</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">CEP de origem</label>
                        <input v-model="form.origin_cep" type="text" maxlength="9" :class="inputClass" placeholder="00000-000" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">CNPJ de origem</label>
                        <input v-model="form.origin_cnpj" type="text" :class="inputClass" placeholder="Opcional" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Markup (%)</label>
                        <input v-model.number="form.markup_percent" type="number" step="0.01" min="0" :class="inputClass" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Dias extras</label>
                        <input v-model.number="form.extra_days" type="number" min="0" :class="inputClass" />
                    </div>
                </div>
            </section>

            <section class="panel-table space-y-4 p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Frete grátis</h2>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            Quando o subtotal do carrinho atingir o valor mínimo, as cotações ficam R$&nbsp;0.
                        </p>
                    </div>
                    <Toggle v-model="form.free_shipping.enabled" label="Ativo" />
                </div>
                <div>
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Valor mínimo (R$)</label>
                    <input
                        v-model.number="form.free_shipping.min_amount"
                        type="number"
                        step="0.01"
                        min="0"
                        :disabled="!form.free_shipping.enabled"
                        :class="inputClass"
                        placeholder="199"
                    />
                    <p class="mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                        A faixa do topo da loja usa
                        <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">&#123;&#123;free_shipping_min&#125;&#125;</code>
                        para exibir este valor automaticamente.
                    </p>
                </div>
            </section>

            <section class="panel-table space-y-4 p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Fallbacks (quando o produto não tem medidas)</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Peso (g)</label>
                        <input v-model.number="form.fallback_weight_g" type="number" min="1" :class="inputClass" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Altura (cm)</label>
                        <input v-model.number="form.fallback_height_cm" type="number" step="0.01" min="0" :class="inputClass" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Largura (cm)</label>
                        <input v-model.number="form.fallback_width_cm" type="number" step="0.01" min="0" :class="inputClass" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Comprimento (cm)</label>
                        <input v-model.number="form.fallback_length_cm" type="number" step="0.01" min="0" :class="inputClass" />
                    </div>
                </div>
            </section>

            <section class="panel-table space-y-4 p-6">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Melhor Envio</h2>
                    <Toggle v-model="form.melhor_envio.enabled" label="Ativo" />
                </div>
                <Toggle v-model="form.melhor_envio.sandbox" label="Sandbox / homologação" />
                <div>
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Token</label>
                    <input
                        v-model="form.melhor_envio.token"
                        type="password"
                        autocomplete="off"
                        :class="inputClass"
                        placeholder="Token da API Melhor Envio"
                    />
                </div>
            </section>

            <section class="panel-table space-y-4 p-6">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Frenet</h2>
                    <Toggle v-model="form.frenet.enabled" label="Ativo" />
                </div>
                <div>
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Token</label>
                    <input
                        v-model="form.frenet.token"
                        type="password"
                        autocomplete="off"
                        :class="inputClass"
                        placeholder="Token Frenet"
                    />
                </div>
                <div>
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">CEP do seller (Frenet)</label>
                    <input v-model="form.frenet.seller_cep" type="text" maxlength="9" :class="inputClass" placeholder="00000-000" />
                </div>
            </section>

            <div class="flex justify-end">
                <Button type="submit" :disabled="saving">
                    <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                    <Save v-else class="h-4 w-4" />
                    {{ saving ? 'Salvando…' : 'Salvar frete' }}
                </Button>
            </div>
        </form>
    </div>
</template>
