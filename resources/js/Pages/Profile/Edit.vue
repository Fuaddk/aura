<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import ChatLayout from '@/Layouts/ChatLayout.vue';
import ChatSidebar from '@/Components/ChatSidebar.vue';

const props = defineProps({
    mustVerifyEmail: Boolean,
    status:          String,
    usagePercent:    Number,
    messagesUsed:    Number,
    messagesLimit:   Number,
    casesCount:      Number,
    tasksCount:      Number,
    cases:           { type: Array, default: () => [] },
});

const page     = usePage();
const authUser = computed(() => page.props.auth.user);

const activeSection = ref('general');

const navItems = [
    {
        id: 'general', label: 'Generelt',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd"/></svg>`,
    },
    {
        id: 'account', label: 'Konto',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3.465 14.493a1.23 1.23 0 0 0 .41 1.412A9.957 9.957 0 0 0 10 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 0 0-13.074.003Z"/></svg>`,
    },
    {
        id: 'subscription', label: 'Abonnement',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2.5 4A1.5 1.5 0 0 0 1 5.5V6h18v-.5A1.5 1.5 0 0 0 17.5 4h-15ZM19 8.5H1v6A1.5 1.5 0 0 0 2.5 16h15a1.5 1.5 0 0 0 1.5-1.5v-6ZM3 13.25a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 0 1.5h-1.5a.75.75 0 0 1-.75-.75Zm4.75-.75a.75.75 0 0 0 0 1.5h3.5a.75.75 0 0 0 0-1.5h-3.5Z" clip-rule="evenodd"/></svg>`,
    },
    {
        id: 'usage', label: 'Brug',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M15.5 2A1.5 1.5 0 0 0 14 3.5v13a1.5 1.5 0 0 0 3 0v-13A1.5 1.5 0 0 0 15.5 2ZM9.5 6A1.5 1.5 0 0 0 8 7.5v9a1.5 1.5 0 0 0 3 0v-9A1.5 1.5 0 0 0 9.5 6ZM3.5 10A1.5 1.5 0 0 0 2 11.5v5a1.5 1.5 0 0 0 3 0v-5A1.5 1.5 0 0 0 3.5 10Z"/></svg>`,
    },
];

/* ── Forms ─────────────────────────────────────────────── */
const profileForm = useForm({
    name:  authUser.value.name  || '',
    email: authUser.value.email || '',
    phone: authUser.value.phone || '',
});

const passwordForm = useForm({
    current_password:      '',
    password:              '',
    password_confirmation: '',
});

const deleteForm = useForm({ password: '' });

/* ── Subscription ───────────────────────────────────────── */
const currentPlan    = ref(authUser.value.subscription_plan || 'free');
const checkoutForm   = useForm({ plan: '' });
const cancelForm     = useForm({});
const isPaidPlan     = computed(() => currentPlan.value !== 'free');

const plans = [
    {
        id:       'free',
        name:     'Gratis',
        price:    '0',
        messages: '50 AI-beskeder/md.',
        features: ['50 AI-beskeder om måneden', '1 aktiv sag', 'Grundlæggende opgavestyring'],
        color:    '#9ca3af',
    },
    {
        id:       'pro',
        name:     'Pro',
        price:    '99',
        messages: '500 AI-beskeder/md.',
        features: ['500 AI-beskeder om måneden', 'Ubegrænset sager', 'Avancerede opgaver', 'Dokumentupload'],
        color:    '#7E75CE',
        popular:  true,
    },
    {
        id:       'business',
        name:     'Business',
        price:    '299',
        messages: 'Ubegrænset',
        features: ['Ubegrænset AI-beskeder', 'Ubegrænset sager', 'Prioritetssupport', 'API-adgang'],
        color:    '#5BC4E8',
    },
];

const startCheckout = (planId) => {
    if (planId === currentPlan.value || checkoutForm.processing) return;
    checkoutForm.plan = planId;
    checkoutForm.post(route('subscription.checkout'));
};

const cancelSubscription = () => {
    if (!confirm('Er du sikker på, at du vil annullere dit abonnement?')) return;
    cancelForm.post(route('subscription.cancel'), {
        onSuccess: () => { currentPlan.value = 'free'; },
    });
};

/* ── Wallet ─────────────────────────────────────────────── */
const walletBalance = computed(() => Number(authUser.value.wallet_balance || 0).toFixed(2));

/* ── Password visibility ────────────────────────────────── */
const showCurrentPw = ref(false);
const showNewPw     = ref(false);
const showConfirmPw = ref(false);
const showDeletePw  = ref(false);
const showDelete    = ref(false);

/* ── Helpers ────────────────────────────────────────────── */
const userInitials = computed(() => {
    const n = authUser.value.name || '';
    return n.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2) || '?';
});

const usageColor = computed(() => {
    if (props.usagePercent >= 90) return '#ef4444';
    if (props.usagePercent >= 70) return '#f59e0b';
    return '#7E75CE';
});

const planLabel = (id) => plans.find(p => p.id === id)?.name ?? id;

/* ── Extra usage toggle ─────────────────────────────────── */
const extraUsageEnabled = ref(false);

/* ── Reset date helper ──────────────────────────────────── */
const nextResetDate = computed(() => {
    const d = new Date();
    d.setMonth(d.getMonth() + 1, 1);
    return d.toLocaleDateString('da-DK', { day: 'numeric', month: 'long' });
});

const submitProfile  = () => profileForm.patch(route('profile.update'));
const submitPassword = () => passwordForm.put(route('profile.password'), { onSuccess: () => passwordForm.reset() });
const submitDelete   = () => deleteForm.delete(route('profile.destroy'));
</script>

<template>
    <Head title="Indstillinger – Aura" />

    <ChatLayout>
    <div class="chat-container">

        <!-- ── Chat sidebar (uændret) ────────────────── -->
        <ChatSidebar :cases="cases" :active-case="null" :open="true" />

        <!-- ── Profilside ────────────────────────────── -->
        <div class="chat-main">
            <div class="chat-topbar">
                <h2 class="page-topbar-title">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="topbar-icon"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                    Profil
                </h2>
            </div>

            <main class="settings-main">
            <div class="settings-layout">

                <!-- Nav-kort -->
                <nav class="settings-nav">
                    <div class="settings-nav-title">Mine indstillinger</div>
                    <ul class="settings-nav-list">
                        <li v-for="item in navItems" :key="item.id">
                            <button
                                @click="activeSection = item.id"
                                :class="['settings-nav-item', activeSection === item.id && 'settings-nav-item-active']"
                            >
                                <span class="settings-nav-icon" v-html="item.icon"></span>
                                {{ item.label }}
                            </button>
                        </li>
                    </ul>
                </nav>

                <!-- Indhold -->
                <div class="settings-content">

                <!-- Status banners -->
                <div v-if="status === 'profile-updated'"      class="s-banner s-banner-green">Profil opdateret.</div>
                <div v-if="status === 'password-updated'"     class="s-banner s-banner-green">Adgangskode opdateret.</div>
                <div v-if="status === 'subscription-updated'"   class="s-banner s-banner-purple">Abonnement opdateret til {{ planLabel(currentPlan) }}.</div>
                <div v-if="status === 'subscription-cancelled'" class="s-banner s-banner-green">Dit abonnement er annulleret. Du er nu på Gratis-planen.</div>

                <!-- ═══════════════════════════════
                     GENERELT
                ═══════════════════════════════ -->
                <template v-if="activeSection === 'general'">
                    <h2 class="s-section-title">Profil</h2>

                    <div class="s-block">
                        <form @submit.prevent="submitProfile" class="s-form">
                            <div class="s-row">
                                <label class="s-label">Fulde navn</label>
                                <div class="s-name-row">
                                    <div class="s-avatar">{{ userInitials }}</div>
                                    <input v-model="profileForm.name" type="text" class="s-input" :class="{ 's-input-err': profileForm.errors.name }" autocomplete="name" required />
                                </div>
                                <p v-if="profileForm.errors.name" class="s-err">{{ profileForm.errors.name }}</p>
                            </div>

                            <div class="s-divider"></div>

                            <div class="s-row">
                                <label class="s-label">Email</label>
                                <input v-model="profileForm.email" type="email" class="s-input" :class="{ 's-input-err': profileForm.errors.email }" autocomplete="email" required />
                                <p v-if="profileForm.errors.email" class="s-err">{{ profileForm.errors.email }}</p>
                                <div v-if="mustVerifyEmail && !authUser.email_verified_at" class="s-verify-notice">
                                    Email ikke bekræftet.
                                    <Link :href="route('verification.send')" method="post" as="button" class="s-link">Send bekræftelsesemail</Link>
                                </div>
                            </div>

                            <div class="s-divider"></div>

                            <div class="s-row">
                                <label class="s-label">Telefon</label>
                                <input v-model="profileForm.phone" type="tel" class="s-input" placeholder="+45 00 00 00 00" autocomplete="tel" />
                            </div>

                            <div class="s-divider"></div>

                            <div class="s-row s-row-action">
                                <button type="submit" class="s-btn" :disabled="profileForm.processing">
                                    <span v-if="!profileForm.processing">Gem</span>
                                    <span v-else class="s-btn-loading"><span class="s-spin"></span>Gemmer…</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </template>

                <!-- ═══════════════════════════════
                     KONTO
                ═══════════════════════════════ -->
                <template v-if="activeSection === 'account'">
                    <h2 class="s-section-title">Konto</h2>

                    <div class="s-block">
                        <h3 class="s-block-title">Skift adgangskode</h3>
                        <form @submit.prevent="submitPassword" class="s-form">
                            <div class="s-row">
                                <label class="s-label">Nuværende adgangskode</label>
                                <div class="s-input-wrap">
                                    <input v-model="passwordForm.current_password" :type="showCurrentPw ? 'text' : 'password'" class="s-input" :class="{ 's-input-err': passwordForm.errors.current_password }" autocomplete="current-password" placeholder="••••••••" />
                                    <button type="button" class="s-eye" @click="showCurrentPw = !showCurrentPw" tabindex="-1">
                                        <svg v-if="!showCurrentPw" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" /><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" /></svg>
                                        <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd" /><path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z" /></svg>
                                    </button>
                                </div>
                                <p v-if="passwordForm.errors.current_password" class="s-err">{{ passwordForm.errors.current_password }}</p>
                            </div>

                            <div class="s-divider"></div>

                            <div class="s-row">
                                <label class="s-label">Ny adgangskode</label>
                                <div class="s-input-wrap">
                                    <input v-model="passwordForm.password" :type="showNewPw ? 'text' : 'password'" class="s-input" :class="{ 's-input-err': passwordForm.errors.password }" autocomplete="new-password" placeholder="••••••••" />
                                    <button type="button" class="s-eye" @click="showNewPw = !showNewPw" tabindex="-1">
                                        <svg v-if="!showNewPw" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" /><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" /></svg>
                                        <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd" /><path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z" /></svg>
                                    </button>
                                </div>
                                <p v-if="passwordForm.errors.password" class="s-err">{{ passwordForm.errors.password }}</p>
                            </div>

                            <div class="s-divider"></div>

                            <div class="s-row">
                                <label class="s-label">Bekræft ny adgangskode</label>
                                <div class="s-input-wrap">
                                    <input v-model="passwordForm.password_confirmation" :type="showConfirmPw ? 'text' : 'password'" class="s-input" autocomplete="new-password" placeholder="••••••••" />
                                    <button type="button" class="s-eye" @click="showConfirmPw = !showConfirmPw" tabindex="-1">
                                        <svg v-if="!showConfirmPw" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" /><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" /></svg>
                                        <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd" /><path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z" /></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="s-divider"></div>

                            <div class="s-row s-row-action">
                                <button type="submit" class="s-btn" :disabled="passwordForm.processing">
                                    <span v-if="!passwordForm.processing">Opdater adgangskode</span>
                                    <span v-else class="s-btn-loading"><span class="s-spin"></span>Opdaterer…</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Delete account -->
                    <div class="s-block s-block-danger">
                        <h3 class="s-block-title s-block-title-danger">Slet konto</h3>
                        <p class="s-block-desc">Alle dine data slettes permanent og kan ikke gendannes.</p>
                        <button v-if="!showDelete" @click="showDelete = true" class="s-btn s-btn-danger" style="margin:0.75rem 1.25rem 1rem">Slet min konto</button>
                        <form v-else @submit.prevent="submitDelete" class="s-form">
                            <div class="s-row">
                                <div class="s-input-wrap">
                                    <input v-model="deleteForm.password" :type="showDeletePw ? 'text' : 'password'" class="s-input" placeholder="Bekræft med adgangskode" autocomplete="current-password" />
                                    <button type="button" class="s-eye" @click="showDeletePw = !showDeletePw" tabindex="-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" /><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" /></svg>
                                    </button>
                                </div>
                                <p v-if="deleteForm.errors.password" class="s-err">{{ deleteForm.errors.password }}</p>
                                <div class="s-btn-row">
                                    <button type="submit" class="s-btn s-btn-danger" :disabled="deleteForm.processing">Slet permanent</button>
                                    <button type="button" @click="showDelete = false" class="s-btn s-btn-ghost">Annuller</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </template>

                <!-- ═══════════════════════════════
                     ABONNEMENT
                ═══════════════════════════════ -->
                <template v-if="activeSection === 'subscription'">
                    <h2 class="s-section-title">Abonnement</h2>

                    <div class="s-block">
                        <div class="plan-header-row">
                            <p class="s-block-desc" style="padding-top:1rem;margin-bottom:0">
                                Du er på <strong>{{ planLabel(currentPlan) }}</strong>-planen.
                            </p>
                            <a v-if="isPaidPlan" :href="route('subscription.portal')" class="s-btn s-btn-outline plan-portal-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:0.875rem;height:0.875rem"><path fill-rule="evenodd" d="M2.5 4A1.5 1.5 0 0 0 1 5.5V6h18v-.5A1.5 1.5 0 0 0 17.5 4h-15ZM19 8.5H1v6A1.5 1.5 0 0 0 2.5 16h15a1.5 1.5 0 0 0 1.5-1.5v-6ZM3 13.25a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 0 1.5h-1.5a.75.75 0 0 1-.75-.75Zm4.75-.75a.75.75 0 0 0 0 1.5h3.5a.75.75 0 0 0 0-1.5h-3.5Z" clip-rule="evenodd" /></svg>
                                Fakturaer &amp; betaling
                            </a>
                        </div>
                        <div class="plan-grid">
                            <div
                                v-for="plan in plans"
                                :key="plan.id"
                                class="plan-card"
                                :class="{ 'plan-card-active': currentPlan === plan.id }"
                                :style="{ '--accent': plan.color }"
                            >
                                <div v-if="plan.popular" class="plan-badge">Mest populær</div>
                                <div class="plan-name">{{ plan.name }}</div>
                                <div class="plan-price">
                                    <span class="plan-price-num">{{ plan.price }}</span>
                                    <span class="plan-price-unit"> kr/md.</span>
                                </div>
                                <div class="plan-msgs">{{ plan.messages }}</div>
                                <ul class="plan-feats">
                                    <li v-for="f in plan.features" :key="f">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                                        {{ f }}
                                    </li>
                                </ul>
                                <div class="plan-footer">
                                    <!-- Nuværende gratis plan -->
                                    <span v-if="currentPlan === plan.id && plan.id === 'free'" class="plan-current">Nuværende plan</span>

                                    <!-- Nuværende betalt plan: vis administrér-knap -->
                                    <span v-else-if="currentPlan === plan.id && plan.id !== 'free'" class="plan-current">
                                        Nuværende plan
                                    </span>

                                    <!-- Gratis plan når man er på betalt: vis annuller -->
                                    <button
                                        v-else-if="plan.id === 'free' && isPaidPlan"
                                        @click="cancelSubscription"
                                        class="plan-cancel-btn"
                                        :disabled="cancelForm.processing"
                                    >
                                        <span v-if="cancelForm.processing"><span class="s-spin s-spin-dark"></span>Annullerer…</span>
                                        <span v-else>Annuller abonnement</span>
                                    </button>

                                    <!-- Opgrader til betalt plan -->
                                    <button
                                        v-else
                                        @click="startCheckout(plan.id)"
                                        class="plan-pick-btn"
                                        :disabled="checkoutForm.processing"
                                        :style="{ '--accent': plan.color }"
                                    >
                                        <span v-if="checkoutForm.processing && checkoutForm.plan === plan.id">
                                            <span class="s-spin" style="border-color:rgba(255,255,255,0.3);border-top-color:#fff"></span>Omdirigerer…
                                        </span>
                                        <span v-else>Vælg plan →</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- ═══════════════════════════════
                     BRUG
                ═══════════════════════════════ -->
                <template v-if="activeSection === 'usage'">
                    <h2 class="s-section-title">Brug</h2>

                    <!-- Plan forbrug -->
                    <div class="s-block">
                        <div class="usage-block-title">Plan forbrug</div>

                        <div class="usage-row">
                            <div class="usage-row-left">
                                <div class="usage-row-name">AI-beskeder</div>
                                <div class="usage-row-sub">Nulstilles {{ nextResetDate }}</div>
                            </div>
                            <div class="usage-bar-wrap">
                                <div class="usage-track">
                                    <div class="usage-fill" :style="{ width: usagePercent + '%', background: usageColor }"></div>
                                </div>
                            </div>
                            <div class="usage-pct-label" :style="{ color: usageColor }">{{ usagePercent }}% brugt</div>
                        </div>

                        <div v-if="usagePercent >= 80" class="usage-warn">
                            Du nærmer dig din grænse.
                            <button @click="activeSection='subscription'" class="s-link">Opgrader plan →</button>
                        </div>

                        <div class="s-divider" style="margin:0.75rem 0"></div>

                        <div class="usage-stats">
                            <div class="usage-stat">
                                <div class="usage-stat-num">{{ messagesUsed }}<span v-if="messagesLimit < 999999">/{{ messagesLimit }}</span></div>
                                <div class="usage-stat-label">Beskeder brugt</div>
                            </div>
                            <div class="usage-stat">
                                <div class="usage-stat-num">{{ casesCount }}</div>
                                <div class="usage-stat-label">Sager</div>
                            </div>
                            <div class="usage-stat">
                                <div class="usage-stat-num">{{ tasksCount }}</div>
                                <div class="usage-stat-label">Opgaver</div>
                            </div>
                        </div>
                    </div>

                    <!-- Extra forbrug -->
                    <div class="s-block">
                        <div class="usage-block-title">Extra forbrug</div>

                        <div class="usage-row usage-toggle-row">
                            <div class="usage-row-left">
                                <div class="usage-row-name">Slå extra forbrug til</div>
                                <div class="usage-row-sub">Fortsæt brugen af Aura hvis du rammer din grænse</div>
                            </div>
                            <button
                                @click="extraUsageEnabled = !extraUsageEnabled"
                                :class="['usage-toggle', extraUsageEnabled && 'usage-toggle-on']"
                                role="switch"
                                :aria-checked="extraUsageEnabled"
                            >
                                <span class="usage-toggle-thumb"></span>
                            </button>
                        </div>

                        <div class="s-divider" style="margin:0"></div>

                        <div class="usage-row">
                            <div class="usage-row-left">
                                <div class="usage-row-name">0,00 kr brugt</div>
                                <div class="usage-row-sub">Nulstilles {{ nextResetDate }}</div>
                            </div>
                            <div class="usage-bar-wrap">
                                <div class="usage-track">
                                    <div class="usage-fill" style="width:0%;background:#7E75CE"></div>
                                </div>
                            </div>
                            <div class="usage-pct-label" style="color:#9ca3af">0% brugt</div>
                        </div>

                        <div class="s-divider" style="margin:0"></div>

                        <div class="usage-row s-row-between" style="flex-direction:row;align-items:center">
                            <div>
                                <div class="usage-row-name">
                                    Månedlig forbrugsgrænse
                                    <span class="usage-info-chip">Ikke sat</span>
                                </div>
                                <div class="usage-row-sub">Maks. ekstra forbrug pr. måned</div>
                            </div>
                            <button class="s-btn s-btn-outline" disabled>Juster grænse</button>
                        </div>

                        <div class="s-divider" style="margin:0"></div>

                        <div class="usage-row s-row-between" style="flex-direction:row;align-items:center">
                            <div>
                                <div class="usage-row-name">{{ walletBalance }} DKK</div>
                                <div class="usage-row-sub">
                                    Aktuel saldo
                                    <span class="usage-autoreload-off">· Auto-genopfyldning slået fra</span>
                                </div>
                            </div>
                            <button class="s-btn" disabled>Køb mere</button>
                        </div>
                    </div>
                </template>

                </div>
            </div>
            </main>
        </div>

    </div><!-- chat-container -->
    </ChatLayout>
</template>

<style scoped>
*, *::before, *::after { box-sizing: border-box; }

/* ── Scrollbart indholdsområde under topbaren ─────────────── */
.settings-main {
    flex: 1;
    min-width: 0;
    overflow-y: auto;
    background: #fff;
    padding: 1.5rem 2rem;
    font-family: 'Inter', system-ui, sans-serif;
}

/* Indre layout: nav-kort + indhold side om side */
.settings-layout {
    display: flex;
    align-items: flex-start;
    gap: 1.5rem;
    max-width: 64rem;
}

/* ── Nav-kort ─────────────────────────────────────────────── */
.settings-nav {
    width: 13.5rem;
    flex-shrink: 0;
    background: #fff;
    border-radius: 0.875rem;
    padding: 0.625rem 0.5rem;
    border: 1px solid #e9eaec;
    box-shadow: 0 1px 6px rgba(0,0,0,0.05);
    position: sticky;
    top: 0;
}

.settings-nav-title {
    font-size: 0.6875rem;
    font-weight: 600;
    color: #b0b7c3;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 0.375rem 0.75rem 0.5rem;
}

.settings-nav-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.settings-nav-item {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    width: 100%;
    text-align: left;
    padding: 0.5625rem 0.75rem;
    font-size: 0.875rem;
    color: #6b7280;
    background: transparent;
    border: none;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: background 0.12s, color 0.12s;
    line-height: 1.3;
}
.settings-nav-item:hover { background: #f5f5f7; color: #1a1a2e; }
.settings-nav-item-active {
    background: #ede9fb;
    color: #5b45c9;
    font-weight: 600;
}
.settings-nav-item-active .settings-nav-icon { color: #7E75CE; }

.settings-nav-icon { display: flex; flex-shrink: 0; color: #9ca3af; transition: color 0.12s; }
.settings-nav-icon svg { width: 1rem; height: 1rem; }

/* ── Indholdsområde ──────────────────────────────────────────── */
.settings-content {
    flex: 1;
    min-width: 0;
}

/* ── Section title ───────────────────────────────────────── */
.s-section-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #111827;
    margin: 0 0 1.5rem;
}

/* ── Banners ─────────────────────────────────────────────── */
.s-banner {
    border-radius: 0.625rem;
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    margin-bottom: 1.25rem;
}
.s-banner-green  { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.s-banner-purple { background: #f5f3ff; color: #5b21b6; border: 1px solid #ddd6fe; }

/* ── Block ───────────────────────────────────────────────── */
.s-block {
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    background: #fff;
    margin-bottom: 1.5rem;
    overflow: hidden;
}
.s-block-danger { border-color: #fecaca; }

.s-block-title {
    font-size: 0.9375rem;
    font-weight: 600;
    color: #111827;
    padding: 1rem 1.25rem 0;
}
.s-block-title-danger { color: #dc2626; }

.s-block-desc {
    font-size: 0.875rem;
    color: #6b7280;
    padding: 0.5rem 1.25rem 0;
    margin: 0;
}

/* ── Row ─────────────────────────────────────────────────── */
.s-form { display: flex; flex-direction: column; }
.s-row  { padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: 0.5rem; }
.s-row-action { padding-top: 0.75rem; padding-bottom: 0.75rem; flex-direction: row; }
.s-row-between { flex-direction: row; align-items: center; justify-content: space-between; }
.s-btn-row { display: flex; gap: 0.75rem; margin-top: 0.5rem; }

.s-divider { height: 1px; background: #f3f4f6; }

.s-label { font-size: 0.8125rem; font-weight: 500; color: #374151; }
.s-err   { font-size: 0.8125rem; color: #ef4444; margin: 0; }

.s-name-row { display: flex; align-items: center; gap: 0.75rem; }
.s-avatar {
    width: 2.25rem; height: 2.25rem; border-radius: 9999px;
    background:
        radial-gradient(circle at 30% 80%, #dda0e8 0%, transparent 60%),
        radial-gradient(circle at 70% 20%, #a0cff5 0%, transparent 60%),
        radial-gradient(circle at 50% 50%, #c0b8f0 0%, transparent 70%);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.8125rem; font-weight: 700; color: #fff; flex-shrink: 0;
}

/* ── Inputs ──────────────────────────────────────────────── */
.s-input-wrap { position: relative; }

.s-input {
    width: 100%; padding: 0.5625rem 0.875rem; font-size: 0.9375rem;
    border: 1.5px solid #e5e7eb; border-radius: 0.5rem;
    background: #fafafa; color: #111827; outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.s-input:focus { border-color: #7E75CE; box-shadow: 0 0 0 3px rgba(126,117,206,0.1); background: #fff; }
.s-input-err   { border-color: #f87171; }
.s-input-wrap .s-input { padding-right: 2.75rem; }

.s-eye {
    position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; color: #9ca3af; display: flex; padding: 0;
}
.s-eye:hover { color: #374151; }
.s-eye svg { width: 1.125rem; height: 1.125rem; }

/* ── Buttons ─────────────────────────────────────────────── */
.s-btn {
    display: inline-flex; align-items: center; gap: 0.375rem;
    padding: 0.5rem 1.125rem; font-size: 0.875rem; font-weight: 600;
    color: #fff; background: linear-gradient(135deg, #7E75CE, #5BC4E8);
    border: none; border-radius: 0.5rem; cursor: pointer;
    transition: opacity 0.15s; white-space: nowrap;
}
.s-btn:hover:not(:disabled) { opacity: 0.88; }
.s-btn:disabled { opacity: 0.55; cursor: not-allowed; }

.s-btn-danger  { background: linear-gradient(135deg, #ef4444, #dc2626); }
.s-btn-ghost   { background: transparent; color: #6b7280; border: 1.5px solid #e5e7eb; }
.s-btn-ghost:hover { color: #111827; background: #f3f4f6; }
.s-btn-outline {
    background: transparent; color: #374151;
    border: 1.5px solid #d1d5db; padding: 0.4375rem 1rem; font-size: 0.8125rem;
}
.s-btn-outline:hover:not(:disabled) { background: #f9fafb; }

.s-btn-loading { display: inline-flex; align-items: center; gap: 0.375rem; }
.s-spin {
    width: 0.875rem; height: 0.875rem;
    border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff;
    border-radius: 9999px; animation: sspin 0.7s linear infinite; display: inline-block;
}
.s-spin-dark { border-color: rgba(0,0,0,0.1); border-top-color: #6b7280; }
@keyframes sspin { to { transform: rotate(360deg); } }

.s-link { color: #7E75CE; font-weight: 600; background: none; border: none; cursor: pointer; font-size: inherit; padding: 0; }
.s-link:hover { text-decoration: underline; }

.s-verify-notice {
    font-size: 0.8125rem; color: #92400e;
    background: #fffbeb; border: 1px solid #fde68a;
    border-radius: 0.375rem; padding: 0.5rem 0.75rem;
    display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
}

/* ── Plan grid ───────────────────────────────────────────── */
.plan-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
    gap: 0.875rem;
    padding: 1rem 1.25rem;
}
.plan-card {
    position: relative; border: 2px solid #e5e7eb; border-radius: 0.75rem;
    padding: 1.125rem 1rem 1rem; cursor: pointer;
    transition: border-color 0.18s, box-shadow 0.18s, transform 0.12s;
    background: #fff; user-select: none;
}
.plan-card:hover { border-color: var(--accent); transform: translateY(-1px); box-shadow: 0 3px 12px rgba(0,0,0,0.07); }
.plan-card-active { border-color: var(--accent); background: color-mix(in srgb, var(--accent) 5%, white); }

.plan-badge {
    position: absolute; top: -0.5625rem; left: 50%; transform: translateX(-50%);
    font-size: 0.625rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;
    white-space: nowrap; padding: 0.1875rem 0.5rem;
    background: var(--accent); color: #fff; border-radius: 9999px;
}
.plan-name      { font-size: 0.9375rem; font-weight: 700; color: #111827; margin-bottom: 0.375rem; }
.plan-price     { margin-bottom: 0.25rem; }
.plan-price-num { font-size: 1.5rem; font-weight: 800; color: var(--accent); }
.plan-price-unit { font-size: 0.75rem; color: #6b7280; }
.plan-msgs      { font-size: 0.75rem; color: #374151; font-weight: 500; margin-bottom: 0.875rem; }
.plan-feats     { list-style: none; padding: 0; margin: 0 0 0.875rem; display: flex; flex-direction: column; gap: 0.3rem; }
.plan-feats li  { display: flex; align-items: flex-start; gap: 0.3rem; font-size: 0.75rem; color: #374151; }
.plan-feats svg { width: 0.75rem; height: 0.75rem; color: var(--accent); flex-shrink: 0; margin-top: 0.1rem; }
.plan-current   { font-size: 0.75rem; font-weight: 600; color: var(--accent); }

.plan-pick-btn {
    display: inline-flex; align-items: center; gap: 0.3rem;
    font-size: 0.75rem; font-weight: 700; color: #fff;
    background: var(--accent); border: none; border-radius: 0.375rem;
    padding: 0.375rem 0.75rem; cursor: pointer;
    transition: opacity 0.15s; width: 100%; justify-content: center;
}
.plan-pick-btn:hover:not(:disabled) { opacity: 0.85; }
.plan-pick-btn:disabled { opacity: 0.55; cursor: not-allowed; }

.plan-cancel-btn {
    display: inline-flex; align-items: center; gap: 0.3rem;
    font-size: 0.75rem; font-weight: 600; color: #6b7280;
    background: transparent; border: 1.5px solid #e5e7eb; border-radius: 0.375rem;
    padding: 0.3125rem 0.75rem; cursor: pointer;
    transition: border-color 0.15s, color 0.15s; width: 100%; justify-content: center;
}
.plan-cancel-btn:hover:not(:disabled) { border-color: #f87171; color: #dc2626; }
.plan-cancel-btn:disabled { opacity: 0.55; cursor: not-allowed; }

.plan-header-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 1.25rem; gap: 1rem; flex-wrap: wrap;
}
.plan-portal-btn {
    margin-top: 0.75rem; display: inline-flex; align-items: center; gap: 0.375rem;
}

/* ── Usage ───────────────────────────────────────────────── */
.usage-block-title {
    font-size: 1rem; font-weight: 700; color: #111827;
    padding: 1rem 1.25rem 0; margin-bottom: 0.25rem;
}
.usage-row {
    display: flex; align-items: center; gap: 1rem;
    padding: 0.875rem 1.25rem;
}
.usage-toggle-row { padding: 1rem 1.25rem; }
.usage-row-left { flex: 0 0 10rem; }
.usage-row-name {
    font-size: 0.875rem; font-weight: 500; color: #111827;
    display: flex; align-items: center; gap: 0.375rem;
}
.usage-row-sub  { font-size: 0.75rem; color: #9ca3af; margin-top: 0.125rem; }
.usage-bar-wrap { flex: 1; }
.usage-track    { height: 0.5rem; background: #f3f4f6; border-radius: 9999px; overflow: hidden; }
.usage-fill     { height: 100%; border-radius: 9999px; transition: width 0.5s ease; }
.usage-pct-label {
    font-size: 0.8125rem; font-weight: 600; white-space: nowrap; flex: 0 0 5.5rem; text-align: right;
}
.usage-warn {
    font-size: 0.8125rem; color: #92400e;
    background: #fffbeb; border: 1px solid #fde68a;
    border-radius: 0.375rem; padding: 0.5rem 0.75rem; margin: 0 1.25rem 0.75rem;
    display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;
}
.usage-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; padding: 1rem 1.25rem; }
.usage-stat  { text-align: center; }
.usage-stat-num   { font-size: 1.375rem; font-weight: 700; color: #111827; }
.usage-stat-num span { font-size: 0.9rem; color: #6b7280; font-weight: 500; }
.usage-stat-label { font-size: 0.75rem; color: #6b7280; margin-top: 0.125rem; }

.usage-toggle {
    position: relative; width: 2.75rem; height: 1.5rem;
    border-radius: 9999px; background: #d1d5db; border: none;
    cursor: pointer; transition: background 0.2s; flex-shrink: 0; padding: 0;
}
.usage-toggle-on { background: #7E75CE; }
.usage-toggle-thumb {
    position: absolute; top: 0.1875rem; left: 0.1875rem;
    width: 1.125rem; height: 1.125rem; border-radius: 9999px;
    background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    transition: transform 0.2s; display: block;
}
.usage-toggle-on .usage-toggle-thumb { transform: translateX(1.25rem); }

.usage-info-chip {
    font-size: 0.6875rem; font-weight: 600; color: #9ca3af;
    background: #f3f4f6; border-radius: 9999px; padding: 0.125rem 0.5rem;
}
.usage-autoreload-off { color: #ef4444; font-size: 0.75rem; }
</style>
