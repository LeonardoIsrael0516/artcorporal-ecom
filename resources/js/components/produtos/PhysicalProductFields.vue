<script setup>
import { computed } from 'vue';
import Toggle from '@/components/ui/Toggle.vue';
import { Plus, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    form: { type: Object, default: null },
    modelValue: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const state = computed(() => props.form || props.modelValue || {});

function setField(key, value) {
    if (props.form) {
        props.form[key] = value;
        return;
    }
    emit('update:modelValue', { ...(props.modelValue || {}), [key]: value });
}

const tiers = computed(() => {
    const raw = state.value.wholesale_tiers;
    return Array.isArray(raw) ? raw : [];
});

function formatMoney(v) {
    const n = Number(v);
    if (!Number.isFinite(n)) return 'R$ 0,00';
    return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function addTier() {
    const current = [...tiers.value];
    const lastMin = current.length ? Number(current[current.length - 1].min_qty) || 10 : 10;
    current.push({
        min_qty: lastMin + (current.length ? 10 : 0),
        unit_price: Math.max(0, Number(state.value.price || 0) * 0.85),
    });
    if (!current[0].min_qty || current[0].min_qty < 2) {
        current[0].min_qty = 10;
    }
    setField('wholesale_tiers', current);
    if (!state.value.wholesale_enabled) {
        setField('wholesale_enabled', true);
    }
}

function updateTier(index, key, value) {
    const current = tiers.value.map((t, i) => {
        if (i !== index) return { ...t };
        return { ...t, [key]: value };
    });
    setField('wholesale_tiers', current);
}

function removeTier(index) {
    const current = tiers.value.filter((_, i) => i !== index);
    setField('wholesale_tiers', current);
    if (current.length === 0) {
        setField('wholesale_enabled', false);
        setField('wholesale_only', false);
        setField('wholesale_hide_from_retail', false);
    }
}

function onWholesaleToggle(enabled) {
    setField('wholesale_enabled', enabled);
    if (enabled && tiers.value.length === 0) {
        setField('wholesale_tiers', [{ min_qty: 10, unit_price: Math.max(0, Number(state.value.price || 0) * 0.85) }]);
    }
    if (!enabled) {
        setField('wholesale_only', false);
        setField('wholesale_hide_from_retail', false);
    }
}

const selectedCategoryIds = computed(() => {
    const raw = state.value.category_ids ?? [];
    if (!Array.isArray(raw)) return [];
    return raw.map((c) => (typeof c === 'object' ? c.id : c));
});

function isCategorySelected(id) {
    return selectedCategoryIds.value.some((x) => String(x) === String(id));
}

function toggleCategory(id) {
    const current = [...selectedCategoryIds.value];
    const idx = current.findIndex((x) => String(x) === String(id));
    if (idx >= 0) current.splice(idx, 1);
    else current.push(id);
    setField('category_ids', current);
}

/** Lista plana com indentação: pais e filhos selecionáveis. */
const categoryOptions = computed(() => {
    const list = Array.isArray(props.categories) ? props.categories : [];
    const byParent = new Map();
    for (const cat of list) {
        const pid = cat.parent_id == null || cat.parent_id === '' ? 'root' : String(cat.parent_id);
        if (!byParent.has(pid)) byParent.set(pid, []);
        byParent.get(pid).push(cat);
    }
    for (const group of byParent.values()) {
        group.sort((a, b) => {
            const pa = Number(a.position ?? 0);
            const pb = Number(b.position ?? 0);
            if (pa !== pb) return pa - pb;
            return String(a.name || '').localeCompare(String(b.name || ''), 'pt-BR');
        });
    }

    const out = [];
    function walk(parentKey, depth) {
        const kids = byParent.get(parentKey) || [];
        for (const cat of kids) {
            out.push({
                id: cat.id,
                name: cat.name,
                depth,
                label: depth > 0 ? `${'— '.repeat(depth)}${cat.name}` : cat.name,
            });
            walk(String(cat.id), depth + 1);
        }
    }
    walk('root', 0);

    // Categorias órfãs (parent ausente na lista) ainda aparecem
    const listed = new Set(out.map((c) => String(c.id)));
    for (const cat of list) {
        if (!listed.has(String(cat.id))) {
            out.push({
                id: cat.id,
                name: cat.name,
                depth: 0,
                label: cat.name,
            });
        }
    }
    return out;
});

const inputClass =
    'mt-1.5 block w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white';
</script>

<template>
    <div class="space-y-5 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900/40">
        <div>
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Produto físico</h3>
            <p class="mt-1 text-xs text-zinc-500">SKU, estoque, dimensões, preço e categorias.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">SKU</label>
                <input
                    :value="state.sku ?? ''"
                    type="text"
                    :class="inputClass"
                    placeholder="SKU interno"
                    @input="setField('sku', $event.target.value)"
                />
            </div>
            <div>
                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Estoque</label>
                <input
                    :value="state.stock ?? 0"
                    type="number"
                    min="0"
                    :class="inputClass"
                    @input="setField('stock', Number($event.target.value) || 0)"
                />
            </div>
            <div>
                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Preço de comparação</label>
                <input
                    :value="state.compare_at_price ?? ''"
                    type="number"
                    step="0.01"
                    min="0"
                    :class="inputClass"
                    placeholder="De R$"
                    @input="setField('compare_at_price', $event.target.value === '' ? null : Number($event.target.value))"
                />
            </div>
            <div>
                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Desconto Pix (%)</label>
                <input
                    :value="state.pix_discount_percent ?? state.pix_discount ?? 0"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    :class="inputClass"
                    @input="setField('pix_discount_percent', Number($event.target.value) || 0)"
                />
            </div>
        </div>

        <Toggle
            :model-value="!!state.track_stock"
            label="Controlar estoque"
            @update:model-value="setField('track_stock', $event)"
        />

        <div>
            <p class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Dimensões para frete</p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="text-xs text-zinc-500">Peso (g)</label>
                    <input
                        :value="state.weight_g ?? ''"
                        type="number"
                        min="0"
                        :class="inputClass"
                        @input="setField('weight_g', Number($event.target.value) || 0)"
                    />
                </div>
                <div>
                    <label class="text-xs text-zinc-500">Altura (cm)</label>
                    <input
                        :value="state.height_cm ?? ''"
                        type="number"
                        step="0.01"
                        min="0"
                        :class="inputClass"
                        @input="setField('height_cm', Number($event.target.value) || 0)"
                    />
                </div>
                <div>
                    <label class="text-xs text-zinc-500">Largura (cm)</label>
                    <input
                        :value="state.width_cm ?? ''"
                        type="number"
                        step="0.01"
                        min="0"
                        :class="inputClass"
                        @input="setField('width_cm', Number($event.target.value) || 0)"
                    />
                </div>
                <div>
                    <label class="text-xs text-zinc-500">Comprimento (cm)</label>
                    <input
                        :value="state.length_cm ?? ''"
                        type="number"
                        step="0.01"
                        min="0"
                        :class="inputClass"
                        @input="setField('length_cm', Number($event.target.value) || 0)"
                    />
                </div>
            </div>
        </div>

        <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div>
                <h4 class="text-sm font-semibold text-zinc-900 dark:text-white">Atacado</h4>
                <p class="mt-1 text-xs text-zinc-500">
                    Defina faixas de quantidade com preço unitário menor. O preço cai conforme o cliente aumenta as peças.
                </p>
            </div>

            <Toggle
                :model-value="!!state.wholesale_enabled"
                label="Vender no atacado"
                @update:model-value="onWholesaleToggle"
            />

            <template v-if="state.wholesale_enabled">
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                            <tr>
                                <th class="px-3 py-2 font-semibold">A partir de (un.)</th>
                                <th class="px-3 py-2 font-semibold">Preço unitário</th>
                                <th class="px-3 py-2 font-semibold text-right"> </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            <tr v-for="(tier, idx) in tiers" :key="idx">
                                <td class="px-3 py-2">
                                    <input
                                        :value="tier.min_qty"
                                        type="number"
                                        min="2"
                                        :class="inputClass"
                                        class="!mt-0"
                                        @input="updateTier(idx, 'min_qty', Number($event.target.value) || 2)"
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        :value="tier.unit_price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        :class="inputClass"
                                        class="!mt-0"
                                        @input="updateTier(idx, 'unit_price', Number($event.target.value) || 0)"
                                    />
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40"
                                        @click="removeTier(idx)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                        Remover
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-300 px-3 py-2 text-xs font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-300 dark:hover:bg-zinc-800"
                    @click="addTier"
                >
                    <Plus class="h-3.5 w-3.5" />
                    Adicionar faixa
                </button>

                <ul v-if="tiers.length" class="space-y-1 text-xs text-zinc-500">
                    <li v-for="(tier, idx) in tiers" :key="`preview-${idx}`">
                        A partir de {{ tier.min_qty }} un.: {{ formatMoney(tier.unit_price) }}
                    </li>
                </ul>

                <div class="space-y-1">
                    <Toggle
                        :model-value="!!state.wholesale_only"
                        label="Somente atacado"
                        @update:model-value="setField('wholesale_only', $event)"
                    />
                    <p class="pl-14 text-xs text-zinc-500">
                        Compra só a partir da quantidade mínima da primeira faixa.
                    </p>
                </div>
                <div class="space-y-1">
                    <Toggle
                        :model-value="!!state.wholesale_hide_from_retail"
                        label="Ocultar do varejo"
                        @update:model-value="setField('wholesale_hide_from_retail', $event)"
                    />
                    <p class="pl-14 text-xs text-zinc-500">
                        Só aparece em categorias de atacado e na página /atacado.
                    </p>
                </div>
            </template>
        </div>

        <div>
            <p class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Categorias</p>
            <p class="mb-2 text-xs text-zinc-500">Pode marcar categorias pai e filhas.</p>
            <div
                v-if="categoryOptions.length"
                class="max-h-56 space-y-1 overflow-y-auto rounded-xl border border-zinc-200 p-2 dark:border-zinc-700"
            >
                <label
                    v-for="cat in categoryOptions"
                    :key="cat.id"
                    class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-2 text-sm transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60"
                    :style="{ paddingLeft: `${0.5 + cat.depth * 1.1}rem` }"
                >
                    <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-zinc-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                        :checked="isCategorySelected(cat.id)"
                        @change="toggleCategory(cat.id)"
                    />
                    <span
                        class="min-w-0 flex-1"
                        :class="cat.depth > 0 ? 'text-zinc-700 dark:text-zinc-300' : 'font-medium text-zinc-900 dark:text-white'"
                    >
                        {{ cat.name }}
                    </span>
                    <span
                        v-if="cat.depth > 0"
                        class="shrink-0 text-[10px] uppercase tracking-wide text-zinc-400"
                    >
                        sub
                    </span>
                </label>
            </div>
            <p v-else class="rounded-xl border border-dashed border-zinc-200 px-3 py-4 text-center text-xs text-zinc-500 dark:border-zinc-700">
                Nenhuma categoria cadastrada.
                <a href="/categorias" class="text-[var(--color-primary)] hover:underline">Criar categorias</a>
            </p>
        </div>
    </div>
</template>
