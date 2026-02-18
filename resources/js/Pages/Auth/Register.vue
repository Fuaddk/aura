<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);
const showConfirm = ref(false);
const mounted = ref(false);

onMounted(() => { setTimeout(() => { mounted.value = true; }, 50); });

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Opret konto – Aura" />

    <div class="auth-page">
        <!-- Left panel -->
        <div class="auth-left">
            <div class="auth-left-inner" :class="{ 'anim-ready': mounted }">
                <div class="auth-brand">
                    <img src="/logo.png" alt="Aura" class="auth-logo" />
                </div>
                <div class="auth-hero">
                    <h1 class="auth-hero-title">
                        Tag det første skridt<br>
                        <span class="auth-hero-accent">mod en ny begyndelse.</span>
                    </h1>
                    <p class="auth-hero-sub">
                        Opret en gratis konto og få adgang til din personlige assistent — klar til at guide dig trygt igennem processen.
                    </p>
                </div>
                <div class="auth-features">
                    <div class="auth-feature">
                        <div class="auth-feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" /></svg>
                        </div>
                        <div>
                            <p class="auth-feature-title">Fortrolig & sikker</p>
                            <p class="auth-feature-desc">Dine oplysninger er beskyttet</p>
                        </div>
                    </div>
                    <div class="auth-feature">
                        <div class="auth-feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM6 8a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM1.49 15.326a.78.78 0 0 1-.358-.442 3 3 0 0 1 4.308-3.516 6.484 6.484 0 0 0-1.905 3.959c-.023.222-.014.442.025.654a4.97 4.97 0 0 1-2.07-.655ZM16.44 15.98a4.97 4.97 0 0 0 2.07-.654.78.78 0 0 0 .357-.442 3 3 0 0 0-4.308-3.517 6.484 6.484 0 0 1 1.907 3.96 2.32 2.32 0 0 1-.026.654ZM18 8a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM5.304 16.19a.844.844 0 0 1-.277-.71 5 5 0 0 1 9.947 0 .843.843 0 0 1-.277.71A6.975 6.975 0 0 1 10 18a6.974 6.974 0 0 1-4.696-1.81Z" /></svg>
                        </div>
                        <div>
                            <p class="auth-feature-title">Gratis at komme i gang</p>
                            <p class="auth-feature-desc">Ingen betalingskort kræves</p>
                        </div>
                    </div>
                    <div class="auth-feature">
                        <div class="auth-feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd" /></svg>
                        </div>
                        <div>
                            <p class="auth-feature-title">Klar på 2 minutter</p>
                            <p class="auth-feature-desc">Hurtig og nem oprettelse</p>
                        </div>
                    </div>
                </div>
                <div class="auth-blob auth-blob-1" aria-hidden="true"></div>
                <div class="auth-blob auth-blob-2" aria-hidden="true"></div>
            </div>
        </div>

        <!-- Right panel -->
        <div class="auth-right">
            <div class="auth-form-wrap" :class="{ 'anim-ready': mounted }">
                <div class="auth-form-header">
                    <h2 class="auth-form-title">Opret gratis konto</h2>
                    <p class="auth-form-sub">Kom i gang med Aura i dag</p>
                </div>

                <form @submit.prevent="submit" class="auth-form">
                    <!-- Name -->
                    <div class="auth-field">
                        <label for="name" class="auth-label">Navn</label>
                        <div class="auth-input-wrap">
                            <span class="auth-input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3.465 14.493a1.23 1.23 0 0 0 .41 1.412A9.957 9.957 0 0 0 10 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 0 0-13.074.003Z" /></svg>
                            </span>
                            <input id="name" type="text" v-model="form.name" class="auth-input" :class="{ 'auth-input-error': form.errors.name }" placeholder="Dit fulde navn" required autofocus autocomplete="name" />
                        </div>
                        <InputError :message="form.errors.name" class="auth-error" />
                    </div>

                    <!-- Email -->
                    <div class="auth-field">
                        <label for="email" class="auth-label">Email</label>
                        <div class="auth-input-wrap">
                            <span class="auth-input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 0 0-2 2v1.161l8.441 4.221a1.25 1.25 0 0 0 1.118 0L19 7.162V6a2 2 0 0 0-2-2H3Z" /><path d="m19 8.839-7.77 3.885a2.75 2.75 0 0 1-2.46 0L1 8.839V14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.839Z" /></svg>
                            </span>
                            <input id="email" type="email" v-model="form.email" class="auth-input" :class="{ 'auth-input-error': form.errors.email }" placeholder="din@email.dk" required autocomplete="username" />
                        </div>
                        <InputError :message="form.errors.email" class="auth-error" />
                    </div>

                    <!-- Password -->
                    <div class="auth-field">
                        <label for="password" class="auth-label">Adgangskode</label>
                        <div class="auth-input-wrap">
                            <span class="auth-input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" /></svg>
                            </span>
                            <input id="password" :type="showPassword ? 'text' : 'password'" v-model="form.password" class="auth-input" :class="{ 'auth-input-error': form.errors.password }" placeholder="••••••••" required autocomplete="new-password" />
                            <button type="button" @click="showPassword = !showPassword" class="auth-pw-toggle" tabindex="-1">
                                <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" /><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" /></svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd" /><path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z" /></svg>
                            </button>
                        </div>
                        <InputError :message="form.errors.password" class="auth-error" />
                    </div>

                    <!-- Confirm Password -->
                    <div class="auth-field">
                        <label for="password_confirmation" class="auth-label">Bekræft adgangskode</label>
                        <div class="auth-input-wrap">
                            <span class="auth-input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" /></svg>
                            </span>
                            <input id="password_confirmation" :type="showConfirm ? 'text' : 'password'" v-model="form.password_confirmation" class="auth-input" :class="{ 'auth-input-error': form.errors.password_confirmation }" placeholder="••••••••" required autocomplete="new-password" />
                            <button type="button" @click="showConfirm = !showConfirm" class="auth-pw-toggle" tabindex="-1">
                                <svg v-if="!showConfirm" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" /><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" /></svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd" /><path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z" /></svg>
                            </button>
                        </div>
                        <InputError :message="form.errors.password_confirmation" class="auth-error" />
                    </div>

                    <button type="submit" class="auth-submit" :class="{ 'auth-submit-loading': form.processing }" :disabled="form.processing">
                        <span v-if="!form.processing">Opret konto</span>
                        <span v-else class="auth-spinner-wrap"><span class="auth-spinner"></span>Opretter konto…</span>
                    </button>
                </form>

                <div class="social-divider"><span>eller</span></div>

                <a :href="route('social.google.redirect')" class="social-btn">
                    <svg class="social-btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
                        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                    </svg>
                    Opret konto med Google
                </a>

                <p class="auth-cta">
                    Har du allerede en konto?
                    <Link :href="route('login')" class="auth-cta-link">Log ind her</Link>
                </p>
            </div>
        </div>
    </div>
</template>

