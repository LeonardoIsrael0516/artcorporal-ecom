<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import CheckoutSteps from '@/components/storefront/checkout/CheckoutSteps.vue';
import OrderSummary from '@/components/storefront/checkout/OrderSummary.vue';

const props = defineProps({
    cart: {
        type: Object,
        default: () => ({ lines: [], subtotal: 0, shipping_quote: null, shipping_address: null }),
    },
    customer: { type: Object, default: null },
    saved_address: { type: Object, default: null },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user || null);

const addr = props.cart?.shipping_address || {};
const savedCustomer = addr.customer || {};
const saved = props.saved_address || {};

function splitName(full) {
    const parts = String(full || '').trim().split(/\s+/);
    return {
        first: parts[0] || '',
        last: parts.slice(1).join(' ') || '',
    };
}

const prefillName = splitName(
    props.customer?.name
    || savedCustomer.name
    || addr.recipient_name
    || authUser.value?.name
    || ''
);

const form = useForm({
    email: props.customer?.email || savedCustomer.email || addr.email || authUser.value?.email || '',
    marketing_opt_in: true,
    first_name: props.customer?.first_name || prefillName.first,
    last_name: props.customer?.last_name || prefillName.last,
    phone: props.customer?.phone || savedCustomer.phone || addr.phone || saved.phone || '',
    cpf: savedCustomer.cpf || addr.cpf || '',
    cep: formatCep(addr.cep || saved.cep || ''),
    street: addr.street || saved.street || '',
    number: addr.number === 'S/N' ? '' : (addr.number || saved.number || ''),
    no_number: !!(addr.no_number || addr.number === 'S/N'),
    complement: addr.complement || saved.complement || '',
    district: addr.district || saved.district || '',
    city: addr.city || saved.city || '',
    state: addr.state || saved.state || '',
    quote: props.cart?.shipping_quote || null,
    notes: addr.notes || '',
});

const shippingLoading = ref(false);
const shippingQuotes = ref([]);
const shippingError = ref('');
const cepLoading = ref(false);
const cepError = ref('');
const stage = ref(initialStage());

const lines = computed(() => props.cart?.lines || []);
const subtotal = computed(() => Number(props.cart?.subtotal ?? 0));
const shippingCost = computed(() =>
    form.quote ? Number(form.quote.price ?? 0) : null
);

const emailOk = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(form.email || '').trim()));
const cepDigits = computed(() => String(form.cep || '').replace(/\D/g, ''));
const cepOk = computed(() => cepDigits.value.length === 8);
const hasQuote = computed(() => !!form.quote?.id);

function initialStage() {
    if (props.cart?.shipping_quote && addr.street) return 'address';
    if (props.cart?.shipping_quote || (addr.cep && String(addr.cep).replace(/\D/g, '').length === 8)) return 'shipping';
    return 'contact';
}

function formatCep(value) {
    const d = String(value || '').replace(/\D/g, '').slice(0, 8);
    if (d.length <= 5) return d;
    return `${d.slice(0, 5)}-${d.slice(5)}`;
}

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatPhone(value) {
    const d = String(value || '').replace(/\D/g, '').slice(0, 11);
    if (d.length <= 2) return d;
    if (d.length <= 7) return `(${d.slice(0, 2)}) ${d.slice(2)}`;
    if (d.length <= 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
    return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
}

function formatDoc(value) {
    const d = String(value || '').replace(/\D/g, '').slice(0, 14);
    if (d.length <= 11) {
        return d
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    }
    return d
        .replace(/^(\d{2})(\d)/, '$1.$2')
        .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1/$2')
        .replace(/(\d{4})(\d)/, '$1-$2');
}

watch(() => form.cep, (v) => {
    form.cep = formatCep(v);
});
watch(() => form.phone, (v) => {
    form.phone = formatPhone(v);
});
watch(() => form.cpf, (v) => {
    form.cpf = formatDoc(v);
});
watch(() => form.no_number, (v) => {
    if (v) form.number = '';
});

async function lookupCep() {
    cepError.value = '';
    if (!cepOk.value) {
        cepError.value = 'Informe um CEP válido.';
        return;
    }
    cepLoading.value = true;
    try {
        const res = await fetch(`https://viacep.com.br/ws/${cepDigits.value}/json/`);
        const data = await res.json();
        if (data?.erro) {
            cepError.value = 'CEP não encontrado.';
            return;
        }
        form.street = data.logradouro || form.street;
        form.district = data.bairro || form.district;
        form.city = data.localidade || form.city;
        form.state = data.uf || form.state;
        await quoteShipping();
        stage.value = 'shipping';
    } catch (_) {
        cepError.value = 'Não foi possível consultar o CEP.';
    } finally {
        cepLoading.value = false;
    }
}

async function quoteShipping() {
    shippingError.value = '';
    if (!cepOk.value) {
        shippingError.value = 'Informe um CEP válido.';
        return;
    }
    shippingLoading.value = true;
    try {
        const { data } = await axios.post('/api/storefront/shipping/quote', {
            cep: cepDigits.value,
            items: lines.value.map((l) => ({
                product_id: l.product_id,
                quantity: l.quantity,
            })),
        });
        shippingQuotes.value = data?.quotes || [];
        if (!shippingQuotes.value.length) {
            shippingError.value = 'Nenhuma opção de frete para este CEP.';
            form.quote = null;
        } else if (form.quote?.id) {
            const still = shippingQuotes.value.find((q) => q.id === form.quote.id);
            form.quote = still || null;
        }
    } catch (e) {
        shippingError.value = e?.response?.data?.message || 'Erro ao calcular frete.';
        shippingQuotes.value = [];
    } finally {
        shippingLoading.value = false;
    }
}

async function selectQuote(q) {
    form.quote = q;
    try {
        await axios.post('/carrinho/shipping', {
            quote: q,
            cep: cepDigits.value,
            address: {
                street: form.street,
                number: form.no_number ? 'S/N' : form.number,
                complement: form.complement,
                district: form.district,
                city: form.city,
                state: form.state,
                no_number: form.no_number,
            },
        });
    } catch (_) {}
    stage.value = 'address';
}

function continueFromContact() {
    if (!emailOk.value) return;
    stage.value = 'shipping';
    if (cepOk.value && !shippingQuotes.value.length) {
        lookupCep();
    }
}

function changeCep() {
    form.quote = null;
    shippingQuotes.value = [];
    stage.value = 'shipping';
}

function submit() {
    if (!hasQuote.value) {
        shippingError.value = 'Selecione uma opção de frete.';
        stage.value = 'shipping';
        return;
    }
    form.post('/checkout/entrega', { preserveScroll: true });
}

const fieldClass =
    'w-full border border-[var(--sf-primary)]/25 bg-white px-4 py-3 text-sm text-[var(--sf-text)] outline-none transition placeholder:text-[var(--sf-text)]/35 focus:border-[var(--sf-primary)]';

const addressLine = computed(() => {
    const parts = [form.street, form.district, form.city, form.state].filter(Boolean);
    return parts.join(', ');
});
</script>

<template>
    <StorefrontLayout>
        <Head title="Entrega" />

        <div class="mx-auto max-w-[1100px] px-4 py-8 md:px-8 md:py-12">
            <CheckoutSteps current="entrega" />

            <div
                v-if="page.props.flash?.error"
                class="mb-6 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                role="alert"
            >
                {{ page.props.flash.error }}
            </div>

            <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start">
                <div class="space-y-8">
                    <!-- Contato -->
                    <section class="space-y-4">
                        <div class="flex items-end justify-between gap-3">
                            <h2 class="font-[family-name:var(--sf-font-heading)] text-2xl text-[var(--sf-secondary)]">
                                Dados de contato
                            </h2>
                            <Link
                                v-if="!authUser"
                                href="/conta/entrar"
                                class="text-xs text-[var(--sf-primary)] hover:underline"
                            >
                                Já tenho conta
                            </Link>
                            <p v-else class="text-xs text-[var(--sf-text)]/50">
                                Olá, {{ authUser.name?.split(' ')[0] }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[var(--sf-text)]/55">
                                E-mail
                            </label>
                            <input
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                required
                                :class="fieldClass"
                                placeholder="seu@email.com"
                                :readonly="!!authUser"
                            />
                            <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                        </div>
                        <label class="flex items-start gap-2 text-sm text-[var(--sf-text)]/70">
                            <input v-model="form.marketing_opt_in" type="checkbox" class="mt-1 accent-[var(--sf-primary)]" />
                            Receber ofertas e novidades por e-mail
                        </label>
                        <button
                            v-if="stage === 'contact'"
                            type="button"
                            class="sf-btn-gold px-8 py-3.5 text-xs font-semibold tracking-[0.2em] uppercase disabled:opacity-50"
                            :disabled="!emailOk"
                            @click="continueFromContact"
                        >
                            Continuar
                        </button>
                    </section>

                    <!-- Entrega / CEP + frete -->
                    <section v-if="stage !== 'contact'" class="space-y-4">
                        <h2 class="font-[family-name:var(--sf-font-heading)] text-2xl text-[var(--sf-secondary)]">
                            Entrega
                        </h2>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[var(--sf-text)]/55">
                                    País
                                </label>
                                <input :class="fieldClass" value="Brasil" disabled />
                            </div>
                            <div>
                                <div class="mb-1.5 flex items-center justify-between">
                                    <label class="text-xs font-medium uppercase tracking-wide text-[var(--sf-text)]/55">
                                        CEP
                                    </label>
                                    <a
                                        href="https://buscacepinter.correios.com.br/app/endereco/index.php"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-xs text-[var(--sf-primary)] hover:underline"
                                    >
                                        Não sei meu CEP
                                    </a>
                                </div>
                                <div class="flex gap-2">
                                    <input
                                        v-model="form.cep"
                                        type="text"
                                        inputmode="numeric"
                                        maxlength="9"
                                        :class="fieldClass"
                                        placeholder="00000-000"
                                        @keydown.enter.prevent="lookupCep"
                                    />
                                    <button
                                        type="button"
                                        class="sf-btn shrink-0 px-4 py-2 text-[11px] font-semibold tracking-[0.15em] uppercase disabled:opacity-50"
                                        :disabled="cepLoading || !cepOk"
                                        @click="lookupCep"
                                    >
                                        {{ cepLoading ? '...' : 'OK' }}
                                    </button>
                                </div>
                                <p v-if="cepError || form.errors.cep" class="mt-1 text-xs text-red-600">
                                    {{ cepError || form.errors.cep }}
                                </p>
                            </div>
                        </div>

                        <div v-if="hasQuote && stage === 'address'" class="flex items-center justify-between border border-[var(--sf-primary)]/20 bg-white/70 px-4 py-3 text-sm">
                            <div>
                                <p class="font-medium">{{ form.quote.name }}</p>
                                <p class="text-xs text-[var(--sf-text)]/50">
                                    {{ formatMoney(form.quote.price) }}
                                    <span v-if="form.quote.days"> · {{ form.quote.days }} dias úteis</span>
                                </p>
                            </div>
                            <button type="button" class="text-xs text-[var(--sf-primary)] hover:underline" @click="changeCep">
                                Alterar
                            </button>
                        </div>

                        <div v-if="stage === 'shipping' || (stage === 'address' && !hasQuote)" class="space-y-2">
                            <p v-if="shippingLoading" class="text-sm text-[var(--sf-text)]/50">Calculando frete…</p>
                            <p v-if="shippingError || form.errors.quote" class="text-xs text-red-600">
                                {{ shippingError || form.errors.quote }}
                            </p>
                            <button
                                v-for="(q, i) in shippingQuotes"
                                :key="q.id || i"
                                type="button"
                                class="flex w-full items-center gap-3 border px-4 py-3 text-left text-sm transition"
                                :class="form.quote?.id === q.id
                                    ? 'border-[var(--sf-secondary)] bg-[var(--sf-secondary)]/5'
                                    : 'border-[var(--sf-primary)]/20 bg-white/70 hover:border-[var(--sf-primary)]/50'"
                                @click="selectQuote(q)"
                            >
                                <span
                                    class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border"
                                    :class="form.quote?.id === q.id ? 'border-[var(--sf-secondary)]' : 'border-zinc-300'"
                                >
                                    <span
                                        v-if="form.quote?.id === q.id"
                                        class="h-2 w-2 rounded-full bg-[var(--sf-secondary)]"
                                    />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="font-medium">{{ q.name }}</span>
                                    <span v-if="q.days" class="block text-xs text-[var(--sf-text)]/45">
                                        Até {{ q.days }} dias úteis
                                    </span>
                                </span>
                                <span class="font-semibold">{{ formatMoney(q.price) }}</span>
                            </button>
                        </div>
                    </section>

                    <!-- Endereço completo -->
                    <section v-if="stage === 'address' && hasQuote" class="space-y-4">
                        <h2 class="font-[family-name:var(--sf-font-heading)] text-2xl text-[var(--sf-secondary)]">
                            Dados para entrega
                        </h2>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[var(--sf-text)]/55">Nome</label>
                                <input v-model="form.first_name" type="text" autocomplete="given-name" required :class="fieldClass" />
                                <p v-if="form.errors.first_name" class="mt-1 text-xs text-red-600">{{ form.errors.first_name }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[var(--sf-text)]/55">Sobrenome</label>
                                <input v-model="form.last_name" type="text" autocomplete="family-name" required :class="fieldClass" />
                                <p v-if="form.errors.last_name" class="mt-1 text-xs text-red-600">{{ form.errors.last_name }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[var(--sf-text)]/55">
                                Telefone com DDD
                            </label>
                            <input v-model="form.phone" type="tel" autocomplete="tel" required :class="fieldClass" placeholder="(00) 00000-0000" />
                            <p v-if="form.errors.phone" class="mt-1 text-xs text-red-600">{{ form.errors.phone }}</p>
                        </div>

                        <div v-if="addressLine" class="flex items-start justify-between gap-3 border border-[var(--sf-primary)]/20 bg-white/70 px-4 py-3 text-sm">
                            <p class="text-[var(--sf-text)]/80">{{ addressLine }}</p>
                            <button type="button" class="shrink-0 text-xs text-[var(--sf-primary)] hover:underline" @click="changeCep">
                                Alterar
                            </button>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[var(--sf-text)]/55">Número</label>
                                <input
                                    v-model="form.number"
                                    type="text"
                                    :disabled="form.no_number"
                                    :class="fieldClass"
                                    placeholder="Nº"
                                />
                                <label class="mt-2 flex items-center gap-2 text-xs text-[var(--sf-text)]/60">
                                    <input v-model="form.no_number" type="checkbox" class="accent-[var(--sf-primary)]" />
                                    Sem número
                                </label>
                                <p v-if="form.errors.number" class="mt-1 text-xs text-red-600">{{ form.errors.number }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[var(--sf-text)]/55">
                                    Complemento <span class="normal-case tracking-normal opacity-60">(opcional)</span>
                                </label>
                                <input v-model="form.complement" type="text" :class="fieldClass" placeholder="Apto, bloco, referência…" />
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="sm:col-span-2">
                                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[var(--sf-text)]/55">Bairro</label>
                                <input v-model="form.district" type="text" required :class="fieldClass" />
                                <p v-if="form.errors.district" class="mt-1 text-xs text-red-600">{{ form.errors.district }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[var(--sf-text)]/55">UF</label>
                                <input v-model="form.state" type="text" maxlength="2" required :class="[fieldClass, 'uppercase']" />
                                <p v-if="form.errors.state" class="mt-1 text-xs text-red-600">{{ form.errors.state }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[var(--sf-text)]/55">Cidade</label>
                            <input v-model="form.city" type="text" required :class="fieldClass" />
                            <p v-if="form.errors.city" class="mt-1 text-xs text-red-600">{{ form.errors.city }}</p>
                        </div>

                        <div>
                            <h3 class="mb-3 font-[family-name:var(--sf-font-heading)] text-xl text-[var(--sf-secondary)]">
                                Dados para nota fiscal
                            </h3>
                            <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-[var(--sf-text)]/55">
                                CPF ou CNPJ
                            </label>
                            <input v-model="form.cpf" type="text" inputmode="numeric" required :class="fieldClass" placeholder="000.000.000-00" />
                            <p v-if="form.errors.cpf" class="mt-1 text-xs text-red-600">{{ form.errors.cpf }}</p>
                        </div>

                        <button
                            type="button"
                            class="sf-btn-gold w-full px-6 py-4 text-xs font-semibold tracking-[0.25em] uppercase disabled:opacity-60 sm:w-auto sm:min-w-[280px]"
                            :disabled="form.processing"
                            @click="submit"
                        >
                            {{ form.processing ? 'Salvando…' : 'Continuar para pagamento' }}
                        </button>
                    </section>
                </div>

                <div class="lg:sticky lg:top-6">
                    <OrderSummary
                        :lines="lines"
                        :subtotal="subtotal"
                        :shipping-cost="shippingCost"
                        :shipping-label="form.quote?.name"
                    />
                </div>
            </div>
        </div>
    </StorefrontLayout>
</template>
