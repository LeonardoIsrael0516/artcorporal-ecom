<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import Toggle from '@/components/ui/Toggle.vue';
import { FileText, Pencil, Plus, X } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    pages: { type: Array, default: () => [] },
});

const showModal = ref(false);
const editing = ref(null);
const saving = ref(false);

const emptyForm = () => ({
    title: '',
    slug: '',
    body: '',
    is_published: true,
});

const form = ref(emptyForm());

function openCreate() {
    editing.value = null;
    form.value = emptyForm();
    showModal.value = true;
}

function openEdit(page) {
    editing.value = page;
    form.value = {
        title: page.title || '',
        slug: page.slug || '',
        body: page.body || '',
        is_published: page.is_published !== false,
    };
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editing.value = null;
    form.value = emptyForm();
}

function submit() {
    if (!form.value.title?.trim()) return;
    saving.value = true;
    const payload = {
        title: form.value.title,
        slug: form.value.slug || null,
        body: form.value.body || '',
        is_published: !!form.value.is_published,
    };

    if (editing.value) {
        router.put(`/paginas-loja/${editing.value.id}`, payload, {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onFinish: () => {
                saving.value = false;
            },
        });
        return;
    }

    router.post('/paginas-loja', payload, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onFinish: () => {
            saving.value = false;
        },
    });
}
</script>

<template>
    <div class="mx-auto max-w-5xl space-y-6">
        <Head title="Páginas da loja" />

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                    <FileText class="h-6 w-6" />
                    Páginas da loja
                </h1>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    Conteúdos institucionais (quem somos, trocas, termos, etc.).
                </p>
            </div>
            <Button type="button" @click="openCreate">
                <Plus class="h-4 w-4" />
                Nova página
            </Button>
        </div>

        <div class="panel-table overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Título</th>
                        <th class="px-4 py-3 font-semibold">Slug</th>
                        <th class="px-4 py-3 font-semibold">Publicada</th>
                        <th class="px-4 py-3 font-semibold text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    <tr
                        v-for="page in pages"
                        :key="page.id"
                        class="bg-white dark:bg-zinc-900/40"
                    >
                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ page.title }}</td>
                        <td class="px-4 py-3 text-zinc-500">/pagina/{{ page.slug }}</td>
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                            {{ page.is_published ? 'Sim' : 'Não' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                @click="openEdit(page)"
                            >
                                <Pencil class="h-3.5 w-3.5" />
                                Editar
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!pages?.length">
                        <td colspan="4" class="px-4 py-10 text-center text-sm text-zinc-500">
                            Nenhuma página criada ainda.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="closeModal"
        >
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                        {{ editing ? 'Editar página' : 'Nova página' }}
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
                        <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Título</label>
                        <input
                            v-model="form.title"
                            type="text"
                            required
                            class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Slug</label>
                        <input
                            v-model="form.slug"
                            type="text"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                            placeholder="Opcional"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Conteúdo</label>
                        <textarea
                            v-model="form.body"
                            rows="12"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 font-mono text-sm outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                            placeholder="HTML ou texto da página"
                        />
                    </div>
                    <Toggle v-model="form.is_published" label="Publicada" />
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
