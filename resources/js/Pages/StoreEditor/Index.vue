<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import draggable from 'vuedraggable';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import Toggle from '@/components/ui/Toggle.vue';
import { postStoreThemePreview } from '@/lib/storeEditorPreview.js';
import {
    GripVertical,
    Loader2,
    Plus,
    Save,
    Trash2,
    Upload,
} from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    theme: { type: Object, required: true },
    sectionTypes: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
});

function deepClone(value) {
    return JSON.parse(JSON.stringify(value ?? {}));
}

function ensureThemeShape(theme) {
    const t = deepClone(theme);
    t.brand = {
        name: '',
        logo: '',
        logo_url: '',
        tagline: 'Body Piercing',
        primary: '#D4AF7F',
        accent_light: '#EAD6C6',
        secondary: '#0E2F24',
        bg: '#F3E8DC',
        text: '#0E2F24',
        header_bg: '#0E2F24',
        header_text: '#EAD6C6',
        footer_bg: '#0A241C',
        ...(t.brand || {}),
    };
    if (!t.brand.logo_url && t.brand.logo) t.brand.logo_url = t.brand.logo;
    t.announcement_bar = {
        enabled: true,
        text: '',
        link: '',
        bg: '#D4AF7F',
        color: '#0E2F24',
        ...(t.announcement_bar || {}),
    };
    t.footer = {
        whatsapp: '',
        columns: [],
        show_newsletter: true,
        copyright: '',
        ...(t.footer || {}),
    };
    if (!Array.isArray(t.sections)) t.sections = [];
    return t;
}

const draft = ref(ensureThemeShape(props.theme));
const selectedId = ref(draft.value.sections[0]?.id || null);
const iframeRef = ref(null);
const publishing = ref(false);
const uploading = ref(false);
const addMenuOpen = ref(false);
let previewTimer = null;

const selected = computed(() => draft.value.sections.find((s) => s.id === selectedId.value) || null);

const sectionLabelMap = computed(() => {
    const map = {};
    for (const t of props.sectionTypes || []) {
        map[t.type] = t.label;
    }
    return map;
});

function labelFor(type) {
    return sectionLabelMap.value[type] || type;
}

function uuid() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID();
    return `sec_${Date.now()}_${Math.random().toString(16).slice(2)}`;
}

function defaultProps(type) {
    switch (type) {
        case 'hero_slider':
            return {
                autoplay_ms: 4500,
                slides: [
                    {
                        image: '',
                        image_mobile: '',
                        title: 'Novidades',
                        subtitle: '',
                        cta: 'Ver produtos',
                        link: '/loja',
                    },
                ],
            };
        case 'category_circles':
            return { mode: 'featured', category_ids: [], title: '' };
        case 'product_shelf':
            return {
                title: 'Produtos',
                source: 'newest',
                category_id: null,
                product_ids: [],
                limit: 8,
            };
        case 'trust_bar':
            return {
                items: [
                    { icon: 'shield', title: 'Site seguro', text: 'Compra protegida' },
                    { icon: 'truck', title: 'Frete calculado', text: 'Entrega em todo o Brasil' },
                ],
            };
        case 'promo_split':
            return {
                title: 'Coleção em destaque',
                image: '',
                bg: '#10261D',
                color: '#F3E7D3',
                bullets: [],
                cta: 'Ver coleção',
                link: '/loja',
            };
        case 'instagram_grid':
            return { title: 'No Instagram', items: [] };
        case 'newsletter':
            return {
                title: 'Receba novidades',
                subtitle: 'Promoções e lançamentos no seu e-mail',
            };
        case 'rich_text':
            return { html: '<p></p>' };
        default:
            return {};
    }
}

function schedulePreview() {
    if (previewTimer) clearTimeout(previewTimer);
    previewTimer = setTimeout(() => {
        const win = iframeRef.value?.contentWindow;
        if (win) postStoreThemePreview(win, draft.value);
    }, 280);
}

watch(draft, () => schedulePreview(), { deep: true });

watch(
    () => props.theme,
    (next) => {
        draft.value = ensureThemeShape(next);
        if (!draft.value.sections.find((s) => s.id === selectedId.value)) {
            selectedId.value = draft.value.sections[0]?.id || null;
        }
    },
    { deep: true },
);

onBeforeUnmount(() => {
    if (previewTimer) clearTimeout(previewTimer);
});

function onIframeLoad() {
    schedulePreview();
}

function addSection(type) {
    const section = {
        id: uuid(),
        type,
        enabled: true,
        props: defaultProps(type),
    };
    if (!Array.isArray(draft.value.sections)) draft.value.sections = [];
    draft.value.sections.push(section);
    selectedId.value = section.id;
    addMenuOpen.value = false;
}

function removeSection(id) {
    draft.value.sections = draft.value.sections.filter((s) => s.id !== id);
    if (selectedId.value === id) {
        selectedId.value = draft.value.sections[0]?.id || null;
    }
}

function publish() {
    publishing.value = true;
    router.put(
        '/editor-loja',
        { theme: deepClone(draft.value) },
        {
            preserveScroll: true,
            onFinish: () => {
                publishing.value = false;
            },
        },
    );
}

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta?.content) return meta.content;
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function uploadFile(file) {
    const fd = new FormData();
    fd.append('file', file);
    const res = await fetch('/editor-loja/upload', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
            'X-XSRF-TOKEN': csrfToken(),
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: fd,
        credentials: 'same-origin',
    });
    if (!res.ok) throw new Error('Falha no upload');
    return res.json();
}

async function onLogoFile(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    uploading.value = true;
    try {
        const json = await uploadFile(file);
        if (json.url) {
            draft.value.brand.logo = json.url;
            draft.value.brand.logo_url = json.url;
        }
    } catch (_) {
        // noop
    } finally {
        uploading.value = false;
        event.target.value = '';
    }
}

async function onSlideImage(event, slide) {
    const file = event.target.files?.[0];
    if (!file) return;
    uploading.value = true;
    try {
        const json = await uploadFile(file);
        if (json.url) slide.image = json.url;
    } catch (_) {
        // noop
    } finally {
        uploading.value = false;
        event.target.value = '';
    }
}

async function onSlideMobileImage(event, slide) {
    const file = event.target.files?.[0];
    if (!file) return;
    uploading.value = true;
    try {
        const json = await uploadFile(file);
        if (json.url) slide.image_mobile = json.url;
    } catch (_) {
        // noop
    } finally {
        uploading.value = false;
        event.target.value = '';
    }
}

async function onPromoImage(event) {
    const file = event.target.files?.[0];
    if (!file || !selected.value) return;
    uploading.value = true;
    try {
        const json = await uploadFile(file);
        if (json.url) selected.value.props.image = json.url;
    } catch (_) {
        // noop
    } finally {
        uploading.value = false;
        event.target.value = '';
    }
}

async function onInstagramImage(event, item) {
    const file = event.target.files?.[0];
    if (!file || !item) return;
    uploading.value = true;
    try {
        const json = await uploadFile(file);
        if (json.url) item.image = json.url;
    } catch (_) {
        // noop
    } finally {
        uploading.value = false;
        event.target.value = '';
    }
}

function ensureSelectedProps() {
    if (!selected.value) return;
    if (!selected.value.props || typeof selected.value.props !== 'object') {
        selected.value.props = defaultProps(selected.value.type);
    }
    if (selected.value.type === 'hero_slider') {
        if (!Array.isArray(selected.value.props.slides)) selected.value.props.slides = [];
        if (!selected.value.props.autoplay_ms) selected.value.props.autoplay_ms = 4500;
        selected.value.props.slides.forEach((slide) => {
            if (slide && slide.image_mobile === undefined) slide.image_mobile = '';
        });
    }
}

watch(selected, () => ensureSelectedProps(), { immediate: true });

function addHeroSlide() {
    ensureSelectedProps();
    if (!Array.isArray(selected.value.props.slides)) selected.value.props.slides = [];
    selected.value.props.slides.push({
        image: '',
        image_mobile: '',
        title: '',
        subtitle: '',
        cta: 'Ver',
        link: '/loja',
    });
}

function removeHeroSlide(idx) {
    selected.value.props.slides.splice(idx, 1);
}

function addTrustItem() {
    ensureSelectedProps();
    if (!Array.isArray(selected.value.props.items)) selected.value.props.items = [];
    selected.value.props.items.push({ icon: 'shield', title: '', text: '' });
}

function removeTrustItem(idx) {
    selected.value.props.items.splice(idx, 1);
}

function addInstagramItem() {
    ensureSelectedProps();
    if (!Array.isArray(selected.value.props.items)) selected.value.props.items = [];
    selected.value.props.items.push({ image: '', link: '', caption: '' });
}

function removeInstagramItem(idx) {
    selected.value.props.items.splice(idx, 1);
}

function bulletsText(propsObj) {
    return Array.isArray(propsObj.bullets) ? propsObj.bullets.join('\n') : '';
}

function setBullets(propsObj, text) {
    propsObj.bullets = String(text || '')
        .split('\n')
        .map((l) => l.trim())
        .filter(Boolean);
}

function toggleCategoryId(id) {
    ensureSelectedProps();
    const list = Array.isArray(selected.value.props.category_ids)
        ? [...selected.value.props.category_ids]
        : [];
    const idx = list.findIndex((x) => String(x) === String(id));
    if (idx >= 0) list.splice(idx, 1);
    else list.push(id);
    selected.value.props.category_ids = list;
}

const inputClass =
    'w-full rounded-lg border border-zinc-300 bg-white px-2.5 py-2 text-sm outline-none focus:border-zinc-900 focus:ring-1 focus:ring-zinc-900/20';
const labelClass = 'mb-1 block text-[11px] font-semibold uppercase tracking-wide text-zinc-500';
</script>

<template>
    <div class="-mx-4 -mb-8 flex h-[calc(100vh-4.5rem)] flex-col bg-zinc-100 dark:bg-zinc-950 sm:-mx-6 lg:-mx-8">
        <Head title="Editor da Loja" />

        <header class="flex shrink-0 items-center justify-between gap-3 border-b border-zinc-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900">
            <div>
                <h1 class="text-lg font-semibold text-zinc-900 dark:text-white">Editor da Loja</h1>
                <p class="text-xs text-zinc-500">Pré-visualização ao vivo · alterações só entram no ar ao publicar</p>
            </div>
            <Button type="button" :disabled="publishing" @click="publish">
                <Loader2 v-if="publishing" class="h-4 w-4 animate-spin" />
                <Save v-else class="h-4 w-4" />
                {{ publishing ? 'Publicando…' : 'Publicar' }}
            </Button>
        </header>

        <div class="flex min-h-0 flex-1">
            <!-- Controles ~380px -->
            <aside
                class="flex w-full max-w-full shrink-0 flex-col overflow-y-auto border-r border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900 lg:w-[380px] lg:max-w-[380px]"
            >
                <div class="space-y-6 p-4">
                    <!-- Marca -->
                    <section class="space-y-3">
                        <h2 :class="labelClass">Marca</h2>
                        <div>
                            <label class="mb-1 block text-xs text-zinc-600">Nome</label>
                            <input v-model="draft.brand.name" type="text" :class="inputClass" placeholder="Nome da loja" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-zinc-600">Slogan (abaixo do nome)</label>
                            <input v-model="draft.brand.tagline" type="text" :class="inputClass" placeholder="Body Piercing" />
                        </div>
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <label class="block text-xs text-zinc-600">Logo</label>
                                <span class="text-[10px] font-medium text-zinc-400">header da loja</span>
                            </div>
                            <div class="mb-2 rounded-xl border border-dashed border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/40">
                                <div
                                    class="flex h-14 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-[var(--sf-header-bg,#10261D)] px-3 dark:border-zinc-700"
                                    :style="{ backgroundColor: draft.brand.header_bg || '#10261D' }"
                                >
                                    <img
                                        v-if="draft.brand.logo_url || draft.brand.logo"
                                        :src="draft.brand.logo_url || draft.brand.logo"
                                        alt="Prévia do logo"
                                        class="max-h-10 max-w-[180px] object-contain"
                                    />
                                    <span v-else class="text-[10px] text-zinc-400">Sem logo</span>
                                </div>
                                <p class="mt-2 text-[11px] leading-relaxed text-zinc-500">
                                    <strong class="font-semibold text-zinc-600 dark:text-zinc-300">Recomendado:</strong>
                                    PNG transparente · altura <strong>96–120&nbsp;px</strong> · largura até <strong>400&nbsp;px</strong>
                                    (proporção horizontal). No site aparece com ~48&nbsp;px de altura.
                                </p>
                            </div>
                            <input
                                v-model="draft.brand.logo"
                                type="text"
                                :class="inputClass"
                                placeholder="https://… ou envie o arquivo"
                                @input="draft.brand.logo_url = draft.brand.logo"
                            />
                            <label class="mt-2 inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-zinc-700">
                                <span class="inline-flex items-center gap-1 rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900">
                                    <Upload class="h-3.5 w-3.5" />
                                    {{ uploading ? 'Enviando…' : 'Enviar logo' }}
                                </span>
                                <input type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="hidden" :disabled="uploading" @change="onLogoFile" />
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs text-zinc-600">Primária</label>
                                <div class="flex items-center gap-2">
                                    <input v-model="draft.brand.primary" type="color" class="h-9 w-10 cursor-pointer rounded border border-zinc-300 bg-white p-0.5" />
                                    <input v-model="draft.brand.primary" type="text" class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-xs" />
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-zinc-600">Secundária</label>
                                <div class="flex items-center gap-2">
                                    <input v-model="draft.brand.secondary" type="color" class="h-9 w-10 cursor-pointer rounded border border-zinc-300 bg-white p-0.5" />
                                    <input v-model="draft.brand.secondary" type="text" class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-xs" />
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-zinc-600">Fundo</label>
                                <div class="flex items-center gap-2">
                                    <input v-model="draft.brand.bg" type="color" class="h-9 w-10 cursor-pointer rounded border border-zinc-300 bg-white p-0.5" />
                                    <input v-model="draft.brand.bg" type="text" class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-xs" />
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-zinc-600">Texto</label>
                                <div class="flex items-center gap-2">
                                    <input v-model="draft.brand.text" type="color" class="h-9 w-10 cursor-pointer rounded border border-zinc-300 bg-white p-0.5" />
                                    <input v-model="draft.brand.text" type="text" class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-xs" />
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-zinc-600">Header</label>
                                <div class="flex items-center gap-2">
                                    <input v-model="draft.brand.header_bg" type="color" class="h-9 w-10 cursor-pointer rounded border border-zinc-300 bg-white p-0.5" />
                                    <input v-model="draft.brand.header_bg" type="text" class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-xs" />
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-zinc-600">Footer</label>
                                <div class="flex items-center gap-2">
                                    <input v-model="draft.brand.footer_bg" type="color" class="h-9 w-10 cursor-pointer rounded border border-zinc-300 bg-white p-0.5" />
                                    <input v-model="draft.brand.footer_bg" type="text" class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-xs" />
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-zinc-600">Dourado claro</label>
                                <div class="flex items-center gap-2">
                                    <input v-model="draft.brand.accent_light" type="color" class="h-9 w-10 cursor-pointer rounded border border-zinc-300 bg-white p-0.5" />
                                    <input v-model="draft.brand.accent_light" type="text" class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-xs" />
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-zinc-600">Texto header/footer</label>
                                <div class="flex items-center gap-2">
                                    <input v-model="draft.brand.header_text" type="color" class="h-9 w-10 cursor-pointer rounded border border-zinc-300 bg-white p-0.5" />
                                    <input v-model="draft.brand.header_text" type="text" class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-xs" />
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Announcement -->
                    <section class="space-y-3 border-t border-zinc-100 pt-5 dark:border-zinc-800">
                        <div class="flex items-center justify-between">
                            <h2 :class="labelClass">Faixa promocional</h2>
                            <Toggle v-model="draft.announcement_bar.enabled" />
                        </div>
                        <input v-model="draft.announcement_bar.text" type="text" :class="inputClass" placeholder="Texto da faixa" />
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            Use <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">&#123;&#123;free_shipping_min&#125;&#125;</code>
                            para o texto de frete grátis (valor em Configurações → Frete).
                        </p>
                        <input v-model="draft.announcement_bar.link" type="text" :class="inputClass" placeholder="Link (opcional)" />
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs text-zinc-600">Fundo</label>
                                <input v-model="draft.announcement_bar.bg" type="color" class="h-9 w-full cursor-pointer rounded border border-zinc-300" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-zinc-600">Texto</label>
                                <input v-model="draft.announcement_bar.color" type="color" class="h-9 w-full cursor-pointer rounded border border-zinc-300" />
                            </div>
                        </div>
                    </section>

                    <!-- WhatsApp -->
                    <section class="space-y-3 border-t border-zinc-100 pt-5 dark:border-zinc-800">
                        <h2 :class="labelClass">WhatsApp (rodapé / flutuante)</h2>
                        <input
                            v-model="draft.footer.whatsapp"
                            type="text"
                            :class="inputClass"
                            placeholder="5511999999999"
                        />
                    </section>

                    <!-- Sections list -->
                    <section class="space-y-3 border-t border-zinc-100 pt-5 dark:border-zinc-800">
                        <div class="flex items-center justify-between gap-2">
                            <h2 :class="labelClass">Seções</h2>
                            <div class="relative">
                                <Button type="button" size="sm" variant="outline" @click="addMenuOpen = !addMenuOpen">
                                    <Plus class="h-3.5 w-3.5" />
                                    Adicionar
                                </Button>
                                <div
                                    v-if="addMenuOpen"
                                    class="absolute right-0 z-20 mt-1 w-52 rounded-xl border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
                                >
                                    <button
                                        v-for="t in sectionTypes"
                                        :key="t.type"
                                        type="button"
                                        class="block w-full px-3 py-2 text-left text-xs hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                        @click="addSection(t.type)"
                                    >
                                        {{ t.label }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <draggable
                            v-model="draft.sections"
                            item-key="id"
                            handle=".drag-handle"
                            class="space-y-2"
                        >
                            <template #item="{ element }">
                                <div
                                    class="flex items-center gap-2 rounded-xl border px-2 py-2 text-sm transition"
                                    :class="
                                        selectedId === element.id
                                            ? 'border-zinc-900 bg-zinc-50 dark:border-white dark:bg-zinc-800'
                                            : 'border-zinc-200 dark:border-zinc-700'
                                    "
                                    @click="selectedId = element.id"
                                >
                                    <button type="button" class="drag-handle cursor-grab touch-none text-zinc-400" @click.stop>
                                        <GripVertical class="h-4 w-4" />
                                    </button>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-xs font-semibold text-zinc-800 dark:text-zinc-100">
                                            {{ labelFor(element.type) }}
                                        </p>
                                        <p class="truncate text-[10px] text-zinc-400">{{ element.type }}</p>
                                    </div>
                                    <input
                                        v-model="element.enabled"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-zinc-300"
                                        title="Ativa"
                                        @click.stop
                                    />
                                    <button
                                        type="button"
                                        class="rounded p-1 text-red-500 hover:bg-red-50"
                                        title="Remover"
                                        @click.stop="removeSection(element.id)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </template>
                        </draggable>

                        <p v-if="!draft.sections?.length" class="text-xs text-zinc-400">
                            Nenhuma seção. Clique em Adicionar.
                        </p>
                    </section>

                    <!-- Props editor -->
                    <section v-if="selected" class="space-y-3 border-t border-zinc-100 pt-5 dark:border-zinc-800">
                        <h2 :class="labelClass">Editar · {{ labelFor(selected.type) }}</h2>

                        <!-- Hero -->
                        <template v-if="selected.type === 'hero_slider'">
                            <div class="rounded-xl border border-amber-200/80 bg-amber-50/80 px-3 py-2.5 text-[11px] leading-relaxed text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100/90">
                                <p class="font-semibold">Banner do slider (rola sozinho a cada ~4,5s)</p>
                                <ul class="mt-1.5 list-disc space-y-0.5 pl-4 opacity-90">
                                    <li><strong>Desktop:</strong> 16:7 · ideal <strong>1920×840&nbsp;px</strong></li>
                                    <li><strong>Mobile:</strong> 3:4 · ideal <strong>1080×1440&nbsp;px</strong> (retrato)</li>
                                </ul>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <label class="text-xs text-zinc-600">Autoplay (ms)</label>
                                <input
                                    v-model.number="selected.props.autoplay_ms"
                                    type="number"
                                    min="3000"
                                    step="500"
                                    class="w-28 rounded-lg border border-zinc-300 px-2 py-1.5 text-xs"
                                    placeholder="4500"
                                />
                            </div>
                            <div
                                v-for="(slide, idx) in selected.props.slides || []"
                                :key="idx"
                                class="space-y-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-semibold text-zinc-600">Slide {{ idx + 1 }}</span>
                                    <button type="button" class="text-xs text-red-500" @click="removeHeroSlide(idx)">Remover</button>
                                </div>

                                <div>
                                    <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Desktop</p>
                                    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800">
                                        <div class="relative aspect-[16/7] min-h-[64px] w-full">
                                            <img
                                                v-if="slide.image"
                                                :src="slide.image"
                                                alt=""
                                                class="absolute inset-0 h-full w-full object-cover"
                                            />
                                            <div
                                                v-else
                                                class="absolute inset-0 flex flex-col items-center justify-center gap-1 px-3 text-center text-[10px] text-zinc-400"
                                            >
                                                <Upload class="h-4 w-4" />
                                                <span>1920 × 840 px</span>
                                            </div>
                                        </div>
                                    </div>
                                    <input v-model="slide.image" type="text" :class="inputClass + ' mt-2'" placeholder="URL banner desktop" />
                                    <label class="mt-1.5 inline-flex cursor-pointer text-xs font-medium text-zinc-700">
                                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900">
                                            <Upload class="h-3.5 w-3.5" />
                                            {{ uploading ? 'Enviando…' : 'Enviar desktop' }}
                                        </span>
                                        <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" :disabled="uploading" @change="onSlideImage($event, slide)" />
                                    </label>
                                </div>

                                <div>
                                    <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Mobile</p>
                                    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800">
                                        <div class="relative mx-auto aspect-[3/4] max-w-[140px]">
                                            <img
                                                v-if="slide.image_mobile"
                                                :src="slide.image_mobile"
                                                alt=""
                                                class="absolute inset-0 h-full w-full object-cover"
                                            />
                                            <div
                                                v-else
                                                class="absolute inset-0 flex flex-col items-center justify-center gap-1 px-2 text-center text-[10px] text-zinc-400"
                                            >
                                                <Upload class="h-4 w-4" />
                                                <span>1080×1440</span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mt-1 text-[10px] text-zinc-400">Se vazio, usa o banner desktop no celular.</p>
                                    <input v-model="slide.image_mobile" type="text" :class="inputClass + ' mt-2'" placeholder="URL banner mobile (opcional)" />
                                    <label class="mt-1.5 inline-flex cursor-pointer text-xs font-medium text-zinc-700">
                                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900">
                                            <Upload class="h-3.5 w-3.5" />
                                            {{ uploading ? 'Enviando…' : 'Enviar mobile' }}
                                        </span>
                                        <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" :disabled="uploading" @change="onSlideMobileImage($event, slide)" />
                                    </label>
                                </div>

                                <input v-model="slide.title" type="text" :class="inputClass" placeholder="Título" />
                                <input v-model="slide.subtitle" type="text" :class="inputClass" placeholder="Subtítulo" />
                                <input v-model="slide.cta" type="text" :class="inputClass" placeholder="Texto do botão (CTA)" />
                                <input v-model="slide.link" type="text" :class="inputClass" placeholder="Link do botão" />
                            </div>
                            <Button type="button" size="sm" variant="outline" @click="addHeroSlide">+ Slide</Button>
                        </template>

                        <!-- Shelf -->
                        <template v-else-if="selected.type === 'product_shelf'">
                            <input v-model="selected.props.title" type="text" :class="inputClass" placeholder="Título" />
                            <select v-model="selected.props.source" :class="inputClass">
                                <option value="newest">Lançamentos</option>
                                <option value="bestsellers">Mais vendidos</option>
                                <option value="category">Por categoria</option>
                                <option value="manual">Manual (IDs)</option>
                            </select>
                            <select
                                v-if="selected.props.source === 'category'"
                                v-model="selected.props.category_id"
                                :class="inputClass"
                            >
                                <option :value="null">Selecione a categoria</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <input
                                v-if="selected.props.source === 'manual'"
                                :value="(selected.props.product_ids || []).join(',')"
                                type="text"
                                :class="inputClass"
                                placeholder="IDs de produtos separados por vírgula"
                                @input="selected.props.product_ids = $event.target.value.split(',').map((s) => s.trim()).filter(Boolean)"
                            />
                            <input v-model.number="selected.props.limit" type="number" min="1" max="48" :class="inputClass" placeholder="Limite" />
                        </template>

                        <!-- Category circles -->
                        <template v-else-if="selected.type === 'category_circles'">
                            <input v-model="selected.props.title" type="text" :class="inputClass" placeholder="Título (opcional)" />
                            <select v-model="selected.props.mode" :class="inputClass">
                                <option value="featured">Destacadas (círculo)</option>
                                <option value="manual">Seleção manual</option>
                            </select>
                            <div v-if="selected.props.mode === 'manual'" class="flex flex-wrap gap-2">
                                <label
                                    v-for="c in categories"
                                    :key="c.id"
                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border px-2 py-1 text-xs"
                                    :class="
                                        (selected.props.category_ids || []).some((id) => String(id) === String(c.id))
                                            ? 'border-zinc-900 bg-zinc-900 text-white'
                                            : 'border-zinc-200'
                                    "
                                >
                                    <input
                                        type="checkbox"
                                        class="sr-only"
                                        :checked="(selected.props.category_ids || []).some((id) => String(id) === String(c.id))"
                                        @change="toggleCategoryId(c.id)"
                                    />
                                    {{ c.name }}
                                </label>
                            </div>
                        </template>

                        <!-- Trust -->
                        <template v-else-if="selected.type === 'trust_bar'">
                            <div
                                v-for="(item, idx) in selected.props.items || []"
                                :key="idx"
                                class="space-y-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700"
                            >
                                <div class="flex justify-between">
                                    <span class="text-xs font-semibold text-zinc-600">Item {{ idx + 1 }}</span>
                                    <button type="button" class="text-xs text-red-500" @click="removeTrustItem(idx)">Remover</button>
                                </div>
                                <input v-model="item.icon" type="text" :class="inputClass" placeholder="Ícone (shield, truck…)" />
                                <input v-model="item.title" type="text" :class="inputClass" placeholder="Título" />
                                <input v-model="item.text" type="text" :class="inputClass" placeholder="Texto" />
                            </div>
                            <Button type="button" size="sm" variant="outline" @click="addTrustItem">+ Item</Button>
                        </template>

                        <!-- Promo -->
                        <template v-else-if="selected.type === 'promo_split'">
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-[11px] leading-relaxed text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800/40 dark:text-zinc-300">
                                Imagem lateral: proporção ~<strong>4:5</strong> ou <strong>1:1</strong> · ideal <strong>1000×1200&nbsp;px</strong> (JPG/WebP).
                            </div>
                            <input v-model="selected.props.title" type="text" :class="inputClass" placeholder="Título" />
                            <div class="overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 dark:border-zinc-700">
                                <div class="relative aspect-[4/5] max-h-40 w-full">
                                    <img
                                        v-if="selected.props.image"
                                        :src="selected.props.image"
                                        alt=""
                                        class="absolute inset-0 h-full w-full object-cover"
                                    />
                                    <div v-else class="absolute inset-0 flex items-center justify-center text-[10px] text-zinc-400">
                                        1000 × 1200 px
                                    </div>
                                </div>
                            </div>
                            <input v-model="selected.props.image" type="text" :class="inputClass" placeholder="URL da imagem" />
                            <label class="inline-flex cursor-pointer text-xs font-medium text-zinc-700">
                                <span class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900">
                                    <Upload class="h-3.5 w-3.5" />
                                    Enviar imagem
                                </span>
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="hidden"
                                    :disabled="uploading"
                                    @change="onPromoImage($event)"
                                />
                            </label>
                            <input v-model="selected.props.cta" type="text" :class="inputClass" placeholder="CTA" />
                            <input v-model="selected.props.link" type="text" :class="inputClass" placeholder="Link" />
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="mb-1 block text-xs text-zinc-600">Fundo</label>
                                    <input v-model="selected.props.bg" type="color" class="h-9 w-full rounded border" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-zinc-600">Texto</label>
                                    <input v-model="selected.props.color" type="color" class="h-9 w-full rounded border" />
                                </div>
                            </div>
                            <textarea
                                :value="bulletsText(selected.props)"
                                rows="4"
                                :class="inputClass"
                                placeholder="Um bullet por linha"
                                @input="setBullets(selected.props, $event.target.value)"
                            />
                        </template>

                        <!-- Instagram -->
                        <template v-else-if="selected.type === 'instagram_grid'">
                            <input v-model="selected.props.title" type="text" :class="inputClass" placeholder="Título" />
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                Envie fotos quadradas (ex.: 1080×1080). Link e legenda são opcionais.
                            </p>
                            <div
                                v-for="(item, idx) in selected.props.items || []"
                                :key="idx"
                                class="space-y-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700"
                            >
                                <div class="flex justify-between">
                                    <span class="text-xs font-semibold text-zinc-600">Post {{ idx + 1 }}</span>
                                    <button type="button" class="text-xs text-red-500" @click="removeInstagramItem(idx)">Remover</button>
                                </div>
                                <div class="overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800">
                                    <div class="relative mx-auto aspect-square max-w-[160px]">
                                        <img
                                            v-if="item.image"
                                            :src="item.image"
                                            alt=""
                                            class="absolute inset-0 h-full w-full object-cover"
                                        />
                                        <div
                                            v-else
                                            class="absolute inset-0 flex flex-col items-center justify-center gap-1 px-2 text-center text-[10px] text-zinc-400"
                                        >
                                            <Upload class="h-4 w-4" />
                                            <span>1080×1080</span>
                                        </div>
                                    </div>
                                </div>
                                <label class="inline-flex cursor-pointer text-xs font-medium text-zinc-700">
                                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200">
                                        <Upload class="h-3.5 w-3.5" />
                                        {{ uploading ? 'Enviando…' : (item.image ? 'Trocar imagem' : 'Enviar imagem') }}
                                    </span>
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        class="hidden"
                                        :disabled="uploading"
                                        @change="onInstagramImage($event, item)"
                                    />
                                </label>
                                <input v-model="item.link" type="text" :class="inputClass" placeholder="Link (opcional)" />
                                <input v-model="item.caption" type="text" :class="inputClass" placeholder="Legenda (opcional)" />
                            </div>
                            <Button type="button" size="sm" variant="outline" @click="addInstagramItem">+ Item</Button>
                        </template>

                        <!-- Newsletter -->
                        <template v-else-if="selected.type === 'newsletter'">
                            <input v-model="selected.props.title" type="text" :class="inputClass" placeholder="Título" />
                            <input v-model="selected.props.subtitle" type="text" :class="inputClass" placeholder="Subtítulo" />
                        </template>

                        <!-- Rich text -->
                        <template v-else-if="selected.type === 'rich_text'">
                            <textarea v-model="selected.props.html" rows="8" :class="inputClass" placeholder="HTML" />
                        </template>

                        <template v-else>
                            <p class="text-xs text-zinc-400">Sem editor específico para este tipo.</p>
                        </template>
                    </section>
                </div>
            </aside>

            <!-- Preview -->
            <div class="relative min-w-0 flex-1 bg-zinc-200 dark:bg-zinc-950">
                <iframe
                    ref="iframeRef"
                    src="/?preview=1"
                    title="Pré-visualização da loja"
                    class="absolute inset-0 h-full w-full border-0 bg-white"
                    @load="onIframeLoad"
                />
            </div>
        </div>
    </div>
</template>
