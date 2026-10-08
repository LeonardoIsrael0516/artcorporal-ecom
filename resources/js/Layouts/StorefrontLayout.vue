<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { STORE_PREVIEW_MSG, ackStoreThemePreview } from '@/lib/storeEditorPreview.js';

const page = usePage();

const previewOverride = ref(null);
const openNav = ref(null);
const cookieAccepted = ref(true);
const searchQ = ref('');
const mobileMenuOpen = ref(false);

const storeTheme = computed(() => {
    const base = page.props.storeTheme || {};
    if (!previewOverride.value) return base;
    return deepMerge(base, previewOverride.value);
});

const cartCount = computed(() => Number(page.props.cartCount ?? 0));
const menuCategories = computed(() => page.props.menuCategories || []);
const authUser = computed(() => page.props.auth?.user || null);

const brand = computed(() => storeTheme.value.brand || {});
const announcement = computed(() => storeTheme.value.announcement_bar || null);
const freeShipping = computed(() => storeTheme.value.free_shipping || null);

function formatFreeShippingMin(amount) {
    const n = Number(amount);
    if (!Number.isFinite(n) || n <= 0) return '';
    return n.toLocaleString('pt-BR', {
        minimumFractionDigits: Number.isInteger(n) ? 0 : 2,
        maximumFractionDigits: 2,
    });
}

const announcementText = computed(() => {
    const raw = typeof announcement.value === 'string'
        ? announcement.value
        : (announcement.value?.text || '');
    if (!raw) return '';

    const fs = freeShipping.value;
    let fretePart = '';
    if (fs?.enabled && Number(fs.min_amount) > 0) {
        fretePart = `Frete grátis acima de R$${formatFreeShippingMin(fs.min_amount)}`;
    }

    return String(raw)
        .replace(/\{\{\s*free_shipping_min\s*\}\}/gi, fretePart)
        .replace(/\s*·\s*·\s*/g, ' · ')
        .replace(/^\s*·\s*/, '')
        .replace(/\s*·\s*$/, '')
        .replace(/\s{2,}/g, ' ')
        .trim();
});
const footer = computed(() => storeTheme.value.footer || {});
const logoUrl = computed(() => brand.value.logo || brand.value.logo_url || storeTheme.value.logo_url || null);
const storeName = computed(() => brand.value.name || storeTheme.value.name || 'Loja');
const whatsapp = computed(() => {
    const raw = storeTheme.value.whatsapp || footer.value.whatsapp || brand.value.whatsapp || '';
    return String(raw).replace(/\D/g, '');
});

const storeTagline = computed(() => (brand.value.tagline ?? 'Body Piercing') || '');

const FONT_STYLESHEET =
    'https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Montserrat:wght@300;400;500;600;700&display=swap';

const cssVars = computed(() => {
    const gold = brand.value.primary || '#B8854A';
    const goldLight = brand.value.accent_light || '#E6BC82';
    return {
        '--sf-primary': gold,
        '--sf-accent-light': goldLight,
        '--sf-secondary': brand.value.secondary || '#10261D',
        '--sf-bg': brand.value.bg || '#FAF6EF',
        '--sf-text': brand.value.text || '#16241D',
        '--sf-header-bg': brand.value.header_bg || '#10261D',
        '--sf-header-text': brand.value.header_text || '#F3E7D3',
        '--sf-footer-bg': brand.value.footer_bg || '#0B1C15',
        '--sf-gold-gradient': `linear-gradient(120deg, ${gold} 0%, ${goldLight} 45%, ${gold} 100%)`,
        '--sf-font-heading': brand.value.font_heading || "'Cormorant Garamond', Georgia, serif",
        '--sf-font-body': brand.value.font_body || "'Montserrat', system-ui, sans-serif",
    };
});

const announcementStyle = computed(() => {
    const a = announcement.value;
    if (!a || typeof a !== 'object') return {};
    return {
        ...(a.bg ? { background: a.bg } : {}),
        ...(a.color ? { color: a.color } : {}),
    };
});

const accountHref = computed(() => (authUser.value ? '/conta' : '/conta/entrar'));

const newsletterForm = useForm({ email: '' });

function deepMerge(a, b) {
    if (!b || typeof b !== 'object') return a;
    const out = { ...a };
    for (const key of Object.keys(b)) {
        if (
            b[key] &&
            typeof b[key] === 'object' &&
            !Array.isArray(b[key]) &&
            a[key] &&
            typeof a[key] === 'object' &&
            !Array.isArray(a[key])
        ) {
            out[key] = deepMerge(a[key], b[key]);
        } else if (b[key] !== undefined) {
            out[key] = b[key];
        }
    }
    return out;
}

function isPreviewMode() {
    if (typeof window === 'undefined') return false;
    return new URLSearchParams(window.location.search).get('preview') === '1';
}

function onPreviewMessage(event) {
    const data = event?.data;
    if (!data || data.type !== STORE_PREVIEW_MSG) return;
    if (!isPreviewMode()) return;
    if (data.theme && typeof data.theme === 'object') {
        previewOverride.value = data.theme;
        ackStoreThemePreview(event);
    }
}

function dismissCookie() {
    cookieAccepted.value = true;
    try {
        localStorage.setItem('sf_cookie_ok', '1');
    } catch (_) {}
}

function submitNewsletter() {
    newsletterForm.post('/newsletter', {
        preserveScroll: true,
        onSuccess: () => newsletterForm.reset('email'),
    });
}

watch(
    () => page.url,
    () => {
        openNav.value = null;
        mobileMenuOpen.value = false;
    },
);

onMounted(() => {
    try {
        cookieAccepted.value = localStorage.getItem('sf_cookie_ok') === '1';
    } catch (_) {
        cookieAccepted.value = false;
    }
    window.addEventListener('message', onPreviewMessage);
});

onUnmounted(() => {
    window.removeEventListener('message', onPreviewMessage);
});
</script>

<template>
    <div
        class="sf-root min-h-screen flex flex-col antialiased text-[var(--sf-text)] bg-[var(--sf-bg)]"
        :style="cssVars"
    >
        <Head>
            <link rel="preconnect" href="https://fonts.googleapis.com" />
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="" />
            <link rel="stylesheet" :href="FONT_STYLESHEET" />
        </Head>

        <!-- Announcement -->
        <div
            v-if="announcement?.enabled !== false && announcementText"
            class="px-4 py-2 text-center text-[11px] font-semibold tracking-[0.18em] uppercase text-[var(--sf-secondary)]"
            style="background: var(--sf-gold-gradient)"
            :style="announcementStyle"
        >
            <a
                v-if="announcement?.link"
                :href="announcement.link"
                class="hover:opacity-80"
            >{{ announcementText }}</a>
            <template v-else>{{ announcementText }}</template>
        </div>

        <!-- Header -->
        <header class="sf-header sticky top-0 z-40 bg-[var(--sf-header-bg)] text-[var(--sf-header-text)] shadow-[0_1px_0_0_rgba(200,149,90,0.25)]">
            <div class="mx-auto flex max-w-[1360px] items-center gap-4 px-4 py-4 md:gap-8 md:px-8">
                <button
                    type="button"
                    class="md:hidden flex h-9 w-9 items-center justify-center border border-[var(--sf-primary)]/40 text-[var(--sf-accent-light)]"
                    aria-label="Menu"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round" />
                    </svg>
                </button>

                <Link href="/" class="flex shrink-0 items-center" :aria-label="storeName">
                    <img
                        v-if="logoUrl"
                        :src="logoUrl"
                        :alt="storeName"
                        class="h-12 w-auto max-w-[200px] object-contain"
                    />
                    <span v-else class="flex flex-col items-center leading-none">
                        <span class="sf-wordmark sf-gold-text text-[1.35rem] md:text-[1.7rem]">{{ storeName }}</span>
                        <span
                            v-if="storeTagline"
                            class="mt-1.5 flex items-center gap-2 text-[9px] font-medium tracking-[0.45em] uppercase text-[var(--sf-accent-light)]/90 md:text-[10px]"
                        >
                            <span class="h-px w-4 bg-[var(--sf-primary)]/60" />
                            {{ storeTagline }}
                            <span class="h-px w-4 bg-[var(--sf-primary)]/60" />
                        </span>
                    </span>
                </Link>

                <form
                    action="/busca"
                    method="get"
                    class="mx-auto hidden max-w-xl flex-1 md:flex"
                >
                    <div class="flex w-full items-stretch overflow-hidden border border-[var(--sf-primary)]/35 bg-white/[0.04] transition focus-within:border-[var(--sf-accent-light)]">
                        <svg viewBox="0 0 24 24" class="ml-3 h-4 w-4 shrink-0 self-center text-[var(--sf-accent-light)]/70" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <circle cx="11" cy="11" r="7" />
                            <path d="m20 20-3.5-3.5" stroke-linecap="round" />
                        </svg>
                        <input
                            v-model="searchQ"
                            type="search"
                            name="q"
                            placeholder="O que você procura?"
                            class="w-full bg-transparent px-3 py-2.5 text-sm text-[var(--sf-header-text)] placeholder:text-[var(--sf-header-text)]/45 outline-none"
                        />
                        <button type="submit" class="sf-btn-gold sf-btn-inset px-5 text-[11px] font-semibold tracking-[0.18em] uppercase">
                            Buscar
                        </button>
                    </div>
                </form>

                <div class="ml-auto flex items-center gap-5 text-[13px] tracking-wide">
                    <Link :href="accountHref" class="hidden items-center gap-2 transition hover:text-[var(--sf-accent-light)] sm:inline-flex">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                            <circle cx="12" cy="8" r="4" />
                            <path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6" stroke-linecap="round" />
                        </svg>
                        <span class="hidden lg:inline">Minha Conta</span>
                    </Link>
                    <Link href="/carrinho" class="relative inline-flex items-center gap-2 transition hover:text-[var(--sf-accent-light)]">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                            <path d="M5 8h14l-1.2 11.2a2 2 0 0 1-2 1.8H8.2a2 2 0 0 1-2-1.8L5 8Z" />
                            <path d="M9 8V6.5a3 3 0 0 1 6 0V8" stroke-linecap="round" />
                        </svg>
                        <span class="hidden lg:inline">Carrinho</span>
                        <span
                            v-if="cartCount > 0"
                            class="sf-cart-badge absolute -left-2 -top-2 flex h-[18px] min-w-[18px] items-center justify-center px-1 text-[10px] font-bold"
                        >
                            {{ cartCount > 99 ? '99+' : cartCount }}
                        </span>
                    </Link>
                </div>
            </div>

            <!-- Desktop nav -->
            <nav class="hidden border-t border-[var(--sf-primary)]/20 md:block">
                <ul class="mx-auto flex max-w-[1360px] flex-wrap items-center justify-center gap-1 px-4 md:px-8">
                    <li
                        v-for="cat in menuCategories"
                        :key="cat.id || cat.slug || cat.name"
                        class="relative"
                        @mouseenter="openNav = cat.slug || cat.name"
                        @mouseleave="openNav = null"
                    >
                        <Link
                            :href="`/categoria/${cat.slug}`"
                            class="sf-nav-link inline-flex items-center gap-1 px-4 py-3 text-[11px] font-medium tracking-[0.2em] uppercase transition hover:text-[var(--sf-accent-light)]"
                        >
                            {{ cat.name }}
                            <span v-if="cat.children?.length" class="text-[9px] opacity-70">▾</span>
                        </Link>
                        <ul
                            v-if="cat.children?.length && openNav === (cat.slug || cat.name)"
                            class="absolute left-1/2 top-full z-50 min-w-[220px] -translate-x-1/2 border-t-2 border-[var(--sf-primary)] bg-[var(--sf-bg)] py-2 text-[var(--sf-text)] shadow-xl"
                        >
                            <li v-for="child in cat.children" :key="child.id || child.slug">
                                <Link
                                    :href="`/categoria/${child.slug}`"
                                    class="block px-5 py-2 text-sm transition hover:bg-[var(--sf-primary)]/10 hover:text-[var(--sf-primary)]"
                                >
                                    {{ child.name }}
                                </Link>
                            </li>
                        </ul>
                    </li>
                </ul>
            </nav>

            <!-- Mobile menu -->
            <div v-if="mobileMenuOpen" class="border-t border-[var(--sf-primary)]/20 px-4 py-4 md:hidden">
                <form action="/busca" method="get" class="mb-4 flex overflow-hidden border border-[var(--sf-primary)]/35">
                    <input
                        type="search"
                        name="q"
                        placeholder="Buscar..."
                        class="w-full bg-transparent px-3 py-2 text-sm text-[var(--sf-header-text)] placeholder:text-[var(--sf-header-text)]/45 outline-none"
                    />
                    <button type="submit" class="sf-btn-gold sf-btn-inset px-4 text-[11px] font-semibold tracking-widest uppercase">OK</button>
                </form>
                <Link :href="accountHref" class="mb-3 block text-sm font-medium text-[var(--sf-accent-light)]">Minha Conta</Link>
                <ul class="space-y-1">
                    <li v-for="cat in menuCategories" :key="cat.id || cat.slug">
                        <Link :href="`/categoria/${cat.slug}`" class="block py-2 text-xs font-medium tracking-[0.2em] uppercase">
                            {{ cat.name }}
                        </Link>
                        <ul v-if="cat.children?.length" class="mb-2 ml-3 border-l border-[var(--sf-primary)]/30 pl-3">
                            <li v-for="child in cat.children" :key="child.slug">
                                <Link :href="`/categoria/${child.slug}`" class="block py-1.5 text-sm text-[var(--sf-header-text)]/70">
                                    {{ child.name }}
                                </Link>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="mt-auto bg-[var(--sf-footer-bg)] text-[var(--sf-header-text)]">
            <div class="h-px w-full" style="background: var(--sf-gold-gradient)" />

            <div class="mx-auto max-w-[1360px] px-4 pt-14 md:px-8">
                <div class="flex flex-col items-center text-center">
                    <span class="sf-wordmark sf-gold-text text-2xl md:text-3xl">{{ storeName }}</span>
                    <span
                        v-if="storeTagline"
                        class="mt-2 text-[10px] font-medium tracking-[0.5em] uppercase text-[var(--sf-accent-light)]/80"
                    >
                        {{ storeTagline }}
                    </span>
                    <div class="sf-ornament sf-footer-ornament mt-6 w-40" />
                </div>
            </div>

            <div class="mx-auto grid max-w-[1360px] gap-10 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4 md:px-8">
                <div
                    v-for="(col, i) in (footer.columns || [])"
                    :key="i"
                >
                    <h3 class="mb-5 text-[11px] font-semibold tracking-[0.25em] uppercase text-[var(--sf-accent-light)]">
                        {{ col.title }}
                    </h3>
                    <ul class="space-y-2.5">
                        <li v-for="(link, j) in (col.links || [])" :key="j">
                            <Link
                                v-if="(link.url || link.href) && !String(link.url || link.href).startsWith('http')"
                                :href="link.url || link.href"
                                class="text-sm text-[var(--sf-header-text)]/70 transition hover:text-[var(--sf-accent-light)]"
                            >
                                {{ link.label }}
                            </Link>
                            <a
                                v-else
                                :href="link.url || link.href || '#'"
                                class="text-sm text-[var(--sf-header-text)]/70 transition hover:text-[var(--sf-accent-light)]"
                                :target="String(link.url || link.href || '').startsWith('http') ? '_blank' : undefined"
                                rel="noopener noreferrer"
                            >
                                {{ link.label }}
                            </a>
                        </li>
                    </ul>
                </div>

                <div v-if="footer.newsletter !== false && footer.show_newsletter !== false" class="lg:col-span-2 lg:pl-10">
                    <h3 class="mb-5 text-[11px] font-semibold tracking-[0.25em] uppercase text-[var(--sf-accent-light)]">
                        Newsletter
                    </h3>
                    <p class="sf-serif mb-4 text-xl italic text-[var(--sf-header-text)]/85">
                        {{ footer.newsletter_text || 'Novidades, lançamentos e ofertas exclusivas no seu e-mail.' }}
                    </p>
                    <form class="flex flex-col gap-2 sm:flex-row" @submit.prevent="submitNewsletter">
                        <input
                            v-model="newsletterForm.email"
                            type="email"
                            required
                            placeholder="Seu e-mail"
                            class="flex-1 border border-[var(--sf-primary)]/35 bg-white/[0.03] px-4 py-3 text-sm text-[var(--sf-header-text)] placeholder:text-[var(--sf-header-text)]/40 outline-none transition focus:border-[var(--sf-accent-light)]"
                        />
                        <button
                            type="submit"
                            class="sf-btn-gold px-7 py-3 text-[11px] font-semibold tracking-[0.2em] uppercase disabled:opacity-60"
                            :disabled="newsletterForm.processing"
                        >
                            Assinar
                        </button>
                    </form>
                </div>
            </div>

            <div class="border-t border-[var(--sf-primary)]/15 px-4 py-6 text-center md:px-8">
                <p class="mb-2 text-[11px] tracking-wider text-[var(--sf-header-text)]/55">
                    {{ footer.payment_text || 'Aceitamos Pix, cartão de crédito e boleto' }}
                </p>
                <p class="text-[11px] tracking-wider text-[var(--sf-header-text)]/35">
                    {{ footer.copyright || `© ${new Date().getFullYear()} ${storeName}. Todos os direitos reservados.` }}
                </p>
            </div>
        </footer>

        <!-- WhatsApp float -->
        <a
            v-if="whatsapp"
            :href="`https://wa.me/${whatsapp}`"
            target="_blank"
            rel="noopener noreferrer"
            class="fixed bottom-5 right-5 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg hover:scale-105 transition"
            aria-label="WhatsApp"
        >
            <svg viewBox="0 0 24 24" class="h-7 w-7 fill-current" aria-hidden="true">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
            </svg>
        </a>

        <!-- Cookie bar -->
        <div
            v-if="!cookieAccepted"
            class="fixed bottom-0 left-0 right-0 z-50 border-t border-[var(--sf-primary)]/30 bg-[var(--sf-bg)] px-4 py-4 shadow-[0_-8px_30px_rgba(16,38,29,0.12)] md:px-8"
        >
            <div class="mx-auto flex max-w-[1360px] flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-[var(--sf-text)]/75">
                    Usamos cookies para melhorar sua experiência de compra.
                </p>
                <button
                    type="button"
                    class="sf-btn px-6 py-2.5 text-[11px] font-semibold tracking-[0.2em] uppercase"
                    @click="dismissCookie"
                >
                    Entendi
                </button>
            </div>
        </div>
    </div>
</template>

<style>
.sf-root {
    font-family: var(--sf-font-body);
    --color-primary: var(--sf-secondary);
}

.sf-root h1,
.sf-root h2,
.sf-serif {
    font-family: var(--sf-font-heading);
    font-weight: 600;
    letter-spacing: 0.01em;
}

.sf-wordmark {
    font-family: 'Cinzel', var(--sf-font-heading);
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.sf-gold-text {
    background-image: var(--sf-gold-gradient);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.sf-btn,
.sf-btn-gold,
.sf-btn-outline {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    /* Dois cantos opostos arredondados — efeito editorial limpo */
    border-radius: 1.75rem 0.2rem;
    letter-spacing: 0.18em;
    transition:
        background 0.3s ease,
        background-position 0.55s ease,
        color 0.3s ease,
        border-color 0.3s ease,
        box-shadow 0.3s ease,
        transform 0.25s ease;
}

.sf-btn:hover:not(:disabled),
.sf-btn-gold:hover:not(:disabled),
.sf-btn-outline:hover:not(:disabled) {
    transform: translateY(-1px);
}

.sf-btn {
    background: var(--sf-secondary);
    color: var(--sf-header-text);
    border: 1px solid var(--sf-secondary);
    box-shadow: 0 8px 22px -14px color-mix(in srgb, var(--sf-secondary) 70%, transparent);
}

.sf-btn:hover:not(:disabled) {
    background: var(--sf-gold-gradient);
    color: var(--sf-secondary);
    border-color: var(--sf-primary);
    box-shadow: 0 10px 26px -12px var(--sf-primary);
}

.sf-btn-gold {
    background-image: linear-gradient(
        120deg,
        var(--sf-primary) 0%,
        var(--sf-accent-light) 25%,
        var(--sf-primary) 50%,
        var(--sf-accent-light) 75%,
        var(--sf-primary) 100%
    );
    background-size: 200% 100%;
    background-position: 0% 50%;
    color: var(--sf-secondary);
    border: 1px solid color-mix(in srgb, var(--sf-primary) 70%, transparent);
    box-shadow: 0 8px 22px -12px color-mix(in srgb, var(--sf-primary) 65%, transparent);
}

.sf-btn-gold:hover:not(:disabled) {
    background-position: 100% 50%;
    box-shadow: 0 12px 28px -12px var(--sf-primary);
}

.sf-btn-outline {
    border: 1px solid var(--sf-primary);
    color: var(--sf-secondary);
    background: transparent;
}

.sf-btn-outline:hover:not(:disabled) {
    background: var(--sf-secondary);
    color: var(--sf-accent-light);
    border-color: var(--sf-secondary);
}

/* Botão embutido (busca etc.) — sem raio assimétrico, não vaza da borda */
.sf-btn.sf-btn-inset,
.sf-btn-gold.sf-btn-inset,
.sf-btn-outline.sf-btn-inset {
    border-radius: 0;
    border: none;
    box-shadow: none;
    transform: none;
    align-self: stretch;
}

.sf-btn.sf-btn-inset:hover:not(:disabled),
.sf-btn-gold.sf-btn-inset:hover:not(:disabled),
.sf-btn-outline.sf-btn-inset:hover:not(:disabled) {
    transform: none;
}

.sf-cart-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background-image: linear-gradient(120deg, var(--sf-primary), var(--sf-accent-light), var(--sf-primary));
    color: var(--sf-secondary);
}

.sf-eyebrow {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.3em;
    text-transform: uppercase;
    color: var(--sf-primary);
}

.sf-ornament {
    position: relative;
    height: 12px;
    margin-left: auto;
    margin-right: auto;
}

.sf-ornament::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--sf-primary), transparent);
}

.sf-ornament::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 7px;
    height: 7px;
    background: var(--sf-accent-light);
    transform: translate(-50%, -50%) rotate(45deg);
    box-shadow: 0 0 0 3px var(--sf-bg, #f3e8dc);
}

.sf-footer-ornament::after {
    box-shadow: 0 0 0 3px var(--sf-footer-bg);
}

.sf-hero-placeholder {
    background:
        radial-gradient(ellipse 60% 80% at 50% 50%, color-mix(in srgb, var(--sf-primary) 22%, transparent) 0%, transparent 70%),
        radial-gradient(circle at 12% 20%, color-mix(in srgb, var(--sf-accent-light) 10%, transparent) 0%, transparent 35%),
        radial-gradient(circle at 88% 85%, color-mix(in srgb, var(--sf-accent-light) 10%, transparent) 0%, transparent 35%),
        linear-gradient(160deg, color-mix(in srgb, var(--sf-secondary) 85%, #000) 0%, var(--sf-secondary) 50%, color-mix(in srgb, var(--sf-secondary) 80%, #000) 100%);
}

.sf-hero-placeholder::before {
    content: '';
    position: absolute;
    inset: 18px;
    border: 1px solid color-mix(in srgb, var(--sf-primary) 30%, transparent);
    pointer-events: none;
}

.sf-nav-link {
    position: relative;
}

.sf-nav-link::after {
    content: '';
    position: absolute;
    left: 50%;
    right: 50%;
    bottom: 6px;
    height: 1px;
    background: var(--sf-accent-light);
    transition: left 0.3s ease, right 0.3s ease;
}

.sf-nav-link:hover::after {
    left: 1rem;
    right: 1rem;
}
</style>
