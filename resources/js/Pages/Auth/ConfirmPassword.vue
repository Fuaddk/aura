<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const form = useForm({ password: '' });
const showPassword = ref(false);
const mounted = ref(false);

onMounted(() => { setTimeout(() => { mounted.value = true; }, 50); });

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Bekræft adgangskode – Aura" />

    <div class="auth-page">
        <!-- Left panel -->
        <div class="auth-left">
            <div class="auth-left-inner" :class="{ 'anim-ready': mounted }">
                <div class="auth-brand">
                    <img src="/logo.png" alt="Aura" class="auth-logo" />
                </div>
                <div class="auth-hero">
                    <h1 class="auth-hero-title">
                        Sikkerhed<br>
                        <span class="auth-hero-accent">er vores prioritet.</span>
                    </h1>
                    <p class="auth-hero-sub">
                        For at beskytte din konto beder vi dig bekræfte din identitet, inden du får adgang til dette område.
                    </p>
                </div>
                <div class="auth-features">
                    <div class="auth-feature">
                        <div class="auth-feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.661 2.237a.531.531 0 0 1 .678 0 11.947 11.947 0 0 0 7.078 2.749.5.5 0 0 1 .479.425c.069.52.104 1.05.104 1.589 0 5.162-3.26 9.563-7.834 11.256a.48.48 0 0 1-.332 0C5.26 16.563 2 12.162 2 7c0-.538.035-1.069.104-1.589a.5.5 0 0 1 .48-.425 11.947 11.947 0 0 0 7.077-2.749Z" clip-rule="evenodd" /></svg>
                        </div>
                        <div>
                            <p class="auth-feature-title">Beskyttet område</p>
                            <p class="auth-feature-desc">Bekræftelse kræves for adgang</p>
                        </div>
                    </div>
                    <div class="auth-feature">
                        <div class="auth-feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" /></svg>
                        </div>
                        <div>
                            <p class="auth-feature-title">Dine data er sikre</p>
                            <p class="auth-feature-desc">Krypteret og fortroligt</p>
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
                    <h2 class="auth-form-title">Bekræft adgangskode</h2>
                    <p class="auth-form-sub">Dette er et sikkert område — bekræft venligst din adgangskode for at fortsætte</p>
                </div>

                <form @submit.prevent="submit" class="auth-form">
                    <div class="auth-field">
                        <label for="password" class="auth-label">Adgangskode</label>
                        <div class="auth-input-wrap">
                            <span class="auth-input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" /></svg>
                            </span>
                            <input id="password" :type="showPassword ? 'text' : 'password'" v-model="form.password" class="auth-input" :class="{ 'auth-input-error': form.errors.password }" placeholder="••••••••" required autofocus autocomplete="current-password" />
                            <button type="button" @click="showPassword = !showPassword" class="auth-pw-toggle" tabindex="-1">
                                <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" /><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" /></svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd" /><path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z" /></svg>
                            </button>
                        </div>
                        <InputError :message="form.errors.password" class="auth-error" />
                    </div>

                    <button type="submit" class="auth-submit" :disabled="form.processing">
                        <span v-if="!form.processing">Bekræft adgangskode</span>
                        <span v-else class="auth-spinner-wrap"><span class="auth-spinner"></span>Bekræfter…</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
