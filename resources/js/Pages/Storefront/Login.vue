<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

const props = defineProps({
    mode: {
        type: String,
        default: 'login',
    },
});

const page = usePage();
const brand = computed(() => page.props.storeTheme?.brand || {});

const tab = ref(props.mode === 'register' ? 'register' : 'login');

const loginForm = useForm({
    email: '',
    password: '',
    remember: true,
});

const registerForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submitLogin() {
    loginForm.post('/conta/entrar', {
        onFinish: () => loginForm.reset('password'),
    });
}

function submitRegister() {
    registerForm.post('/conta/registro', {
        onFinish: () => registerForm.reset('password', 'password_confirmation'),
    });
}

const inputClass =
    'w-full border border-[var(--sf-primary)]/40 bg-white px-4 py-3 text-sm outline-none transition focus:border-[var(--sf-secondary)]';
const labelClass = 'mb-1.5 block text-[11px] font-semibold tracking-[0.2em] uppercase text-[var(--sf-secondary)]';
</script>

<template>
    <StorefrontLayout>
        <Head :title="tab === 'login' ? 'Entrar' : 'Criar conta'" />

        <div class="mx-auto max-w-[1100px] px-4 py-14 md:px-8">
            <div class="grid overflow-hidden border border-[var(--sf-primary)]/25 bg-white/70 md:grid-cols-2">
                <div class="sf-hero-placeholder relative hidden flex-col items-center justify-center p-12 text-center md:flex">
                    <span class="sf-wordmark sf-gold-text relative text-3xl">{{ brand.name || 'Loja' }}</span>
                    <span v-if="brand.tagline" class="relative mt-2 text-[10px] font-medium tracking-[0.5em] uppercase text-[var(--sf-accent-light)]/85">
                        {{ brand.tagline }}
                    </span>
                    <div class="sf-ornament relative mt-6 w-32" style="--sf-bg: var(--sf-secondary)" />
                    <p class="sf-serif relative mt-6 max-w-xs text-xl italic text-[var(--sf-header-text)]/85">
                        Acompanhe seus pedidos, salve endereços e compre com mais agilidade.
                    </p>
                </div>

                <div class="p-8 md:p-12">
                    <div class="mb-8 flex border-b border-[var(--sf-primary)]/25">
                        <button
                            type="button"
                            class="flex-1 pb-3 text-[11px] font-semibold tracking-[0.25em] uppercase transition"
                            :class="tab === 'login' ? 'border-b-2 border-[var(--sf-primary)] text-[var(--sf-secondary)]' : 'text-[var(--sf-text)]/45 hover:text-[var(--sf-secondary)]'"
                            @click="tab = 'login'"
                        >
                            Entrar
                        </button>
                        <button
                            type="button"
                            class="flex-1 pb-3 text-[11px] font-semibold tracking-[0.25em] uppercase transition"
                            :class="tab === 'register' ? 'border-b-2 border-[var(--sf-primary)] text-[var(--sf-secondary)]' : 'text-[var(--sf-text)]/45 hover:text-[var(--sf-secondary)]'"
                            @click="tab = 'register'"
                        >
                            Criar conta
                        </button>
                    </div>

                    <form v-if="tab === 'login'" class="space-y-5" @submit.prevent="submitLogin">
                        <h1 class="text-3xl text-[var(--sf-secondary)]">Bem-vinda(o) de volta</h1>
                        <div>
                            <label :class="labelClass">E-mail</label>
                            <input v-model="loginForm.email" type="email" required autocomplete="email" :class="inputClass" />
                            <p v-if="loginForm.errors.email" class="mt-1 text-xs text-red-700">{{ loginForm.errors.email }}</p>
                        </div>
                        <div>
                            <label :class="labelClass">Senha</label>
                            <input v-model="loginForm.password" type="password" required autocomplete="current-password" :class="inputClass" />
                            <p v-if="loginForm.errors.password" class="mt-1 text-xs text-red-700">{{ loginForm.errors.password }}</p>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-[var(--sf-text)]/75">
                            <input v-model="loginForm.remember" type="checkbox" class="accent-[var(--sf-secondary)]" />
                            Lembrar de mim
                        </label>
                        <button
                            type="submit"
                            class="sf-btn-gold w-full px-6 py-4 text-xs font-semibold tracking-[0.25em] uppercase disabled:opacity-60"
                            :disabled="loginForm.processing"
                        >
                            {{ loginForm.processing ? 'Entrando…' : 'Entrar' }}
                        </button>
                    </form>

                    <form v-else class="space-y-5" @submit.prevent="submitRegister">
                        <h1 class="text-3xl text-[var(--sf-secondary)]">Crie sua conta</h1>
                        <div>
                            <label :class="labelClass">Nome</label>
                            <input v-model="registerForm.name" type="text" required autocomplete="name" :class="inputClass" />
                            <p v-if="registerForm.errors.name" class="mt-1 text-xs text-red-700">{{ registerForm.errors.name }}</p>
                        </div>
                        <div>
                            <label :class="labelClass">E-mail</label>
                            <input v-model="registerForm.email" type="email" required autocomplete="email" :class="inputClass" />
                            <p v-if="registerForm.errors.email" class="mt-1 text-xs text-red-700">{{ registerForm.errors.email }}</p>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label :class="labelClass">Senha</label>
                                <input v-model="registerForm.password" type="password" required minlength="8" autocomplete="new-password" :class="inputClass" />
                            </div>
                            <div>
                                <label :class="labelClass">Confirmar</label>
                                <input v-model="registerForm.password_confirmation" type="password" required minlength="8" autocomplete="new-password" :class="inputClass" />
                            </div>
                        </div>
                        <p v-if="registerForm.errors.password" class="-mt-3 text-xs text-red-700">{{ registerForm.errors.password }}</p>
                        <button
                            type="submit"
                            class="sf-btn-gold w-full px-6 py-4 text-xs font-semibold tracking-[0.25em] uppercase disabled:opacity-60"
                            :disabled="registerForm.processing"
                        >
                            {{ registerForm.processing ? 'Criando…' : 'Criar conta' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </StorefrontLayout>
</template>
