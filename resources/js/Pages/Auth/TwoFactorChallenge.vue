<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const useRecovery = ref(false);
const mounted = ref(false);

onMounted(() => {
    setTimeout(() => { mounted.value = true; }, 50);
});

const form = useForm({
    code: '',
    recovery_code: '',
});

const toggleMode = () => {
    useRecovery.value = !useRecovery.value;
    form.code = '';
    form.recovery_code = '';
    form.clearErrors();
};

const submit = () => {
    form.post(route('two-factor.challenge'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Totrinsgodkendelse – Aura" />

    <div class="tfc-page">
        <div class="tfc-card" :class="{ 'anim-ready': mounted }">

            <div class="tfc-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z" clip-rule="evenodd" />
                </svg>
            </div>

            <h2 class="tfc-title">Totrinsgodkendelse</h2>

            <p class="tfc-description" v-if="!useRecovery">
                Indtast den 6-cifrede kode fra din Google Authenticator-app.
            </p>
            <p class="tfc-description" v-else>
                Indtast en af dine gendannelseskoder.
            </p>

            <form @submit.prevent="submit" class="tfc-form">
                <!-- TOTP code -->
                <div v-if="!useRecovery" class="tfc-field">
                    <label for="code" class="tfc-label">Bekræftelseskode</label>
                    <input
                        id="code"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        v-model="form.code"
                        class="tfc-input tfc-input-code"
                        :class="{ 'tfc-input-error': form.errors.code }"
                        placeholder="000000"
                        autofocus
                        autocomplete="one-time-code"
                    />
                    <InputError :message="form.errors.code" class="tfc-error" />
                </div>

                <!-- Recovery code -->
                <div v-else class="tfc-field">
                    <label for="recovery_code" class="tfc-label">Gendannelseskode</label>
                    <input
                        id="recovery_code"
                        type="text"
                        v-model="form.recovery_code"
                        class="tfc-input"
                        :class="{ 'tfc-input-error': form.errors.code }"
                        placeholder="xxxxxxxxxx"
                        autofocus
                    />
                    <InputError :message="form.errors.code" class="tfc-error" />
                </div>

                <button
                    type="submit"
                    class="tfc-submit"
                    :disabled="form.processing"
                >
                    <span v-if="!form.processing">Bekræft</span>
                    <span v-else class="tfc-spinner-wrap">
                        <span class="tfc-spinner"></span>
                        Bekræfter…
                    </span>
                </button>
            </form>

            <button type="button" @click="toggleMode" class="tfc-toggle">
                <template v-if="!useRecovery">Brug en gendannelseskode i stedet</template>
                <template v-else>Brug bekræftelseskode i stedet</template>
            </button>

        </div>
    </div>
</template>

<style scoped>
.tfc-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f9fafb;
    padding: 2rem 1rem;
}

.tfc-card {
    width: 100%;
    max-width: 24rem;
    background: #fff;
    border-radius: 1rem;
    padding: 2.5rem 2rem;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
    text-align: center;
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.6s ease, transform 0.6s ease;
}

.tfc-card.anim-ready {
    opacity: 1;
    transform: translateY(0);
}

.tfc-icon {
    width: 3.5rem;
    height: 3.5rem;
    margin: 0 auto 1.25rem;
    background: linear-gradient(135deg, #ede9fe, #e0e7ff);
    border-radius: 0.875rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.tfc-icon svg {
    width: 1.5rem;
    height: 1.5rem;
    color: #7E75CE;
}

.tfc-title {
    font-size: 1.375rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 0.5rem;
    letter-spacing: -0.02em;
}

.tfc-description {
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 1.5rem;
    line-height: 1.55;
}

.tfc-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.tfc-field {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    text-align: left;
}

.tfc-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
}

.tfc-input {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 0.625rem;
    font-size: 0.9375rem;
    color: #111827;
    background: #f9fafb;
    outline: none;
    transition: all 0.15s ease;
    font-family: inherit;
}

.tfc-input-code {
    text-align: center;
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: 0.5em;
    padding-left: 1.5em;
    font-family: 'SF Mono', 'Fira Code', 'Cascadia Code', monospace;
}

.tfc-input:focus {
    border-color: #7E75CE;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(126, 117, 206, 0.12);
}

.tfc-input-error {
    border-color: #fca5a5;
}

.tfc-input-error:focus {
    border-color: #ef4444;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.tfc-error {
    font-size: 0.8125rem;
    color: #ef4444;
}

.tfc-submit {
    width: 100%;
    padding: 0.875rem;
    background: linear-gradient(135deg, #5BC4E8 0%, #7E75CE 50%, #D9609A 100%);
    color: #fff;
    font-size: 0.9375rem;
    font-weight: 600;
    border: none;
    border-radius: 0.75rem;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px rgba(126, 117, 206, 0.4);
}

.tfc-submit:hover:not(:disabled) {
    background: linear-gradient(135deg, #45b8e0 0%, #6e66c0 50%, #cc4f8e 100%);
    box-shadow: 0 6px 20px rgba(126, 117, 206, 0.55);
    transform: translateY(-1px);
}

.tfc-submit:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.tfc-spinner-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.625rem;
}

.tfc-spinner {
    width: 1rem;
    height: 1rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: #fff;
    border-radius: 9999px;
    animation: tfc-spin 0.7s linear infinite;
}

@keyframes tfc-spin {
    to { transform: rotate(360deg); }
}

.tfc-toggle {
    margin-top: 1.25rem;
    background: none;
    border: none;
    color: #7E75CE;
    font-size: 0.8125rem;
    font-weight: 500;
    cursor: pointer;
    transition: color 0.15s;
}

.tfc-toggle:hover {
    color: #5d55b0;
    text-decoration: underline;
}
</style>
