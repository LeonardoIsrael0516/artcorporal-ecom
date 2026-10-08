<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import ProductShelf from '@/components/storefront/sections/ProductShelf.vue';

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    related: {
        type: Array,
        default: () => [],
    },
});

const qty = ref(1);
const activeImage = ref(0);
const cep = ref('');
const shippingLoading = ref(false);
const shippingQuotes = ref([]);
const shippingError = ref('');
const buyLoading = ref(false);
const buyError = ref('');

const images = computed(() => {
    if (Array.isArray(props.product.gallery) && props.product.gallery.length) {
        return props.product.gallery;
    }
    if (props.product.image_url) return [props.product.image_url];
    return [];
});

const price = computed(() => formatMoney(props.product.price));
const pixPrice = computed(() =>
    props.product.pix_price != null ? formatMoney(props.product.pix_price) : null,
);

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function onlyDigits(v) {
    return String(v || '').replace(/\D/g, '');
}

async function quoteShipping() {
    shippingError.value = '';
    shippingQuotes.value = [];
    const digits = onlyDigits(cep.value);
    if (digits.length !== 8) {
        shippingError.value = 'Informe um CEP válido com 8 dígitos.';
        return;
    }

    shippingLoading.value = true;
    try {
        const { data } = await axios.post('/api/storefront/shipping/quote', {
            cep: digits,
            product_id: props.product.id,
            quantity: qty.value,
        });
        shippingQuotes.value = data?.quotes || data?.options || data || [];
        if (!shippingQuotes.value.length) {
            shippingError.value = 'Nenhuma opção de frete encontrada para este CEP.';
        }
    } catch (e) {
        shippingError.value =
            e?.response?.data?.message || 'Não foi possível calcular o frete. Tente novamente.';
    } finally {
        shippingLoading.value = false;
    }
}

async function addToCart() {
    buyError.value = '';
    buyLoading.value = true;
    try {
        await axios.post('/carrinho/add', {
            product_id: props.product.id,
            quantity: qty.value,
        });
        window.location.href = '/carrinho';
    } catch (e) {
        buyError.value = e?.response?.data?.message || 'Erro ao adicionar ao carrinho.';
    } finally {
        buyLoading.value = false;
    }
}
</script>

<template>
    <StorefrontLayout>
        <Head :title="product.name" />

        <div class="mx-auto max-w-[1360px] px-4 py-10 md:px-8">
            <nav class="mb-6 text-xs text-zinc-500">
                <Link href="/" class="hover:text-[var(--sf-primary)]">Início</Link>
                <span class="mx-2">/</span>
                <Link
                    v-if="product.category"
                    :href="`/categoria/${product.category.slug}`"
                    class="hover:text-[var(--sf-primary)]"
                >
                    {{ product.category.name }}
                </Link>
                <span v-if="product.category" class="mx-2">/</span>
                <span class="text-[var(--sf-text)]">{{ product.name }}</span>
            </nav>

            <div class="grid gap-10 lg:grid-cols-2">
                <!-- Gallery -->
                <div>
                    <div class="aspect-square overflow-hidden bg-white ring-1 ring-[var(--sf-primary)]/20">
                        <img
                            v-if="images[activeImage]"
                            :src="images[activeImage]"
                            :alt="product.name"
                            class="h-full w-full object-cover"
                        />
                        <div
                            v-else
                            class="sf-hero-placeholder relative flex h-full items-center justify-center"
                        >
                            <span class="sf-wordmark sf-gold-text relative text-xl">{{ product.name }}</span>
                        </div>
                    </div>
                    <div v-if="images.length > 1" class="mt-3 flex gap-2 overflow-x-auto">
                        <button
                            v-for="(img, i) in images"
                            :key="i"
                            type="button"
                            class="h-16 w-16 shrink-0 overflow-hidden border-2"
                            :class="i === activeImage ? 'border-[var(--sf-primary)]' : 'border-transparent'"
                            @click="activeImage = i"
                        >
                            <img :src="img" :alt="`${product.name} ${i + 1}`" class="h-full w-full object-cover" />
                        </button>
                    </div>
                </div>

                <!-- Info -->
                <div>
                    <p class="sf-eyebrow">{{ product.category?.name || 'Body Piercing' }}</p>
                    <h1 class="mt-2 text-4xl leading-tight text-[var(--sf-secondary)]">{{ product.name }}</h1>
                    <div class="sf-ornament !ml-0 mt-4 w-24" />

                    <p class="sf-serif mt-6 text-3xl text-[var(--sf-secondary)]">{{ price }}</p>
                    <p v-if="pixPrice" class="mt-1 text-sm font-semibold text-[var(--sf-primary)]">
                        {{ pixPrice }} no Pix
                    </p>
                    <p v-if="product.installment_text" class="mt-1 text-sm text-[var(--sf-text)]/60">
                        {{ product.installment_text }}
                    </p>

                    <div class="mt-6 flex items-center gap-3">
                        <label class="text-[11px] font-semibold tracking-[0.2em] uppercase">Qtd.</label>
                        <input
                            v-model.number="qty"
                            type="number"
                            min="1"
                            class="w-20 border border-[var(--sf-primary)]/40 bg-white px-3 py-2 text-sm outline-none focus:border-[var(--sf-secondary)]"
                        />
                    </div>

                    <button
                        type="button"
                        class="sf-btn-gold mt-6 w-full px-6 py-4 text-xs font-semibold tracking-[0.25em] uppercase disabled:opacity-60"
                        :disabled="buyLoading"
                        @click="addToCart"
                    >
                        {{ buyLoading ? 'Adicionando...' : 'Comprar' }}
                    </button>
                    <p v-if="buyError" class="mt-2 text-sm text-red-600">{{ buyError }}</p>

                    <!-- Shipping -->
                    <div class="mt-8 border border-[var(--sf-primary)]/25 bg-white/60 p-5">
                        <p class="text-[11px] font-semibold tracking-[0.2em] uppercase text-[var(--sf-secondary)]">Calcular frete</p>
                        <form class="mt-3 flex gap-2" @submit.prevent="quoteShipping">
                            <input
                                v-model="cep"
                                type="text"
                                maxlength="9"
                                placeholder="00000-000"
                                class="flex-1 border border-[var(--sf-primary)]/40 bg-white px-3 py-2 text-sm outline-none focus:border-[var(--sf-secondary)]"
                            />
                            <button
                                type="submit"
                                class="sf-btn px-5 py-2 text-[11px] font-semibold tracking-[0.2em] uppercase disabled:opacity-60"
                                :disabled="shippingLoading"
                            >
                                {{ shippingLoading ? '...' : 'OK' }}
                            </button>
                        </form>
                        <p v-if="shippingError" class="mt-2 text-xs text-red-600">{{ shippingError }}</p>
                        <ul v-if="shippingQuotes.length" class="mt-3 space-y-2">
                            <li
                                v-for="(q, i) in shippingQuotes"
                                :key="i"
                                class="flex justify-between text-sm"
                            >
                                <span>{{ q.name || q.service || 'Frete' }}</span>
                                <span class="font-medium">
                                    {{ formatMoney(q.price ?? q.cost ?? 0) }}
                                    <span v-if="q.days || q.delivery_days" class="font-normal text-zinc-500">
                                        · {{ q.days || q.delivery_days }} dias
                                    </span>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div v-if="product.description || product.description_blocks?.length" class="mt-14">
                <h2 class="mb-4 text-3xl text-[var(--sf-secondary)]">Descrição</h2>
                <div
                    v-if="product.description_blocks?.length"
                    class="space-y-6 text-sm leading-relaxed text-zinc-700"
                >
                    <div v-for="(block, i) in product.description_blocks" :key="i">
                        <h3 v-if="block.title" class="mb-2 font-semibold text-[var(--sf-text)]">
                            {{ block.title }}
                        </h3>
                        <div v-if="block.html" v-html="block.html" />
                        <p v-else-if="block.text" class="whitespace-pre-line">{{ block.text }}</p>
                    </div>
                </div>
                <div
                    v-else
                    class="prose prose-sm max-w-none text-zinc-700"
                    v-html="product.description"
                />
            </div>
        </div>

        <ProductShelf
            v-if="related.length"
            title="Você também pode gostar"
            :products="related"
        />
    </StorefrontLayout>
</template>
