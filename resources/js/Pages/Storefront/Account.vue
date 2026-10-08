<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

const props = defineProps({
    user: {
        type: Object,
        default: null,
    },
    orders: {
        type: Array,
        default: () => [],
    },
});

const STATUS_LABELS = {
    pending: 'Aguardando pagamento',
    completed: 'Pago',
    paid: 'Pago',
    cancelled: 'Cancelado',
    refunded: 'Reembolsado',
    shipped: 'Enviado',
};

const FULFILLMENT_LABELS = {
    preparing: 'Preparando envio',
    shipped: 'Enviado',
    delivered: 'Entregue',
    returned: 'Devolvido',
};

function fulfillmentLabel(order) {
    if (order?.fulfillment_status && FULFILLMENT_LABELS[order.fulfillment_status]) {
        return FULFILLMENT_LABELS[order.fulfillment_status];
    }
    if (order?.shipping_tracking) return 'Enviado';
    return STATUS_LABELS[order?.status] || order?.status || '—';
}

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatDate(value) {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleDateString('pt-BR');
    } catch (_) {
        return String(value);
    }
}

function logout() {
    router.post('/conta/sair');
}

const displayName = computed(() => props.user?.name || 'Cliente');
</script>

<template>
    <StorefrontLayout>
        <Head title="Minha Conta" />

        <div class="mx-auto max-w-4xl px-4 py-12 md:px-8">
            <div class="mb-10 flex flex-col items-center text-center">
                <p class="sf-eyebrow">Olá, {{ displayName }}</p>
                <h1 class="mt-2 text-4xl text-[var(--sf-secondary)] md:text-5xl">Minha Conta</h1>
                <div class="sf-ornament mt-4 w-28" />
            </div>

            <div v-if="!user" class="border border-[var(--sf-primary)]/25 bg-white/70 p-8 text-center">
                <p class="text-sm text-[var(--sf-text)]/70">Você precisa entrar para ver seus pedidos.</p>
                <Link
                    href="/conta/entrar"
                    class="sf-btn mt-5 px-8 py-3 text-[11px] font-semibold tracking-[0.2em] uppercase"
                >
                    Entrar
                </Link>
            </div>

            <template v-else>
                <section class="mb-10 border border-[var(--sf-primary)]/25 border-t-2 border-t-[var(--sf-primary)] bg-white/70 p-6">
                    <div class="mb-4 flex items-center justify-between gap-4">
                        <h2 class="text-2xl text-[var(--sf-secondary)]">Seus dados</h2>
                        <button
                            type="button"
                            class="sf-btn-outline px-5 py-2 text-[11px] font-semibold tracking-[0.2em] uppercase"
                            @click="logout"
                        >
                            Sair
                        </button>
                    </div>
                    <dl class="grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-[11px] tracking-[0.2em] uppercase text-[var(--sf-primary)]">Nome</dt>
                            <dd class="mt-1 font-medium">{{ user.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] tracking-[0.2em] uppercase text-[var(--sf-primary)]">E-mail</dt>
                            <dd class="mt-1 font-medium">{{ user.email }}</dd>
                        </div>
                    </dl>
                </section>

                <section>
                    <h2 class="mb-4 text-2xl text-[var(--sf-secondary)]">Pedidos</h2>

                    <div v-if="!orders.length" class="border border-dashed border-[var(--sf-primary)]/40 p-10 text-center text-sm text-[var(--sf-text)]/60">
                        Você ainda não possui pedidos.
                        <div class="mt-5">
                            <Link href="/loja" class="sf-btn px-8 py-3 text-[11px] font-semibold tracking-[0.2em] uppercase">
                                Ir para a loja
                            </Link>
                        </div>
                    </div>

                    <div v-else class="overflow-x-auto border border-[var(--sf-primary)]/25 bg-white/70">
                        <table class="w-full min-w-[560px] text-left text-sm">
                            <thead class="bg-[var(--sf-secondary)] text-[11px] uppercase tracking-[0.2em] text-[var(--sf-accent-light)]">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Pedido</th>
                                    <th class="px-4 py-3 font-medium">Data</th>
                                    <th class="px-4 py-3 font-medium">Status</th>
                                    <th class="px-4 py-3 font-medium">Rastreio</th>
                                    <th class="px-4 py-3 text-right font-medium">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="order in orders"
                                    :key="order.id"
                                    class="border-t border-[var(--sf-primary)]/15"
                                >
                                    <td class="px-4 py-3 font-medium">#{{ order.number || order.id }}</td>
                                    <td class="px-4 py-3 text-[var(--sf-text)]/70">{{ formatDate(order.created_at) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-block bg-[var(--sf-primary)]/12 px-2.5 py-1 text-xs text-[var(--sf-secondary)]">
                                            {{ fulfillmentLabel(order) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a
                                            v-if="order.shipping_tracking && order.tracking_url"
                                            :href="order.tracking_url"
                                            target="_blank"
                                            rel="noopener"
                                            class="font-mono text-xs text-[var(--sf-primary)] hover:underline"
                                        >
                                            {{ order.shipping_tracking }}
                                        </a>
                                        <span v-else-if="order.shipping_tracking" class="font-mono text-xs">
                                            {{ order.shipping_tracking }}
                                        </span>
                                        <span v-else class="text-xs text-[var(--sf-text)]/40">—</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium">
                                        {{ formatMoney(order.amount ?? order.total) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>
        </div>
    </StorefrontLayout>
</template>
