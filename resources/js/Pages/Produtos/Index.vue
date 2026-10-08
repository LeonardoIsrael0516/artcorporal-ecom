<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import ProdutosTabs from '@/components/produtos/ProdutosTabs.vue';
import ProdutoCreateSidebar from '@/components/produtos/ProdutoCreateSidebar.vue';
import PluginRuntimeMount from '@/components/plugins/PluginRuntimeMount.vue';
import PluginRenderZone from '@/components/plugins/PluginRenderZone.vue';
import {
    MoreVertical,
    Pencil,
    Copy,
    Trash2,
    Package,
    ExternalLink,
    Download,
    Upload,
    Search,
    X,
} from 'lucide-vue-next';
import ProductPackageModal from '@/components/produtos/ProductPackageModal.vue';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    produtos: { type: [Array, Object], default: () => [] },
    productTypes: { type: Array, default: () => [] },
    billingTypes: { type: Array, default: () => [] },
    exchange_rates: { type: Object, default: () => ({ brl_eur: 0.16, brl_usd: 0.18 }) },
    plugin_card_actions: { type: [Object, Array], default: () => [] },
    plugin_form_sections: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ category_id: null, q: '' }) },
});

const produtosList = computed(() => props.produtos?.data ?? (Array.isArray(props.produtos) ? props.produtos : []));

const sidebarOpen = ref(false);
const openMenuId = ref(null);
const productToDelete = ref(null);
const packageModalOpen = ref(false);
const packageModalMode = ref('import');
const packageProduct = ref(null);
const savingIds = ref(new Set());
const drafts = ref({});

const searchQ = ref(props.filters?.q ?? '');
const categoryId = ref(props.filters?.category_id ? String(props.filters.category_id) : '');

watch(
    () => props.filters,
    (f) => {
        searchQ.value = f?.q ?? '';
        categoryId.value = f?.category_id ? String(f.category_id) : '';
    },
    { deep: true },
);

watch(
    () => produtosList.value,
    (list) => {
        const next = { ...drafts.value };
        for (const p of list) {
            if (!next[p.id]) {
                next[p.id] = {
                    name: p.name ?? '',
                    price: formatPriceInput(p.price),
                };
            } else {
                // Keep draft if currently focused/saving; otherwise sync from server
                if (!savingIds.value.has(p.id) && document.activeElement?.dataset?.productField !== String(p.id)) {
                    next[p.id] = {
                        name: p.name ?? '',
                        price: formatPriceInput(p.price),
                    };
                }
            }
        }
        drafts.value = next;
    },
    { immediate: true },
);

function formatPriceInput(value) {
    const n = Number(value ?? 0);
    if (Number.isNaN(n)) return '0,00';
    return n.toFixed(2).replace('.', ',');
}

function parsePriceInput(raw) {
    if (raw == null || raw === '') return null;
    const cleaned = String(raw).trim().replace(/\./g, '').replace(',', '.');
    const n = Number(cleaned);
    return Number.isFinite(n) ? n : null;
}

function applyFilters(overrides = {}) {
    const q = (overrides.q !== undefined ? overrides.q : searchQ.value).trim();
    const cat = overrides.category_id !== undefined ? overrides.category_id : categoryId.value;
    const params = {};
    if (q) params.q = q;
    if (cat) params.category_id = cat;

    router.get('/produtos', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

let searchTimer = null;
function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 350);
}

function onCategoryChange() {
    applyFilters();
}

function clearFilters() {
    searchQ.value = '';
    categoryId.value = '';
    applyFilters({ q: '', category_id: '' });
}

const hasActiveFilters = computed(() => !!(searchQ.value || categoryId.value));

function openImportModal() {
    packageModalMode.value = 'import';
    packageProduct.value = null;
    packageModalOpen.value = true;
}

function openExportModal(p) {
    closeMenu();
    packageModalMode.value = 'export';
    packageProduct.value = p;
    packageModalOpen.value = true;
}

function openSidebar() {
    sidebarOpen.value = true;
}

function closeSidebar() {
    sidebarOpen.value = false;
}

function toggleMenu(id) {
    openMenuId.value = openMenuId.value === id ? null : id;
}

function closeMenu() {
    openMenuId.value = null;
}

function handleClickOutside(event) {
    if (openMenuId.value == null) return;
    const menuEl = document.querySelector(`[data-product-menu="${openMenuId.value}"]`);
    if (menuEl && !menuEl.contains(event.target)) {
        closeMenu();
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    clearTimeout(searchTimer);
});

function markSaving(id, on) {
    const next = new Set(savingIds.value);
    if (on) next.add(id);
    else next.delete(id);
    savingIds.value = next;
}

function quickUpdate(p, payload) {
    markSaving(p.id, true);
    router.patch(`/produtos/${p.id}/quick`, payload, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => markSaving(p.id, false),
    });
}

function saveName(p) {
    const draft = drafts.value[p.id];
    if (!draft) return;
    const name = String(draft.name ?? '').trim();
    if (!name || name === p.name) {
        drafts.value[p.id] = { ...draft, name: p.name ?? '' };
        return;
    }
    quickUpdate(p, { name });
}

function savePrice(p) {
    const draft = drafts.value[p.id];
    if (!draft) return;
    const price = parsePriceInput(draft.price);
    if (price === null || price < 0) {
        drafts.value[p.id] = { ...draft, price: formatPriceInput(p.price) };
        return;
    }
    if (Math.abs(price - Number(p.price ?? 0)) < 0.001) {
        drafts.value[p.id] = { ...draft, price: formatPriceInput(p.price) };
        return;
    }
    quickUpdate(p, { price });
}

function toggleActive(p) {
    quickUpdate(p, { is_active: !p.is_active });
}

function duplicate(p) {
    router.post(`/produtos/${p.id}/duplicate`, {}, { preserveScroll: true });
    closeMenu();
}

function openDeleteModal(p) {
    closeMenu();
    productToDelete.value = p;
}

function closeDeleteModal() {
    productToDelete.value = null;
}

function confirmDestroy() {
    const p = productToDelete.value;
    if (!p) return;
    router.delete(`/produtos/${p.id}`, { preserveScroll: true });
    closeDeleteModal();
}

function pluginActions(productId) {
    const raw = props.plugin_card_actions;
    if (Array.isArray(raw)) {
        return raw.filter((a) => !a?.product_id || String(a.product_id) === String(productId));
    }
    return raw?.[productId] ?? raw?.[String(productId)] ?? [];
}

function categoryNames(p) {
    const cats = p.categories ?? [];
    if (!cats.length) return '—';
    return cats.map((c) => c.name).join(', ');
}
</script>

<template>
    <div class="space-y-6">
        <ProdutosTabs />
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                <div class="relative min-w-0 flex-1 sm:max-w-xs">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                    <input
                        v-model="searchQ"
                        type="search"
                        placeholder="Buscar por nome ou SKU…"
                        class="w-full rounded-lg border border-zinc-200 bg-white py-2 pl-9 pr-3 text-sm text-zinc-900 outline-none transition focus:border-zinc-400 dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                        @input="onSearchInput"
                        @keydown.enter.prevent="applyFilters()"
                    />
                </div>
                <select
                    v-model="categoryId"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none transition focus:border-zinc-400 sm:w-56 dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                    @change="onCategoryChange"
                >
                    <option value="">Todas as categorias</option>
                    <option v-for="c in categories" :key="c.id" :value="String(c.id)">
                        {{ c.name }}
                    </option>
                </select>
                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg px-2 py-2 text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200"
                    @click="clearFilters"
                >
                    <X class="h-4 w-4" />
                    Limpar
                </button>
            </div>
            <div class="flex justify-end gap-2">
                <Button variant="outline" @click="openImportModal">
                    <Upload class="h-4 w-4" />
                    Importar
                </Button>
                <Button @click="openSidebar">
                    Novo produto
                </Button>
            </div>
        </div>
        <PluginRenderZone zone="produtos.index.after_toolbar" />

        <div v-if="produtosList.length" class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-400">
                        <tr>
                            <th class="px-3 py-3 font-medium">Produto</th>
                            <th class="hidden px-3 py-3 font-medium md:table-cell">Categoria</th>
                            <th class="px-3 py-3 font-medium">Preço</th>
                            <th class="hidden px-3 py-3 font-medium sm:table-cell">Estoque</th>
                            <th class="px-3 py-3 font-medium">Status</th>
                            <th class="px-3 py-3 font-medium text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <tr
                            v-for="p in produtosList"
                            :key="p.id"
                            class="group transition hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40"
                            :class="{ 'opacity-60': savingIds.has(p.id) }"
                        >
                            <td class="px-3 py-2.5">
                                <div class="flex items-center gap-3">
                                    <Link
                                        :href="`/produtos/${p.id}/edit`"
                                        class="relative h-11 w-11 shrink-0 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-600"
                                    >
                                        <img
                                            v-if="p.image_url"
                                            :src="p.image_url"
                                            :alt="p.name"
                                            class="absolute inset-0 h-full w-full object-cover"
                                        />
                                        <div
                                            v-else
                                            class="flex h-full w-full items-center justify-center bg-zinc-100 text-zinc-400 dark:bg-zinc-700/50"
                                        >
                                            <Package class="h-5 w-5" />
                                        </div>
                                    </Link>
                                    <div class="min-w-0 flex-1">
                                        <input
                                            v-if="drafts[p.id]"
                                            v-model="drafts[p.id].name"
                                            type="text"
                                            :data-product-field="p.id"
                                            class="w-full min-w-[10rem] rounded-md border border-transparent bg-transparent px-1.5 py-1 font-medium text-zinc-900 outline-none transition hover:border-zinc-200 focus:border-zinc-300 focus:bg-white dark:text-white dark:hover:border-zinc-600 dark:focus:border-zinc-500 dark:focus:bg-zinc-900"
                                            @blur="saveName(p)"
                                            @keydown.enter.prevent="($event.target.blur())"
                                        />
                                        <p class="mt-0.5 truncate px-1.5 text-xs text-zinc-400 md:hidden">
                                            {{ categoryNames(p) }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden max-w-[12rem] px-3 py-2.5 text-zinc-600 dark:text-zinc-400 md:table-cell">
                                <span class="line-clamp-2">{{ categoryNames(p) }}</span>
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="relative w-[7.5rem]">
                                    <span class="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-xs text-zinc-400">R$</span>
                                    <input
                                        v-if="drafts[p.id]"
                                        v-model="drafts[p.id].price"
                                        type="text"
                                        inputmode="decimal"
                                        :data-product-field="p.id"
                                        class="w-full rounded-md border border-transparent bg-transparent py-1 pl-7 pr-1.5 font-semibold text-zinc-900 outline-none transition hover:border-zinc-200 focus:border-zinc-300 focus:bg-white dark:text-white dark:hover:border-zinc-600 dark:focus:border-zinc-500 dark:focus:bg-zinc-900"
                                        @blur="savePrice(p)"
                                        @keydown.enter.prevent="($event.target.blur())"
                                    />
                                </div>
                            </td>
                            <td class="hidden px-3 py-2.5 text-zinc-600 dark:text-zinc-400 sm:table-cell">
                                {{ p.track_stock ? p.stock : '—' }}
                            </td>
                            <td class="px-3 py-2.5">
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium transition"
                                    :class="p.is_active
                                        ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300'
                                        : 'bg-zinc-100 text-zinc-500 hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-400'"
                                    :title="p.is_active ? 'Clique para desativar' : 'Clique para ativar'"
                                    @click="toggleActive(p)"
                                >
                                    {{ p.is_active ? 'Ativo' : 'Inativo' }}
                                </button>
                            </td>
                            <td class="px-3 py-2.5 text-right">
                                <div class="relative inline-flex" :data-product-menu="p.id">
                                    <Link
                                        :href="`/produtos/${p.id}/edit`"
                                        class="mr-1 inline-flex h-8 w-8 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                                        title="Editar completo"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                    <button
                                        type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                                        aria-label="Abrir menu"
                                        @click="toggleMenu(p.id)"
                                    >
                                        <MoreVertical class="h-4 w-4" />
                                    </button>
                                    <div
                                        v-show="openMenuId === p.id"
                                        class="absolute right-0 top-full z-50 mt-1 w-48 rounded-xl border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
                                    >
                                        <Link
                                            :href="`/produtos/${p.id}/edit`"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                            @click="closeMenu"
                                        >
                                            <Pencil class="h-4 w-4 shrink-0" />
                                            Editar completo
                                        </Link>
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                            @click="duplicate(p)"
                                        >
                                            <Copy class="h-4 w-4 shrink-0" />
                                            Duplicar
                                        </button>
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                            @click="openExportModal(p)"
                                        >
                                            <Download class="h-4 w-4 shrink-0" />
                                            Exportar
                                        </button>
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                            @click="openDeleteModal(p)"
                                        >
                                            <Trash2 class="h-4 w-4 shrink-0" />
                                            Excluir
                                        </button>
                                        <template v-for="(action, actIdx) in pluginActions(p.id)" :key="`plugin-${p.id}-${actIdx}`">
                                            <div
                                                v-if="action.ui_mode === 'runtime'"
                                                class="px-3 py-2"
                                                @click="closeMenu"
                                            >
                                                <PluginRuntimeMount :item="action" :context="{ product: p }" />
                                            </div>
                                            <a
                                                v-else-if="action.href"
                                                :href="action.href"
                                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                                @click="closeMenu"
                                            >
                                                <ExternalLink v-if="!action.icon" class="h-4 w-4 shrink-0" />
                                                <component v-else :is="action.icon" class="h-4 w-4 shrink-0" />
                                                {{ action.label }}
                                            </a>
                                            <span v-else class="block border-t border-zinc-100 px-3 py-1 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                                                {{ action.label }}
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <nav
            v-if="produtos?.links?.length > 3"
            class="flex items-center justify-center gap-2"
            aria-label="Paginação"
        >
            <a
                v-for="link in produtos.links"
                :key="link.label"
                :href="link.url"
                :aria-current="link.active ? 'page' : undefined"
                :aria-disabled="!link.url"
                :class="[
                    'relative inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium transition',
                    link.active
                        ? 'z-10 bg-[var(--color-primary)] text-white'
                        : link.url
                          ? 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700'
                          : 'cursor-not-allowed text-zinc-400 dark:text-zinc-500',
                ]"
                v-html="link.label"
                @click.prevent="link.url && router.visit(link.url, { preserveState: true })"
            />
        </nav>

        <div
            v-if="!produtosList.length"
            class="panel-card-dashed flex flex-col items-center justify-center py-16"
        >
            <Package class="h-14 w-14 text-zinc-400 dark:text-zinc-500" />
            <p class="mt-3 text-zinc-600 dark:text-zinc-400">
                {{ hasActiveFilters ? 'Nenhum produto encontrado com esses filtros.' : 'Nenhum produto ainda.' }}
            </p>
            <Button v-if="!hasActiveFilters" class="mt-4" @click="openSidebar">
                Criar primeiro produto
            </Button>
            <Button v-else variant="outline" class="mt-4" @click="clearFilters">
                Limpar filtros
            </Button>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="productToDelete"
            class="fixed inset-0 z-[100002] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-modal-title"
        >
            <div
                class="fixed inset-0 bg-zinc-900/60 dark:bg-zinc-950/70"
                aria-hidden="true"
                @click="closeDeleteModal"
            />
            <div
                class="relative w-full max-w-sm rounded-xl border border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-700 dark:bg-zinc-800"
            >
                <h2 id="delete-modal-title" class="text-lg font-semibold text-zinc-900 dark:text-white">
                    Excluir produto?
                </h2>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    Tem certeza que deseja excluir
                    <strong class="text-zinc-900 dark:text-white">"{{ productToDelete?.name }}"</strong>?
                    Esta ação não pode ser desfeita.
                </p>
                <div class="mt-5 flex gap-3 justify-end">
                    <Button variant="outline" @click="closeDeleteModal">
                        Cancelar
                    </Button>
                    <Button variant="destructive" @click="confirmDestroy">
                        Excluir
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>

    <ProductPackageModal
        :open="packageModalOpen"
        :mode="packageModalMode"
        :product="packageProduct"
        @update:open="packageModalOpen = $event"
    />

    <ProdutoCreateSidebar
        :open="sidebarOpen"
        :product-types="productTypes"
        :billing-types="billingTypes"
        :exchange-rates="exchange_rates"
        :plugin-form-sections="plugin_form_sections"
        @close="closeSidebar"
        @success="closeSidebar"
    />
</template>
