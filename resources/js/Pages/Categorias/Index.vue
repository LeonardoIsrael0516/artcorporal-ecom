<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import Toggle from '@/components/ui/Toggle.vue';
import { Pencil, Plus, Trash2, X } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    categories: { type: Array, default: () => [] },
});

const showModal = ref(false);
const editing = ref(null);
const saving = ref(false);
const imagePreview = ref(null);
const imageInputRef = ref(null);

const emptyForm = () => ({
    name: '',
    slug: '',
    parent_id: null,
    position: 0,
    is_active: true,
    is_featured_circle: false,
    show_in_menu: true,
    description: '',
    image: null,
});

const form = ref(emptyForm());

const parentOptions = computed(() => {
    const editId = editing.value?.id;
    return (props.categories || []).filter((c) => c.id !== editId);
});

function openCreate() {
    editing.value = null;
    form.value = emptyForm();
    imagePreview.value = null;
    showModal.value = true;
}

function openEdit(cat) {
    editing.value = cat;
    form.value = {
        name: cat.name || '',
        slug: cat.slug || '',
        parent_id: cat.parent_id ?? null,
        position: cat.position ?? 0,
        is_active: !!cat.is_active,
        is_featured_circle: !!cat.is_featured_circle,
        show_in_menu: !!cat.show_in_menu,
        description: cat.description || '',
        image: null,
    };
    imagePreview.value = cat.image_url || null;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editing.value = null;
    form.value = emptyForm();
    imagePreview.value = null;
    if (imageInputRef.value) imageInputRef.value.value = '';
}

function onImageChange(event) {
    const file = event.target.files?.[0] || null;
    form.value.image = file;
    if (imagePreview.value && imagePreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(imagePreview.value);
    }
    imagePreview.value = file ? URL.createObjectURL(file) : editing.value?.image_url || null;
}

function buildFormData() {
    const fd = new FormData();
    const f = form.value;
    fd.append('name', f.name ?? '');
    fd.append('slug', f.slug ?? '');
    if (f.parent_id != null && f.parent_id !== '') {
        fd.append('parent_id', String(f.parent_id));
    }
    fd.append('position', String(f.position ?? 0));
    fd.append('is_active', f.is_active ? '1' : '0');
    fd.append('is_featured_circle', f.is_featured_circle ? '1' : '0');
    fd.append('show_in_menu', f.show_in_menu ? '1' : '0');
    fd.append('description', f.description ?? '');
    if (f.image instanceof File) {
        fd.append('image', f.image);
    }
    return fd;
}

function submit() {
    if (!form.value.name?.trim()) return;
    saving.value = true;
    const fd = buildFormData();

    if (editing.value) {
        fd.append('_method', 'put');
        router.post(`/categorias/${editing.value.id}`, fd, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onFinish: () => {
                saving.value = false;
            },
        });
        return;
    }

    router.post('/categorias', fd, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onFinish: () => {
            saving.value = false;
        },
    });
}

function destroy(cat) {
    if (!confirm(`Remover a categoria "${cat.name}"?`)) return;
    router.delete(`/categorias/${cat.id}`, { preserveScroll: true });
}

function yesNo(v) {
    return v ? 'Sim' : 'Não';
}
</script>

<template>
    <div class="mx-auto max-w-6xl space-y-6">
        <Head title="Categorias" />

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                    Categorias
                </h1>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    Organize o menu, círculos da home e hierarquia da loja.
                </p>
            </div>
            <Button type="button" @click="openCreate">
                <Plus class="h-4 w-4" />
                Nova categoria
            </Button>
        </div>

        <div class="panel-table overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Categoria</th>
                            <th class="px-4 py-3 font-semibold">Posição</th>
                            <th class="px-4 py-3 font-semibold">Ativa</th>
                            <th class="px-4 py-3 font-semibold">Menu</th>
                            <th class="px-4 py-3 font-semibold">Círculo</th>
                            <th class="px-4 py-3 font-semibold">Produtos</th>
                            <th class="px-4 py-3 font-semibold text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <tr
                            v-for="cat in categories"
                            :key="cat.id"
                            class="bg-white dark:bg-zinc-900/40"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="cat.image_url"
                                        :src="cat.image_url"
                                        :alt="cat.name"
                                        class="h-10 w-10 rounded-full object-cover ring-1 ring-zinc-200 dark:ring-zinc-700"
                                    />
                                    <div
                                        v-else
                                        class="flex h-10 w-10 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold text-zinc-500 dark:bg-zinc-800"
                                    >
                                        {{ (cat.name || '?').slice(0, 1).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-zinc-900 dark:text-white">
                                            {{ cat.name }}
                                        </p>
                                        <p class="truncate text-xs text-zinc-500">
                                            <span v-if="cat.parent_name">{{ cat.parent_name }} / </span>
                                            {{ cat.slug }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ cat.position }}</td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ yesNo(cat.is_active) }}</td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ yesNo(cat.show_in_menu) }}</td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ yesNo(cat.is_featured_circle) }}</td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ cat.products_count ?? 0 }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                        @click="openEdit(cat)"
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                        Editar
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40"
                                        @click="destroy(cat)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                        Excluir
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!categories?.length">
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-zinc-500">
                                Nenhuma categoria cadastrada ainda.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="closeModal"
        >
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                        {{ editing ? 'Editar categoria' : 'Nova categoria' }}
                    </h2>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                        aria-label="Fechar"
                        @click="closeModal"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <form class="space-y-4 p-5" @submit.prevent="submit">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Nome</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                            placeholder="Ex.: Piercings"
                        />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Slug</label>
                        <input
                            v-model="form.slug"
                            type="text"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                            placeholder="Opcional — gerado automaticamente"
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Categoria pai</label>
                            <select
                                v-model="form.parent_id"
                                class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                            >
                                <option :value="null">Sem pai</option>
                                <option v-for="c in parentOptions" :key="c.id" :value="c.id">
                                    {{ c.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Posição</label>
                            <input
                                v-model.number="form.position"
                                type="number"
                                min="0"
                                class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Descrição</label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                            placeholder="Texto opcional da categoria"
                        />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Imagem</label>
                        <div class="flex items-center gap-4">
                            <img
                                v-if="imagePreview"
                                :src="imagePreview"
                                alt="Prévia"
                                class="h-14 w-14 rounded-full object-cover ring-1 ring-zinc-200"
                            />
                            <input
                                ref="imageInputRef"
                                type="file"
                                accept="image/*"
                                class="block w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-900 file:px-3 file:py-2 file:text-xs file:font-medium file:text-white"
                                @change="onImageChange"
                            />
                        </div>
                    </div>

                    <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <Toggle v-model="form.is_active" label="Ativa" />
                        <Toggle v-model="form.is_featured_circle" label="Destacar em círculos na home" />
                        <Toggle v-model="form.show_in_menu" label="Exibir no menu" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" @click="closeModal">Cancelar</Button>
                        <Button type="submit" :disabled="saving">
                            {{ saving ? 'Salvando…' : 'Salvar' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
