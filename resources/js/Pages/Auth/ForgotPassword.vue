<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

defineProps({ status: { type: String } });

const form = useForm({ email: '' });
const mounted = ref(false);
onMounted(() => { setTimeout(() => { mounted.value = true; }, 50); });

const submit = () => { form.post(route('password.email')); };
</script>

<template>
    <Head title="Glemt adgangskode – Aura" />

    <div class="auth-page">
        <!-- Left panel -->
        <div class="auth-left">
            <div class="auth-left-inner" :class="{ 'anim-ready': mounted }">
                <div class="auth-brand">
                    <img src="/logo.png" alt="Aura" class="auth-logo" />
                </div>
                <div class="auth-hero">
                    <h1 class="auth-hero-title">
                        Ingen grund til bekymring —<br>
                        <span class="auth-hero-accent">vi hjælper dig videre.</span>
                    </h1>
                    <p class="auth-hero-sub">
                        Indtast din email, så sender vi dig et link til at nulstille din adgangskode. Det tager kun et øjeblik.
                    </p>
                </div>
                <div class="auth-features">
                    <div class="auth-feature">
                        <div class="auth-feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 0 0-2 2v1.161l8.441 4.221a1.25 1.25 0 0 0 1.118 0L19 7.162V6a2 2 0 0 0-2-2H3Z" /><path d="m19 8.839-7.77 3.885a2.75 2.75 0 0 1-2.46 0L1 8.839V14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.839Z" /></svg>
                        </div>
                        <div>
                            <p class="auth-feature-title">Link sendes til din email</p>
                            <p class="auth-feature-desc">Tjek også din spam-mappe</p>
                        </div>
                    </div>
                    <div class="auth-feature">
                        <div class="auth-feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" /></svg>
                        </div>
                        <div>
                            <p class="auth-feature-title">Sikkert og krypteret</p>
                            <p class="auth-feature-desc">Linket udløber efter 60 minutter</p>
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
                <Link :href="route('login')" class="auth-back">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10Z" clip-rule="evenodd" /></svg>
                    Tilbage til log ind
                </Link>

                <div class="auth-form-header">
                    <h2 class="auth-form-title">Glemt adgangskode?</h2>
                    <p class="auth-form-sub">Vi sender dig et nulstillingslink med det samme</p>
                </div>

                <div v-if="status" class="auth-status">{{ status }}</div>

                <form @submit.prevent="submit" class="auth-form">
                    <div class="auth-field">
                        <label for="email" class="auth-label">Email</label>
                        <div class="auth-input-wrap">
                            <span class="auth-input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 0 0-2 2v1.161l8.441 4.221a1.25 1.25 0 0 0 1.118 0L19 7.162V6a2 2 0 0 0-2-2H3Z" /><path d="m19 8.839-7.77 3.885a2.75 2.75 0 0 1-2.46 0L1 8.839V14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.839Z" /></svg>
                            </span>
                            <input id="email" type="email" v-model="form.email" class="auth-input" :class="{ 'auth-input-error': form.errors.email }" placeholder="din@email.dk" required autofocus autocomplete="username" />
                        </div>
                        <InputError :message="form.errors.email" class="auth-error" />
                    </div>

                    <button type="submit" class="auth-submit" :disabled="form.processing">
                        <span v-if="!form.processing">Send nulstillingslink</span>
                        <span v-else class="auth-spinner-wrap"><span class="auth-spinner"></span>Sender…</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
