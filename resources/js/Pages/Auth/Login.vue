<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);
const mounted = ref(false);

onMounted(() => {
    setTimeout(() => { mounted.value = true; }, 50);
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log ind – Aura" />

    <div class="login-page">

        <!-- Left panel – branding -->
        <div class="login-left">
            <div class="login-left-inner" :class="{ 'anim-ready': mounted }">

                <div class="login-brand">
                    <img src="/logo.png" alt="Aura" class="login-logo" />
                </div>

                <!-- Chat preview animation -->
                <div class="chat-demo">
                    <div class="chat-demo-header">
                        <div class="chat-demo-status-dot"></div>
                        <span class="chat-demo-header-title">Aura</span>
                        <span class="chat-demo-online">online</span>
                    </div>
                    <div class="chat-demo-body">
                        <div class="chat-demo-row chat-demo-row-ai msg-appear-1">
                            <div class="chat-demo-avatar">A</div>
                            <div class="chat-demo-bubble chat-demo-bubble-ai">
                                Hej! Jeg er Aura. Hvad kan jeg hjælpe dig med i dag?
                            </div>
                        </div>
                        <div class="chat-demo-row chat-demo-row-user msg-appear-2">
                            <div class="chat-demo-bubble chat-demo-bubble-user">
                                Jeg skal skilles – hvad gør jeg nu?
                            </div>
                        </div>
                        <div class="chat-demo-row chat-demo-row-ai msg-appear-3">
                            <div class="chat-demo-avatar">A</div>
                            <div class="chat-demo-bubble chat-demo-bubble-ai">
                                Jeg hører dig. Første skridt er at kontakte Familieretshuset og søge om separation – de guider dig igennem hele processen.
                            </div>
                        </div>

                        <!-- Tasks created indicator -->
                        <div class="tasks-created-header msg-appear-4">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/></svg>
                            Aura oprettede 3 opgaver til dig
                        </div>

                        <!-- Task cards -->
                        <div class="demo-task-card task-appear-1">
                            <div class="demo-task-dot demo-task-dot-high"></div>
                            <div class="demo-task-info">
                                <span class="demo-task-title">Kontakt Familieretshuset</span>
                                <span class="demo-task-due">Frist: om 7 dage</span>
                            </div>
                            <span class="demo-task-badge demo-task-badge-high">Høj</span>
                        </div>
                        <div class="demo-task-card task-appear-2">
                            <div class="demo-task-dot demo-task-dot-high"></div>
                            <div class="demo-task-info">
                                <span class="demo-task-title">Søg om separation online</span>
                                <span class="demo-task-due">Frist: om 14 dage</span>
                            </div>
                            <span class="demo-task-badge demo-task-badge-high">Høj</span>
                        </div>
                        <div class="demo-task-card task-appear-3">
                            <div class="demo-task-dot demo-task-dot-med"></div>
                            <div class="demo-task-info">
                                <span class="demo-task-title">Book møde med advokat</span>
                                <span class="demo-task-due">Frist: om 30 dage</span>
                            </div>
                            <span class="demo-task-badge demo-task-badge-med">Normal</span>
                        </div>
                    </div>
                    <div class="chat-demo-footer">
                        <span class="chat-demo-prompt">Stil et spørgsmål til Aura…</span>
                        <div class="chat-demo-send-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3.105 2.289a.75.75 0 0 0-.826.95l1.414 4.925A1.5 1.5 0 0 0 5.135 9.25h6.115a.75.75 0 0 1 0 1.5H5.135a1.5 1.5 0 0 0-1.442 1.086l-1.414 4.926a.75.75 0 0 0 .826.95 28.896 28.896 0 0 0 15.293-7.154.75.75 0 0 0 0-1.115A28.897 28.897 0 0 0 3.105 2.289Z"/></svg>
                        </div>
                    </div>
                </div>

                <p class="login-tagline">
                    Navigér processen med empati, overblik og konkret juridisk vejledning.
                </p>

                <!-- Decorative blobs -->
                <div class="login-blob login-blob-1" aria-hidden="true"></div>
                <div class="login-blob login-blob-2" aria-hidden="true"></div>
            </div>
        </div>

        <!-- Right panel – form -->
        <div class="login-right">
            <div class="login-form-wrap" :class="{ 'anim-ready': mounted }">

                <div class="login-form-header">
                    <h2 class="login-form-title">Velkommen tilbage</h2>
                    <p class="login-form-sub">Log ind for at fortsætte med Aura</p>
                </div>

                <div v-if="status" class="login-status">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="login-form">

                    <!-- Email -->
                    <div class="login-field">
                        <label for="email" class="login-label">Email</label>
                        <div class="login-input-wrap">
                            <span class="login-input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M3 4a2 2 0 0 0-2 2v1.161l8.441 4.221a1.25 1.25 0 0 0 1.118 0L19 7.162V6a2 2 0 0 0-2-2H3Z" />
                                    <path d="m19 8.839-7.77 3.885a2.75 2.75 0 0 1-2.46 0L1 8.839V14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.839Z" />
                                </svg>
                            </span>
                            <input
                                id="email"
                                type="email"
                                v-model="form.email"
                                class="login-input"
                                :class="{ 'login-input-error': form.errors.email }"
                                placeholder="din@email.dk"
                                required
                                autofocus
                                autocomplete="username"
                            />
                        </div>
                        <InputError :message="form.errors.email" class="login-error" />
                    </div>

                    <!-- Password -->
                    <div class="login-field">
                        <div class="login-label-row">
                            <label for="password" class="login-label">Adgangskode</label>
                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="login-forgot"
                            >
                                Glemt adgangskode?
                            </Link>
                        </div>
                        <div class="login-input-wrap">
                            <span class="login-input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                v-model="form.password"
                                class="login-input"
                                :class="{ 'login-input-error': form.errors.password }"
                                placeholder="••••••••"
                                required
                                autocomplete="current-password"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="login-pw-toggle"
                                tabindex="-1"
                            >
                                <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" />
                                    <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" />
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd" />
                                    <path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z" />
                                </svg>
                            </button>
                        </div>
                        <InputError :message="form.errors.password" class="login-error" />
                    </div>

                    <!-- Remember me -->
                    <div class="login-remember">
                        <label class="login-remember-label">
                            <Checkbox name="remember" v-model:checked="form.remember" />
                            <span>Husk mig</span>
                        </label>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        class="login-submit"
                        :class="{ 'login-submit-loading': form.processing }"
                        :disabled="form.processing"
                    >
                        <span v-if="!form.processing">Log ind</span>
                        <span v-else class="login-spinner-wrap">
                            <span class="login-spinner"></span>
                            Logger ind…
                        </span>
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
                    Fortsæt med Google
                </a>

                <p class="login-register-cta">
                    Har du ikke en konto?
                    <Link :href="route('register')" class="login-register-link">Opret gratis konto</Link>
                </p>

            </div>
        </div>
    </div>
</template>

<style scoped>
/* ─── Page layout ─────────────────────────────────────── */
.login-page {
    display: flex;
    min-height: 100vh;
    background: #fff;
}

/* ─── Left panel ──────────────────────────────────────── */
.login-left {
    display: none;
    position: relative;
    overflow: hidden;
    background: linear-gradient(145deg, #1a3a5c 0%, #2d2060 35%, #4a1a4a 70%, #3d1030 100%);
}

@media (min-width: 768px) {
    .login-left {
        display: flex;
        width: 45%;
        flex-shrink: 0;
    }
}

@media (min-width: 1024px) {
    .login-left { width: 50%; }
}

.login-left-inner {
    position: relative;
    z-index: 10;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    padding: 2rem 2rem 2rem;
    width: 100%;
    height: 100%;
    opacity: 0;
    transform: translateX(-20px);
    transition: opacity 0.7s ease, transform 0.7s ease;
}

.login-left-inner.anim-ready {
    opacity: 1;
    transform: translateX(0);
}

.login-brand {
    display: flex;
    align-items: center;
}

.login-logo {
    height: 3rem;
    width: auto;
    filter: brightness(0) invert(1) opacity(0.9);
}

/* ── Chat demo ──────────────────────────────────────────── */
.chat-demo {
    background: rgba(255,255,255,0.07);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.13);
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0,0,0,0.28), inset 0 1px 0 rgba(255,255,255,0.08);
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.chat-demo-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    background: rgba(255,255,255,0.05);
    border-bottom: 1px solid rgba(255,255,255,0.07);
    flex-shrink: 0;
}

.chat-demo-status-dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 9999px;
    background: #4ade80;
    box-shadow: 0 0 6px rgba(74,222,128,0.6);
    flex-shrink: 0;
}

.chat-demo-header-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: rgba(255,255,255,0.9);
    flex: 1;
}

.chat-demo-online {
    font-size: 0.6875rem;
    color: rgba(255,255,255,0.38);
}

.chat-demo-body {
    flex: 1;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    overflow: hidden;
}

.chat-demo-row {
    display: flex;
    align-items: flex-end;
    gap: 0.5rem;
}

.chat-demo-row-user { flex-direction: row-reverse; }

.chat-demo-avatar {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 9999px;
    background: linear-gradient(135deg, #7E75CE, #5BC4E8);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6875rem;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
}

.chat-demo-bubble {
    max-width: 78%;
    padding: 0.5625rem 0.875rem;
    border-radius: 1rem;
    font-size: 0.8125rem;
    line-height: 1.55;
}

.chat-demo-bubble-ai {
    background: rgba(255,255,255,0.11);
    color: rgba(255,255,255,0.88);
    border-bottom-left-radius: 0.3rem;
}

.chat-demo-bubble-user {
    background: linear-gradient(135deg, #7E75CE, #5BC4E8);
    color: #fff;
    border-bottom-right-radius: 0.3rem;
}

.chat-demo-typing-dots {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.625rem 0.875rem;
    background: rgba(255,255,255,0.11);
    border-radius: 1rem;
    border-bottom-left-radius: 0.3rem;
}

.chat-demo-typing-dots span {
    width: 0.375rem;
    height: 0.375rem;
    border-radius: 9999px;
    background: rgba(255,255,255,0.55);
    animation: typing-bounce 1.4s ease-in-out infinite;
}

.chat-demo-typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.chat-demo-typing-dots span:nth-child(3) { animation-delay: 0.4s; }

@keyframes typing-bounce {
    0%, 60%, 100% { transform: translateY(0); opacity: 0.55; }
    30%            { transform: translateY(-5px); opacity: 1; }
}

.chat-demo-footer {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 0.875rem;
    background: rgba(255,255,255,0.04);
    border-top: 1px solid rgba(255,255,255,0.07);
    flex-shrink: 0;
}

.chat-demo-prompt {
    flex: 1;
    font-size: 0.8125rem;
    color: rgba(255,255,255,0.3);
    font-style: italic;
}

.chat-demo-send-btn {
    width: 1.875rem;
    height: 1.875rem;
    border-radius: 0.5rem;
    background: linear-gradient(135deg, #7E75CE, #5BC4E8);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    opacity: 0.6;
}

.chat-demo-send-btn svg {
    width: 0.9375rem;
    height: 0.9375rem;
    color: #fff;
}

/* Message appear animations */
.msg-appear-1 { animation: msg-fade-in 0.45s ease both; animation-delay: 0.5s; }
.msg-appear-2 { animation: msg-fade-in 0.45s ease both; animation-delay: 1.4s; }
.msg-appear-3 { animation: msg-fade-in 0.45s ease both; animation-delay: 2.5s; }
.msg-appear-4 { animation: msg-fade-in 0.4s ease both;  animation-delay: 3.7s; }

/* Task cards appear */
.task-appear-1 { animation: task-slide-in 0.4s cubic-bezier(0.34,1.56,0.64,1) both; animation-delay: 4.3s; }
.task-appear-2 { animation: task-slide-in 0.4s cubic-bezier(0.34,1.56,0.64,1) both; animation-delay: 4.85s; }
.task-appear-3 { animation: task-slide-in 0.4s cubic-bezier(0.34,1.56,0.64,1) both; animation-delay: 5.4s; }

@keyframes msg-fade-in {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

@keyframes task-slide-in {
    from { opacity: 0; transform: translateX(-12px) scale(0.97); }
    to   { opacity: 1; transform: translateX(0) scale(1); }
}

/* Tasks created header */
.tasks-created-header {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: rgba(255,255,255,0.5);
    letter-spacing: 0.03em;
    padding: 0.125rem 0;
    margin-top: 0.125rem;
}

.tasks-created-header svg {
    width: 0.875rem;
    height: 0.875rem;
    color: #4ade80;
    flex-shrink: 0;
}

/* Task card */
.demo-task-card {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.625rem;
    padding: 0.5rem 0.75rem;
    cursor: default;
    transition: background 0.15s;
}

.demo-task-dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 9999px;
    flex-shrink: 0;
}

.demo-task-dot-high { background: #f87171; box-shadow: 0 0 5px rgba(248,113,113,0.5); }
.demo-task-dot-med  { background: #fbbf24; box-shadow: 0 0 5px rgba(251,191,36,0.5); }

.demo-task-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.demo-task-title {
    font-size: 0.75rem;
    font-weight: 600;
    color: rgba(255,255,255,0.88);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.demo-task-due {
    font-size: 0.625rem;
    color: rgba(255,255,255,0.38);
}

.demo-task-badge {
    font-size: 0.5625rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.1875rem 0.4375rem;
    border-radius: 9999px;
    flex-shrink: 0;
}

.demo-task-badge-high {
    background: rgba(248,113,113,0.18);
    color: #fca5a5;
    border: 1px solid rgba(248,113,113,0.25);
}

.demo-task-badge-med {
    background: rgba(251,191,36,0.15);
    color: #fcd34d;
    border: 1px solid rgba(251,191,36,0.2);
}

/* Tagline */
.login-tagline {
    color: rgba(255,255,255,0.5);
    font-size: 0.875rem;
    line-height: 1.65;
    max-width: 22rem;
    margin: 0;
    flex-shrink: 0;
}

/* Decorative blobs */
.login-blob {
    position: absolute;
    border-radius: 9999px;
    filter: blur(60px);
    pointer-events: none;
}

.login-blob-1 {
    width: 20rem;
    height: 20rem;
    background: rgba(91, 196, 232, 0.22);
    top: -5rem;
    right: -5rem;
    animation: blob-drift 8s ease-in-out infinite;
}

.login-blob-2 {
    width: 16rem;
    height: 16rem;
    background: rgba(217, 96, 154, 0.2);
    bottom: 0;
    left: -4rem;
    animation: blob-drift 10s ease-in-out infinite reverse;
}

@keyframes blob-drift {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33%       { transform: translate(20px, -15px) scale(1.05); }
    66%       { transform: translate(-10px, 10px) scale(0.95); }
}

/* ─── Right panel ─────────────────────────────────────── */
.login-right {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1.5rem;
    background: #fff;
}

.login-form-wrap {
    width: 100%;
    max-width: 24rem;
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.6s ease 0.15s, transform 0.6s ease 0.15s;
}

.login-form-wrap.anim-ready {
    opacity: 1;
    transform: translateY(0);
}

/* Form header */
.login-form-header {
    margin-bottom: 2rem;
}

.login-form-title {
    font-size: 1.625rem;
    font-weight: 700;
    color: #111827;
    letter-spacing: -0.02em;
    margin-bottom: 0.375rem;
}

.login-form-sub {
    font-size: 0.9375rem;
    color: #6b7280;
}

/* Status */
.login-status {
    margin-bottom: 1rem;
    padding: 0.75rem 1rem;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 0.625rem;
    font-size: 0.875rem;
    color: #15803d;
}

/* Form */
.login-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.login-field {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.login-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.login-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
}

.login-forgot {
    font-size: 0.8125rem;
    color: #7E75CE;
    text-decoration: none;
    transition: color 0.15s;
}

.login-forgot:hover {
    color: #5d55b0;
    text-decoration: underline;
}

/* Input wrapper */
.login-input-wrap {
    position: relative;
}

.login-input-icon {
    position: absolute;
    left: 0.875rem;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    pointer-events: none;
    display: flex;
}

.login-input-icon svg {
    width: 1rem;
    height: 1rem;
}

.login-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 0.625rem;
    font-size: 0.9375rem;
    color: #111827;
    background: #f9fafb;
    outline: none;
    transition: all 0.15s ease;
    font-family: inherit;
}

.login-input:focus {
    border-color: #7E75CE;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(126, 117, 206, 0.12);
}

.login-input-error {
    border-color: #fca5a5;
    background: #fff;
}

.login-input-error:focus {
    border-color: #ef4444;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.login-error {
    font-size: 0.8125rem;
    color: #ef4444;
    margin-top: 0.125rem;
}

/* Password toggle */
.login-pw-toggle {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    padding: 0.25rem;
    color: #9ca3af;
    background: none;
    border: none;
    cursor: pointer;
    border-radius: 0.375rem;
    display: flex;
    transition: color 0.15s;
}

.login-pw-toggle:hover { color: #6b7280; }

.login-pw-toggle svg {
    width: 1.125rem;
    height: 1.125rem;
}

/* Remember me */
.login-remember {
    display: flex;
    align-items: center;
}

.login-remember-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: #6b7280;
    cursor: pointer;
}

/* Submit button */
.login-submit {
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
    letter-spacing: 0.01em;
    box-shadow: 0 4px 14px rgba(126, 117, 206, 0.4);
    margin-top: 0.25rem;
}

.login-submit:hover:not(:disabled) {
    background: linear-gradient(135deg, #45b8e0 0%, #6e66c0 50%, #cc4f8e 100%);
    box-shadow: 0 6px 20px rgba(126, 117, 206, 0.55);
    transform: translateY(-1px);
}

.login-submit:active:not(:disabled) {
    transform: translateY(0);
}

.login-submit:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.login-spinner-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.625rem;
}

.login-spinner {
    width: 1rem;
    height: 1rem;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 9999px;
    animation: spin 0.7s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Social divider */
.social-divider {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 1.25rem 0;
    color: #d1d5db;
    font-size: 0.8125rem;
    color: #9ca3af;
}

.social-divider::before,
.social-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #e5e7eb;
}

/* Google button */
.social-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.625rem;
    width: 100%;
    padding: 0.75rem 1rem;
    background: #fff;
    border: 1.5px solid #e5e7eb;
    border-radius: 0.75rem;
    font-size: 0.9375rem;
    font-weight: 500;
    color: #374151;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.15s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    margin-bottom: 0.25rem;
}

.social-btn:hover {
    background: #f9fafb;
    border-color: #d1d5db;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transform: translateY(-1px);
}

.social-btn-icon {
    width: 1.25rem;
    height: 1.25rem;
    flex-shrink: 0;
}

/* Register CTA */
.login-register-cta {
    margin-top: 1.75rem;
    text-align: center;
    font-size: 0.875rem;
    color: #6b7280;
}

.login-register-link {
    color: #7E75CE;
    font-weight: 600;
    text-decoration: none;
    transition: color 0.15s;
}

.login-register-link:hover {
    color: #5d55b0;
    text-decoration: underline;
}
</style>
