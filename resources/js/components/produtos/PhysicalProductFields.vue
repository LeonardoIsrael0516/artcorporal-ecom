<script setup>
import { computed } from 'vue';
import Toggle from '@/components/ui/Toggle.vue';

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
