<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const props = defineProps({ status: { type: String } });

const form = useForm({});
const mounted = ref(false);

onMounted(() => { setTimeout(() => { mounted.value = true; }, 50); });

const submit = () => { form.post(route('verification.send')); };

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <Head title="Bekræft email – Aura" />

    <div class="auth-page">
        <!-- Left panel -->
        <div class="auth-left">
            <div class="auth-left-inner" :class="{ 'anim-ready': mounted }">
                <div class="auth-brand">
                    <img src="/logo.png" alt="Aura" class="auth-logo" />
                </div>
                <div class="auth-hero">
                    <h1 class="auth-hero-title">
                        Næsten klar —<br>
                        <span class="auth-hero-accent">bekræft din email.</span>
                    </h1>
                    <p class="auth-hero-sub">
                        Vi har sendt et bekræftelseslink til din emailadresse. Klik på linket for at aktivere din konto og komme i gang.
                    </p>
                </div>
                <div class="auth-features">
                    <div class="auth-feature">
                        <div class="auth-feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 0 0-2 2v1.161l8.441 4.221a1.25 1.25 0 0 0 1.118 0L19 7.162V6a2 2 0 0 0-2-2H3Z" /><path d="m19 8.839-7.77 3.885a2.75 2.75 0 0 1-2.46 0L1 8.839V14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.839Z" /></svg>
                        </div>
                        <div>
                            <p class="auth-feature-title">Tjek din indbakke</p>
                            <p class="auth-feature-desc">Tjek også din spam-mappe</p>
                        </div>
                    </div>
                    <div class="auth-feature">
                        <div class="auth-feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd" /></svg>
                        </div>
                        <div>
                            <p class="auth-feature-title">Linket er gyldigt i 60 min</p>
                            <p class="auth-feature-desc">Send nyt link hvis det udløber</p>
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
                    <h2 class="auth-form-title">Bekræft din email</h2>
                    <p class="auth-form-sub">Vi har sendt et link til din emailadresse — klik på det for at fortsætte</p>
                </div>

                <div v-if="verificationLinkSent" class="auth-status">
                    Et nyt bekræftelseslink er sendt til din emailadresse.
                </div>

                <div class="auth-verify-info">
                    <div class="auth-verify-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 0 0-2 2v1.161l8.441 4.221a1.25 1.25 0 0 0 1.118 0L19 7.162V6a2 2 0 0 0-2-2H3Z" /><path d="m19 8.839-7.77 3.885a2.75 2.75 0 0 1-2.46 0L1 8.839V14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.839Z" /></svg>
                    </div>
                    <p class="auth-verify-text">
                        Tak for din tilmelding! Inden du kan starte, skal du bekræfte din emailadresse ved at klikke på linket vi sendte dig. Modtog du ikke emailen? Så sender vi dig gerne en ny.
                    </p>
                </div>

                <form @submit.prevent="submit" class="auth-form">
                    <button type="submit" class="auth-submit" :disabled="form.processing">
                        <span v-if="!form.processing">Send bekræftelseslink igen</span>
                        <span v-else class="auth-spinner-wrap"><span class="auth-spinner"></span>Sender…</span>
                    </button>
                </form>

                <div class="auth-cta">
                    Forkert konto?
                    <Link :href="route('logout')" method="post" as="button" class="auth-cta-link">Log ud</Link>
                </div>
            </div>
        </div>
    </div>
</template>
