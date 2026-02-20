<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import ChatLayout from '@/Layouts/ChatLayout.vue';
import ChatSidebar from '@/Components/ChatSidebar.vue';

const props = defineProps({
    mustVerifyEmail:   Boolean,
    status:            String,
    topupAmount:       Number,
    twoFactor:         Object,
    usagePercent:      Number,
    tokensUsed:        Number,
    tokensLimit:       Number,
    casesCount:        Number,
    tasksCount:        Number,
    cases:             { type: Array, default: () => [] },
    subscriptionPlans: { type: Array, default: () => [] },
});

const page       = usePage();
const authUser   = computed(() => page.props.auth.user);
const sidebarOpen = ref(true);

const activeSection = ref('general');

const navItems = [
    { id: 'general',      label: 'Generelt' },
    { id: 'account',      label: 'Konto' },
    { id: 'subscription', label: 'Fakturering' },
    { id: 'security',     label: 'Sikkerhed' },
    { id: 'usage',        label: 'Forbrug' },
];

/* ── Forms ─────────────────────────────────────────────── */
const profileForm = useForm({
    name:             authUser.value.name             || '',
    email:            authUser.value.email            || '',
    display_name:     authUser.value.display_name     || '',
    work_description: authUser.value.work_description || '',
    preferences:      authUser.value.preferences      || '',
    phone:            authUser.value.phone            || '',
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

const plans = computed(() => props.subscriptionPlans.map(sp => ({
    id:       sp.slug,
    name:     sp.name,
    price:    String(sp.price),
    messages: sp.slug === 'basis' ? '10x mere forbrug end gratis'
            : sp.slug === 'pro'   ? '15x mere forbrug end gratis'
            : 'Begrænset forbrug',
    features: Array.isArray(sp.features) ? sp.features : [],
    color:    sp.color || '#9ca3af',
    popular:  sp.is_popular,
    hasStripe: !!sp.stripe_price_id,
})));

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
const hasWalletBalance = computed(() => Number(authUser.value.wallet_balance || 0) > 0);
const showExtraUsage = computed(() => currentPlan.value !== 'free' || hasWalletBalance.value);

/* ── Wallet top-up modal ─────────────────────────────────── */
const showTopupModal  = ref(false);
const topupPresets    = [50, 100, 200, 500];
const topupAmount     = ref(100);
const topupCustom     = ref('');
const topupLoading    = ref(false);
const topupError      = ref('');

const selectPreset = (val) => {
    topupAmount.value = val;
    topupCustom.value = '';
    topupError.value  = '';
};
const onCustomInput = () => {
    const v = parseInt(topupCustom.value);
    if (!isNaN(v) && v >= 10) topupAmount.value = v;
};
const submitTopup = async () => {
    if (topupAmount.value < 10 || topupLoading.value) return;
    topupLoading.value = true;
    topupError.value   = '';

    try {
        const { data } = await window.axios.post(
            route('subscription.wallet.topup'),
            { amount: topupAmount.value }
        );
        window.location.href = data.url;
    } catch (e) {
        topupError.value  = e?.response?.data?.message || e?.message || 'Ukendt fejl';
        topupLoading.value = false;
    }
};

/* ── Password visibility ────────────────────────────────── */
const showCurrentPw = ref(false);
const showNewPw     = ref(false);
const showConfirmPw = ref(false);
const showDeletePw  = ref(false);
const showDelete    = ref(false);

/* ── Helpers ────────────────────────────────────────────── */
const userDisplayId = computed(() => {
    const id = authUser.value.id || 1;
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let seed = id;
    const next = () => { seed = (seed * 1664525 + 1013904223) >>> 0; return chars[seed % chars.length]; };
    return `${next()}${next()}${next()}${next()}-${next()}${next()}${next()}${next()}-${next()}${next()}${next()}${next()}`;
});

const userInitials = computed(() => {
    const n = authUser.value.name || '';
    return n.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2) || '?';
});

const usageColor = computed(() => {
    if (props.usagePercent >= 90) return '#ef4444';
    if (props.usagePercent >= 70) return '#f59e0b';
    return '#374151';
});

const planLabel = (id) => plans.value.find(p => p.id === id)?.name ?? id;

/* ── Two-Factor Authentication ──────────────────────────── */
const twoFactorEnabled = computed(() => !!authUser.value.two_factor_confirmed_at);
const tfSetupStep      = ref('idle');
const tfConfirmForm    = useForm({ code: '' });
const tfDisableForm    = useForm({ password: '' });
const tfRegenForm      = useForm({ password: '' });
const showTfDisablePw  = ref(false);
const showTfRegenPw    = ref(false);

const enableTwoFactor = () => {
    useForm({}).post(route('two-factor.enable'), {
        preserveScroll: true,
        onSuccess: () => { tfSetupStep.value = 'qr'; },
    });
};

const confirmTwoFactor = () => {
    tfConfirmForm.post(route('two-factor.confirm'), {
        preserveScroll: true,
        onSuccess: () => { tfSetupStep.value = 'recovery'; tfConfirmForm.reset(); },
    });
};

const disableTwoFactor = () => {
    tfDisableForm.post(route('two-factor.disable'), {
        preserveScroll: true,
        onSuccess: () => { tfSetupStep.value = 'idle'; tfDisableForm.reset(); },
    });
};

const regenerateRecoveryCodes = () => {
    tfRegenForm.post(route('two-factor.recovery-codes'), {
        preserveScroll: true,
        onSuccess: () => { tfSetupStep.value = 'recovery'; tfRegenForm.reset(); },
    });
};

/* ── Extra usage toggle ─────────────────────────────────── */
const extraUsageEnabled   = ref(!!authUser.value.extra_usage_enabled);
const autoRefillEnabled   = ref(!!authUser.value.auto_refill_enabled);
const autoRefillThreshold = ref(authUser.value.auto_refill_threshold ?? 50);
const autoRefillAmount    = ref(authUser.value.auto_refill_amount ?? 100);
const extraUsageSaving    = ref(false);

const saveExtraUsage = async (patch = {}) => {
    extraUsageSaving.value = true;
    try {
        await window.axios.patch(route('profile.extra-usage'), {
            extra_usage_enabled:  extraUsageEnabled.value,
            auto_refill_enabled:  autoRefillEnabled.value,
            auto_refill_threshold: autoRefillThreshold.value,
            auto_refill_amount:   autoRefillAmount.value,
            ...patch,
        });
    } finally {
        extraUsageSaving.value = false;
    }
};

const toggleExtraUsage = () => {
    extraUsageEnabled.value = !extraUsageEnabled.value;
    saveExtraUsage();
};

const toggleAutoRefill = () => {
    autoRefillEnabled.value = !autoRefillEnabled.value;
    saveExtraUsage();
};

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
        <ChatSidebar :cases="cases" :active-case="null" :open="sidebarOpen" @toggle="sidebarOpen = !sidebarOpen" />

        <div class="chat-main">
            <!-- Topbar -->
            <div class="chat-topbar">
                <h2 class="page-topbar-title">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="topbar-icon">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    Indstillinger
                </h2>
            </div>

            <div class="page-scroll">
                <div class="st-outer">

                    <!-- ── Left nav ─────────────────────────── -->
                    <nav class="st-nav">
                        <button @click="activeSection = 'general'" :class="['st-nav-btn', { 'st-nav-btn-active': activeSection === 'general' }]">
                            <svg class="st-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                            Generelt
                        </button>
                        <button @click="activeSection = 'account'" :class="['st-nav-btn', { 'st-nav-btn-active': activeSection === 'account' }]">
                            <svg class="st-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.559.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.272-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                            Konto
                        </button>
                        <button @click="activeSection = 'subscription'" :class="['st-nav-btn', { 'st-nav-btn-active': activeSection === 'subscription' }]">
                            <svg class="st-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg>
                            Fakturering
                        </button>
                        <button @click="activeSection = 'security'" :class="['st-nav-btn', { 'st-nav-btn-active': activeSection === 'security' }]">
                            <svg class="st-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                            Sikkerhed
                        </button>
                        <button @click="activeSection = 'usage'" :class="['st-nav-btn', { 'st-nav-btn-active': activeSection === 'usage' }]">
                            <svg class="st-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                            Forbrug
                        </button>
                    </nav>

                    <!-- ── Content ──────────────────────────── -->
                    <div class="st-content">

                        <!-- Banners -->
                        <div v-if="status === 'profile-updated'"           class="st-banner st-banner-green">Profil opdateret.</div>
                        <div v-if="status === 'password-updated'"          class="st-banner st-banner-green">Adgangskode opdateret.</div>
                        <div v-if="status === 'subscription-updated'"      class="st-banner st-banner-purple">Abonnement opdateret til {{ planLabel(currentPlan) }}.</div>
                        <div v-if="status === 'subscription-cancelled'"    class="st-banner st-banner-green">Dit abonnement er annulleret. Du er nu på Gratis-planen.</div>
                        <div v-if="status === 'subscription-payment-failed'" class="st-banner st-banner-red">Betalingen kunne ikke gennemføres. Din plan er ikke ændret. Tjek at dit kort har dækning og prøv igen.</div>
                        <div v-if="status === 'wallet-topup-success'" class="st-banner st-banner-green">{{ topupAmount }} Kr. er tilføjet til din saldo.</div>

                        <!-- ══════════════ GENERELT ══════════════ -->
                        <template v-if="activeSection === 'general'">

                            <!-- User avatar + name -->
                            <div class="st-profile-hero">
                                <div class="st-avatar">{{ userInitials }}</div>
                                <div>
                                    <div class="st-profile-name">{{ authUser.name }}</div>
                                    <div class="st-profile-email">{{ authUser.email }}</div>
                                    <code class="st-profile-id">{{ userDisplayId }}</code>
                                </div>
                            </div>

                            <div class="st-divider"></div>

                            <h3 class="st-heading">Profil</h3>
                            <form @submit.prevent="submitProfile" class="st-form">

                                <!-- Full name + Display name side by side -->
                                <div class="st-field-pair">
                                    <div class="st-field-block">
                                        <label class="st-label-block">Fulde navn</label>
                                        <input v-model="profileForm.name" type="text" class="st-input" :class="{ 'st-input-err': profileForm.errors.name }" autocomplete="name" required placeholder="Dit fulde navn" />
                                        <p v-if="profileForm.errors.name" class="st-err">{{ profileForm.errors.name }}</p>
                                    </div>
                                    <div class="st-field-block">
                                        <label class="st-label-block">Hvad skal Aura kalde dig?</label>
                                        <input v-model="profileForm.display_name" type="text" class="st-input" placeholder="fx. Fuad" />
                                    </div>
                                </div>

                                <!-- Work description -->
                                <div class="st-field-block" style="margin-top:1rem">
                                    <label class="st-label-block">Hvad beskriver dig bedst som person?</label>
                                    <input v-model="profileForm.work_description" type="text" class="st-input" placeholder="fx. Empatisk, direkte, analytisk, foretrækker korte svar..." />
                                </div>

                                <div class="st-divider" style="margin:1.5rem 0 1.25rem"></div>

                                <!-- Personal preferences -->
                                <div class="st-field-block">
                                    <label class="st-label-block" style="font-weight:500;color:#111827">Hvilke personlige præferencer skal Aura tage hensyn til i sine svar?</label>
                                    <p class="st-muted" style="margin-bottom:0.5rem">Dine præferencer gælder for alle samtaler, inden for Auras retningslinjer.</p>
                                    <textarea v-model="profileForm.preferences" class="st-input st-textarea" rows="4" placeholder="fx. Brug dansk, vær kortfattet, undgå juridisk jargon..."></textarea>
                                </div>

                                <div class="st-form-actions">
                                    <button type="submit" class="st-btn" :disabled="profileForm.processing">
                                        <span v-if="!profileForm.processing">Gem ændringer</span>
                                        <span v-else class="st-btn-loading"><span class="st-spin st-spin-dark"></span>Gemmer…</span>
                                    </button>
                                </div>
                            </form>
                        </template>

                        <!-- ══════════════ KONTO ══════════════ -->
                        <template v-if="activeSection === 'account'">
                            <h3 class="st-heading">Konto</h3>

                            <!-- Skift adgangskode -->
                            <h4 class="st-subheading">Skift adgangskode</h4>
                            <form @submit.prevent="submitPassword">
                                <div class="st-field">
                                    <label class="st-label">Nuværende adgangskode</label>
                                    <div class="st-input-wrap">
                                        <input v-model="passwordForm.current_password" :type="showCurrentPw ? 'text' : 'password'" class="st-input" :class="{ 'st-input-err': passwordForm.errors.current_password }" autocomplete="current-password" placeholder="••••••••" />
                                        <button type="button" class="st-eye" @click="showCurrentPw = !showCurrentPw" tabindex="-1">
                                            <svg v-if="!showCurrentPw" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" /><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" /></svg>
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd" /><path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z" /></svg>
                                        </button>
                                    </div>
                                    <p v-if="passwordForm.errors.current_password" class="st-err">{{ passwordForm.errors.current_password }}</p>
                                </div>
                                <div class="st-field">
                                    <label class="st-label">Ny adgangskode</label>
                                    <div class="st-input-wrap">
                                        <input v-model="passwordForm.password" :type="showNewPw ? 'text' : 'password'" class="st-input" :class="{ 'st-input-err': passwordForm.errors.password }" autocomplete="new-password" placeholder="••••••••" />
                                        <button type="button" class="st-eye" @click="showNewPw = !showNewPw" tabindex="-1">
                                            <svg v-if="!showNewPw" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" /><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" /></svg>
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd" /><path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z" /></svg>
                                        </button>
                                    </div>
                                    <p v-if="passwordForm.errors.password" class="st-err">{{ passwordForm.errors.password }}</p>
                                </div>
                                <div class="st-field">
                                    <label class="st-label">Bekræft ny adgangskode</label>
                                    <div class="st-input-wrap">
                                        <input v-model="passwordForm.password_confirmation" :type="showConfirmPw ? 'text' : 'password'" class="st-input" autocomplete="new-password" placeholder="••••••••" />
                                        <button type="button" class="st-eye" @click="showConfirmPw = !showConfirmPw" tabindex="-1">
                                            <svg v-if="!showConfirmPw" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" /><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" /></svg>
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd" /><path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z" /></svg>
                                        </button>
                                    </div>
                                </div>
                                <div style="margin-top:1.25rem">
                                    <button type="submit" class="st-btn" :disabled="passwordForm.processing">
                                        <span v-if="!passwordForm.processing">Opdater adgangskode</span>
                                        <span v-else class="st-btn-loading"><span class="st-spin"></span>Opdaterer…</span>
                                    </button>
                                </div>
                            </form>

                            <div class="st-divider" style="margin-top:2rem"></div>

                            <!-- Slet konto -->
                            <h4 class="st-subheading">Vil du gerne slette din konto?</h4>
                            <p class="st-muted">Alle dine data slettes permanent og kan ikke gendannes.</p>
                            <div v-if="!showDelete" style="margin-top:0.875rem">
                                <button @click="showDelete = true" class="st-btn st-btn-danger">Slet konto</button>
                            </div>
                            <div v-else style="margin-top:0.875rem; display:flex; flex-direction:column; gap:0.75rem; max-width:22rem">
                                <div class="st-input-wrap">
                                    <input v-model="deleteForm.password" :type="showDeletePw ? 'text' : 'password'" class="st-input" placeholder="Bekræft med adgangskode" autocomplete="current-password" />
                                    <button type="button" class="st-eye" @click="showDeletePw = !showDeletePw" tabindex="-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" /><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" /></svg>
                                    </button>
                                </div>
                                <p v-if="deleteForm.errors.password" class="st-err">{{ deleteForm.errors.password }}</p>
                                <div style="display:flex; gap:0.75rem">
                                    <button @click="submitDelete" class="st-btn st-btn-danger" :disabled="deleteForm.processing">Slet permanent</button>
                                    <button type="button" @click="showDelete = false" class="st-btn st-btn-ghost">Annuller</button>
                                </div>
                            </div>
                        </template>

                        <!-- ══════════════ FAKTURERING ══════════════ -->
                        <template v-if="activeSection === 'subscription'">

                            <!-- Plan header -->
                            <div class="st-bill-row">
                                <div class="st-bill-plan-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" /></svg>
                                </div>
                                <div class="st-bill-plan-info">
                                    <div class="st-bill-plan-name">{{ planLabel(currentPlan) }}-plan</div>
                                    <div v-if="isPaidPlan" class="st-muted">Månedlig · fornyes automatisk den {{ nextResetDate }}.</div>
                                    <div v-else class="st-muted">Opgrader for at få mere forbrug.</div>
                                </div>
                                <Link :href="route('subscription.plans')" class="st-btn">{{ isPaidPlan ? 'Juster plan' : 'Opgrader' }}</Link>
                            </div>

                            <div class="st-divider"></div>

                            <!-- Payment -->
                            <div class="st-bill-section">Betaling</div>
                            <div class="st-bill-row">
                                <div class="st-bill-card-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg>
                                </div>
                                <div class="st-bill-card-info">
                                    <div v-if="isPaidPlan" class="st-muted">Betalingskort og fakturaer administreres via Stripe</div>
                                    <div v-else class="st-muted">Ingen betalingsmetode registreret</div>
                                </div>
                                <a v-if="isPaidPlan" :href="route('subscription.portal')" class="st-btn">Opdater</a>
                            </div>

                            <template v-if="isPaidPlan">
                            <div class="st-divider"></div>

                            <!-- Extra usage — kun for betalende brugere -->
                            <div class="st-bill-section">Extra forbrug</div>
                            <p class="st-muted" style="margin-bottom:1.25rem">Køb extra forbrug for at fortsætte brugen af Aura, hvis du rammer din grænse.</p>

                            <div class="st-bill-row" style="margin-bottom:0.875rem">
                                <div>
                                    <div class="st-bill-amount">{{ walletBalance }} Kr.</div>
                                    <div class="st-muted">Aktuel saldo</div>
                                </div>
                                <button @click="showTopupModal = true" class="st-btn">Køb mere</button>
                            </div>

                            </template>

                            <div class="st-divider"></div>

                            <!-- Invoices -->
                            <div class="st-bill-section">Fakturaer</div>
                            <div class="st-bill-row">
                                <div class="st-muted">Se og download dine fakturaer via betalingsportalen.</div>
                                <a v-if="isPaidPlan" :href="route('subscription.portal')" class="st-btn">Se fakturaer</a>
                            </div>

                            <div v-if="isPaidPlan">
                                <div class="st-divider"></div>
                                <!-- Cancellation -->
                                <div class="st-bill-section">Annullering</div>
                                <div class="st-bill-row">
                                    <div class="st-muted">Annuller abonnement</div>
                                    <button @click="cancelSubscription" class="st-btn st-btn-danger" :disabled="cancelForm.processing">
                                        <span v-if="cancelForm.processing"><span class="st-spin st-spin-dark"></span>Annullerer…</span>
                                        <span v-else>Annuller</span>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- ══════════════ SIKKERHED ══════════════ -->
                        <template v-if="activeSection === 'security'">
                            <div v-if="status === 'two-factor-disabled'" class="st-banner st-banner-green">Totrinsgodkendelse er deaktiveret.</div>

                            <h3 class="st-heading">Sikkerhed</h3>

                            <div class="st-2fa-header">
                                <div class="st-2fa-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z" clip-rule="evenodd" /></svg>
                                </div>
                                <div class="st-2fa-info">
                                    <div class="st-2fa-title">Totrinsgodkendelse (2FA)</div>
                                    <div class="st-muted">Tilføj et ekstra lag sikkerhed med Google Authenticator.</div>
                                </div>
                                <span v-if="twoFactorEnabled" class="st-badge st-badge-green">Aktiv</span>
                                <span v-else class="st-badge st-badge-red">Ikke aktiv</span>
                            </div>

                            <div class="st-divider"></div>

                            <div v-if="!twoFactorEnabled && tfSetupStep === 'idle'">
                                <p class="st-muted" style="margin-bottom:1rem">Når totrinsgodkendelse er aktiveret, skal du indtaste en engangskode fra din Google Authenticator-app, når du logger ind.</p>
                                <button @click="enableTwoFactor" class="st-btn">Aktivér totrinsgodkendelse</button>
                            </div>

                            <div v-if="!twoFactorEnabled && tfSetupStep === 'qr' && twoFactor?.qrCodeSvg">
                                <p class="st-muted" style="margin-bottom:1rem">Scan QR-koden med Google Authenticator og indtast den 6-cifrede kode for at bekræfte.</p>
                                <div class="st-qr" v-html="twoFactor.qrCodeSvg"></div>
                                <div class="st-secret">
                                    <span class="st-muted">Hemmelig nøgle:</span>
                                    <code class="st-code">{{ twoFactor.secret }}</code>
                                </div>
                                <form @submit.prevent="confirmTwoFactor" style="display:flex;align-items:flex-start;gap:0.75rem;flex-wrap:wrap;margin-top:1rem">
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" v-model="tfConfirmForm.code" class="st-input st-code-input" :class="{ 'st-input-err': tfConfirmForm.errors.code }" placeholder="000000" autocomplete="one-time-code" />
                                    <button type="submit" class="st-btn" :disabled="tfConfirmForm.processing">Bekræft</button>
                                    <p v-if="tfConfirmForm.errors.code" class="st-err">{{ tfConfirmForm.errors.code }}</p>
                                </form>
                            </div>

                            <div v-if="tfSetupStep === 'recovery' && twoFactor?.recoveryCodes">
                                <div class="st-notice st-notice-warn" style="margin-bottom:1rem">Gem disse gendannelseskoder et sikkert sted. Hver kode kan kun bruges én gang.</div>
                                <div class="st-codes-grid">
                                    <code v-for="code in twoFactor.recoveryCodes" :key="code" class="st-code st-code-block">{{ code }}</code>
                                </div>
                                <button @click="tfSetupStep = 'idle'" class="st-btn" style="margin-top:1rem">Forstået, gå videre</button>
                            </div>

                            <div v-if="twoFactorEnabled && tfSetupStep !== 'recovery'">
                                <p class="st-muted" style="margin-bottom:1.25rem">Totrinsgodkendelse er aktiv.</p>

                                <h4 class="st-subheading">Generer nye gendannelseskoder</h4>
                                <form @submit.prevent="regenerateRecoveryCodes" style="display:flex;align-items:flex-start;gap:0.75rem;flex-wrap:wrap;margin-bottom:1.5rem">
                                    <div class="st-input-wrap" style="width:14rem">
                                        <input v-model="tfRegenForm.password" :type="showTfRegenPw ? 'text' : 'password'" class="st-input" placeholder="Adgangskode" autocomplete="current-password" />
                                        <button type="button" class="st-eye" @click="showTfRegenPw = !showTfRegenPw" tabindex="-1"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" /><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" /></svg></button>
                                    </div>
                                    <button type="submit" class="st-btn st-btn-outline" :disabled="tfRegenForm.processing">Generer</button>
                                    <p v-if="tfRegenForm.errors.password" class="st-err">{{ tfRegenForm.errors.password }}</p>
                                </form>

                                <div class="st-divider"></div>
                                <h4 class="st-subheading" style="color:#dc2626">Deaktivér totrinsgodkendelse</h4>
                                <form @submit.prevent="disableTwoFactor" style="display:flex;align-items:flex-start;gap:0.75rem;flex-wrap:wrap;margin-top:0.875rem">
                                    <div class="st-input-wrap" style="width:14rem">
                                        <input v-model="tfDisableForm.password" :type="showTfDisablePw ? 'text' : 'password'" class="st-input" placeholder="Adgangskode" autocomplete="current-password" />
                                        <button type="button" class="st-eye" @click="showTfDisablePw = !showTfDisablePw" tabindex="-1"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" /><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" /></svg></button>
                                    </div>
                                    <button type="submit" class="st-btn st-btn-danger" :disabled="tfDisableForm.processing">Deaktivér</button>
                                    <p v-if="tfDisableForm.errors.password" class="st-err">{{ tfDisableForm.errors.password }}</p>
                                </form>
                            </div>
                        </template>

                        <!-- ══════════════ FORBRUG ══════════════ -->
                        <template v-if="activeSection === 'usage'">
                            <h3 class="st-heading">Forbrug</h3>

                            <!-- Main usage bar -->
                            <div class="st-usage-item">
                                <div class="st-usage-top">
                                    <div>
                                        <div class="st-usage-name">Dit nuværende forbrug</div>
                                        <div class="st-muted" style="margin-top:0.125rem;font-size:0.8125rem">Nulstilles {{ nextResetDate }}</div>
                                    </div>
                                    <span class="st-usage-pct" :style="{ color: usageColor }">{{ usagePercent }}% brugt</span>
                                </div>
                                <div class="st-usage-track">
                                    <div class="st-usage-fill" :style="{ width: Math.min(usagePercent, 100) + '%', background: usageColor }"></div>
                                </div>
                            </div>

                            <div v-if="usagePercent >= 80" class="st-notice" style="margin:0.75rem 0 0">
                                {{ usagePercent >= 100 ? 'Du har nået din grænse.' : 'Du nærmer dig din grænse.' }}
                                <button @click="activeSection = 'subscription'" class="st-link">Opgrader plan →</button>
                            </div>

                            <template v-if="showExtraUsage">
                            <div class="st-divider"></div>

                            <!-- Extra usage -->
                            <h3 class="st-heading">Extra forbrug</h3>

                            <!-- Toggle: slå extra forbrug til -->
                            <div class="st-extra-row">
                                <div>
                                    <div class="st-usage-name">Slå extra forbrug til for at fortsætte brugen af Aura, hvis du rammer din grænse.</div>
                                </div>
                                <button @click="toggleExtraUsage" :class="['st-toggle', extraUsageEnabled && 'st-toggle-on']" role="switch" :aria-checked="extraUsageEnabled" :disabled="extraUsageSaving">
                                    <span class="st-toggle-thumb"></span>
                                </button>
                            </div>

                            <template v-if="extraUsageEnabled">
                                <!-- Aktuel saldo + køb mere -->
                                <div class="st-extra-info-row" style="margin-top:1rem">
                                    <span class="st-usage-name">{{ walletBalance }} Kr.</span>
                                    <span class="st-muted">Aktuel saldo</span>
                                </div>
                                <button @click="showTopupModal = true" class="st-btn st-btn-outline" style="margin-top:0.875rem">Køb mere</button>

                                <div class="st-divider" style="margin:1.25rem 0"></div>

                                <!-- Auto-genopfyldning -->
                                <div class="st-extra-row">
                                    <div>
                                        <div class="st-usage-name">Auto-genopfyldning</div>
                                        <div class="st-muted" style="margin-top:0.2rem;font-size:0.8125rem">Fyld automatisk op når saldo falder under tærsklen.</div>
                                    </div>
                                    <button @click="toggleAutoRefill" :class="['st-toggle', autoRefillEnabled && 'st-toggle-on']" role="switch" :aria-checked="autoRefillEnabled" :disabled="extraUsageSaving">
                                        <span class="st-toggle-thumb"></span>
                                    </button>
                                </div>

                                <template v-if="autoRefillEnabled">
                                    <div class="st-refill-fields">
                                        <div class="st-field-block">
                                            <label class="st-label-block">Genopfyld når saldo er under</label>
                                            <div class="wt-custom-wrap" style="max-width:10rem">
                                                <input v-model.number="autoRefillThreshold" @change="saveExtraUsage()" type="number" min="10" max="1000" class="wt-custom-input" />
                                                <span class="wt-custom-suffix">Kr.</span>
                                            </div>
                                        </div>
                                        <div class="st-field-block">
                                            <label class="st-label-block">Genopfyld med</label>
                                            <div class="wt-custom-wrap" style="max-width:10rem">
                                                <input v-model.number="autoRefillAmount" @change="saveExtraUsage()" type="number" min="10" max="5000" class="wt-custom-input" />
                                                <span class="wt-custom-suffix">Kr.</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </template>
                            </template><!-- /showExtraUsage -->
                        </template>

                    </div><!-- st-content -->
                </div><!-- st-outer -->
            </div><!-- page-scroll -->
        </div><!-- chat-main -->
    </div><!-- chat-container -->

    <!-- ── Wallet top-up modal ─────────────────────────────── -->
    <Teleport to="body">
        <Transition name="modal-fade">
        <div v-if="showTopupModal" class="wt-overlay" @click.self="showTopupModal = false">
            <div class="wt-modal">
                <div class="wt-header">
                    <h3 class="wt-title">Køb extra forbrug</h3>
                    <button @click="showTopupModal = false" class="wt-close">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
                    </button>
                </div>

                <p class="wt-sub">Vælg et beløb at tilføje til din saldo. Bruges automatisk når din månedlige kvote er opbrugt.</p>

                <!-- Preset amounts -->
                <div class="wt-presets">
                    <button
                        v-for="p in topupPresets" :key="p"
                        @click="selectPreset(p)"
                        :class="['wt-preset', topupAmount === p && !topupCustom ? 'wt-preset-active' : '']"
                    >{{ p }} Kr.</button>
                </div>

                <!-- Custom amount -->
                <div class="wt-custom-row">
                    <span class="wt-custom-label">Andet beløb</span>
                    <div class="wt-custom-wrap">
                        <input
                            v-model="topupCustom"
                            @input="onCustomInput"
                            type="number" min="10" max="5000"
                            placeholder="fx. 350"
                            class="wt-custom-input"
                        />
                        <span class="wt-custom-suffix">Kr.</span>
                    </div>
                </div>

                <!-- Summary -->
                <div class="wt-summary">
                    <span>Total</span>
                    <span class="wt-summary-amount">{{ topupAmount }} Kr.</span>
                </div>

                <!-- Error -->
                <p v-if="topupError" style="color:#ef4444;font-size:0.8125rem;margin:0">{{ topupError }}</p>

                <!-- Actions -->
                <div class="wt-actions">
                    <button @click="showTopupModal = false" class="wt-btn-cancel">Annuller</button>
                    <button @click="submitTopup" :disabled="topupLoading || topupAmount < 10" class="wt-btn-pay">
                        <span v-if="topupLoading" class="st-btn-loading"><span class="st-spin"></span>Sender…</span>
                        <span v-else>Fyld op</span>
                    </button>
                </div>
            </div>
        </div>
        </Transition>
    </Teleport>

    </ChatLayout>
</template>

<style scoped>
*, *::before, *::after { box-sizing: border-box; }

/* ── Layout ──────────────────────────────────────────────── */
.st-outer {
    display: flex;
    min-height: 100%;
    font-family: 'Inter', system-ui, sans-serif;
}

/* ── Left nav ────────────────────────────────────────────── */
.st-nav {
    width: 13.5rem;
    flex-shrink: 0;
    padding: 2rem 1rem;
    background: #fff;
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.st-nav-title {
    font-size: 1.375rem;
    font-weight: 700;
    color: #111827;
    margin: 0 0 1.25rem;
    padding: 0 0.5rem;
}

.st-nav-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    text-align: left;
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #9ca3af;
    background: #fff;
    border: none;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: background 0.12s, color 0.12s;
}
.st-nav-icon { width: 1rem; height: 1rem; flex-shrink: 0; }
.st-nav-btn:hover { background: #f3f4f6; color: #111827; }
.st-nav-btn-active {
    background: #f3f4f6;
    color: #111827;
    font-weight: 500;
}
.st-nav-btn-active:hover { background: #f3f4f6; color: #111827; }

/* ── Content ─────────────────────────────────────────────── */
.st-content {
    flex: 1;
    min-width: 0;
    padding: 2rem 2.5rem;
    max-width: 46rem;
}

/* ── Typography ──────────────────────────────────────────── */
.st-heading {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #9ca3af;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    margin: 0 0 1.25rem;
}

.st-subheading {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #9ca3af;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    margin: 0 0 0.75rem;
}

.st-muted {
    font-size: 0.875rem;
    color: #6b7280;
    line-height: 1.5;
}

.st-divider {
    height: 1px;
    background: #e5e7eb;
    margin: 1.75rem 0;
}

/* ── Profile hero ────────────────────────────────────────── */
.st-profile-hero {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.75rem;
}

.st-avatar {
    width: 3.5rem;
    height: 3.5rem;
    border-radius: 9999px;
    background:
        radial-gradient(circle at 30% 80%, #dda0e8 0%, transparent 60%),
        radial-gradient(circle at 70% 20%, #a0cff5 0%, transparent 60%),
        radial-gradient(circle at 50% 50%, #c0b8f0 0%, transparent 70%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.125rem;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
}

.st-profile-name  { font-size: 1rem; font-weight: 700; color: #111827; }
.st-profile-email { font-size: 0.875rem; color: #6b7280; margin-top: 0.125rem; }
.st-profile-id    { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.75rem; color: #9ca3af; margin-top: 0.25rem; display: block; letter-spacing: 0.04em; }

/* ── Form ────────────────────────────────────────────────── */
.st-form { display: flex; flex-direction: column; gap: 0; }

.st-field-row {
    display: grid;
    grid-template-columns: 9rem 1fr;
    align-items: center;
    gap: 1rem;
    padding: 0.875rem 0;
    border-bottom: 1px solid #f3f4f6;
}
.st-field-row:last-of-type { border-bottom: none; }

/* stacked fallback for Account password fields */
.st-field {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    margin-bottom: 1rem;
}

.st-input-col { display: flex; flex-direction: column; gap: 0.25rem; }

.st-form-actions { padding-top: 1.25rem; }

/* Side-by-side field pair */
.st-field-pair {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
.st-field-block {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}
.st-label-block {
    font-size: 0.75rem;
    font-weight: 600;
    color: #9ca3af;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
.st-textarea { max-width: 100%; resize: vertical; min-height: 6rem; }
.st-field-pair .st-input,
.st-field-block .st-input { max-width: 100%; }

.st-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #9ca3af;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    flex-shrink: 0;
}

.st-input-wrap { position: relative; }
.st-input {
    width: 100%;
    max-width: 26rem;
    padding: 0.5625rem 0.875rem;
    font-size: 0.9375rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 0.5rem;
    background: #fafafa;
    color: #111827;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.st-input:focus { border-color: #7E75CE; box-shadow: 0 0 0 3px rgba(126,117,206,0.1); background: #fff; }
.st-input-err   { border-color: #f87171; }
.st-input-wrap .st-input { padding-right: 2.75rem; max-width: 100%; }
.st-eye { position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #9ca3af; display: flex; padding: 0; }
.st-eye:hover { color: #374151; }
.st-eye svg { width: 1.125rem; height: 1.125rem; }

.st-err  { font-size: 0.8125rem; color: #ef4444; margin: 0; }
.st-link { color: #7E75CE; font-weight: 600; background: none; border: none; cursor: pointer; font-size: inherit; padding: 0; }
.st-link:hover { text-decoration: underline; }

/* ── Info row (konto) ────────────────────────────────────── */
.st-info-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.5rem 0;
    margin-bottom: 0.25rem;
}
.st-info-label { font-size: 0.875rem; color: #374151; font-weight: 500; min-width: 7rem; }
.st-code { font-family: 'SF Mono', 'Fira Code', monospace; background: #f3f4f6; padding: 0.25rem 0.625rem; border-radius: 0.375rem; color: #111827; font-size: 0.8125rem; font-weight: 600; letter-spacing: 0.04em; user-select: all; }

/* ── Buttons ─────────────────────────────────────────────── */
.st-btn {
    display: inline-flex; align-items: center; gap: 0.375rem;
    padding: 0.5rem 1.125rem; font-size: 0.875rem; font-weight: 500;
    color: #374151; background: #fff;
    border: 1.5px solid #d1d5db; border-radius: 0.5rem; cursor: pointer;
    transition: background 0.15s, border-color 0.15s; white-space: nowrap;
}
.st-btn:hover:not(:disabled) { background: #f9fafb; border-color: #9ca3af; }
.st-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.st-btn-danger  { color: #dc2626; border-color: #fca5a5; background: #fff; }
.st-btn-danger:hover:not(:disabled) { background: #fef2f2; border-color: #f87171; }
.st-btn-ghost   { background: #fff; color: #6b7280; border: 1.5px solid #e5e7eb; }
.st-btn-ghost:hover { color: #111827; background: #f9fafb; }
.st-btn-outline {
    background: #fff; color: #374151;
    border: 1.5px solid #d1d5db;
    padding: 0.4375rem 1rem; font-size: 0.8125rem;
}
.st-btn-outline:hover:not(:disabled) { background: #f9fafb; }
.st-btn-loading { display: inline-flex; align-items: center; gap: 0.375rem; }
.st-spin { width: 0.875rem; height: 0.875rem; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 9999px; animation: stspin 0.7s linear infinite; display: inline-block; }
.st-spin-dark { border-color: rgba(0,0,0,0.1); border-top-color: #6b7280; }
@keyframes stspin { to { transform: rotate(360deg); } }

/* ── Billing ─────────────────────────────────────────────── */
.st-bill-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    justify-content: space-between;
    flex-wrap: wrap;
}

.st-bill-plan-icon {
    width: 3rem; height: 3rem; flex-shrink: 0;
    border-radius: 0.75rem;
    background: #f9fafb;
    border: 1.5px solid #e5e7eb;
    display: flex; align-items: center; justify-content: center;
    color: #6b7280;
}
.st-bill-plan-icon svg { width: 1.375rem; height: 1.375rem; }

.st-bill-plan-info { flex: 1; min-width: 0; }
.st-bill-plan-name { font-size: 1rem; font-weight: 700; color: #111827; margin-bottom: 0.125rem; }

.st-bill-card-icon {
    width: 2.25rem; height: 2.25rem; flex-shrink: 0;
    color: #9ca3af; display: flex; align-items: center; justify-content: center;
}
.st-bill-card-icon svg { width: 1.25rem; height: 1.25rem; }
.st-bill-card-info { flex: 1; min-width: 0; }

.st-bill-section {
    font-size: 0.8125rem; font-weight: 600; color: #9ca3af;
    letter-spacing: 0.06em; text-transform: uppercase;
    margin-bottom: 0.875rem;
}

.st-bill-amount {
    font-size: 1.25rem; font-weight: 700; color: #111827;
    margin-bottom: 0.125rem;
}

/* ── Banners ─────────────────────────────────────────────── */
.st-banner { border-radius: 0.625rem; padding: 0.75rem 1rem; font-size: 0.875rem; margin-bottom: 1.25rem; }
.st-banner-green  { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.st-banner-purple { background: #f5f3ff; color: #5b21b6; border: 1px solid #ddd6fe; }
.st-banner-red    { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

/* ── Notices ─────────────────────────────────────────────── */
.st-notice { font-size: 0.8125rem; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 0.375rem; padding: 0.5rem 0.75rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.st-notice-warn { border-radius: 0.5rem; padding: 0.75rem; display: block; }

/* ── Billing ─────────────────────────────────────────────── */
.st-billing-plan-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 0.25rem;
}
.st-billing-plan-name { font-size: 1rem; font-weight: 700; color: #111827; }

/* ── Plan grid ───────────────────────────────────────────── */
.st-plan-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: 0.875rem; margin-top: 0.75rem; }
.st-plan-card { position: relative; border: 2px solid #e5e7eb; border-radius: 0.75rem; padding: 1.125rem 1rem 1rem; transition: border-color 0.18s, box-shadow 0.18s, transform 0.12s; background: #fff; }
.st-plan-card:hover { border-color: var(--accent); transform: translateY(-1px); box-shadow: 0 3px 12px rgba(0,0,0,0.07); }
.st-plan-card-active { border-color: var(--accent); background: color-mix(in srgb, var(--accent) 5%, white); }
.st-plan-badge { position: absolute; top: -0.5625rem; left: 50%; transform: translateX(-50%); font-size: 0.625rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; white-space: nowrap; padding: 0.1875rem 0.5rem; background: var(--accent); color: #fff; border-radius: 9999px; }
.st-plan-name      { font-size: 0.9375rem; font-weight: 700; color: #111827; margin-bottom: 0.375rem; }
.st-plan-price     { margin-bottom: 0.25rem; }
.st-plan-price-num { font-size: 1.5rem; font-weight: 800; color: var(--accent); }
.st-plan-price-unit { font-size: 0.75rem; color: #6b7280; }
.st-plan-msgs      { font-size: 0.75rem; color: #374151; font-weight: 500; margin-bottom: 0.875rem; }
.st-plan-feats     { list-style: none; padding: 0; margin: 0 0 0.875rem; display: flex; flex-direction: column; gap: 0.3rem; }
.st-plan-feats li  { display: flex; align-items: flex-start; gap: 0.3rem; font-size: 0.75rem; color: #374151; }
.st-plan-feats svg { width: 0.75rem; height: 0.75rem; color: var(--accent); flex-shrink: 0; margin-top: 0.1rem; }
.st-plan-current   { font-size: 0.75rem; font-weight: 600; color: var(--accent); }
.st-plan-pick-btn  { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.75rem; font-weight: 700; color: #fff; background: var(--accent); border: none; border-radius: 0.375rem; padding: 0.375rem 0.75rem; cursor: pointer; transition: opacity 0.15s; width: 100%; justify-content: center; }
.st-plan-pick-btn:hover:not(:disabled) { opacity: 0.85; }
.st-plan-pick-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.st-plan-cancel-btn { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.75rem; font-weight: 600; color: #6b7280; background: transparent; border: 1.5px solid #e5e7eb; border-radius: 0.375rem; padding: 0.3125rem 0.75rem; cursor: pointer; transition: border-color 0.15s, color 0.15s; width: 100%; justify-content: center; }
.st-plan-cancel-btn:hover:not(:disabled) { border-color: #f87171; color: #dc2626; }
.st-plan-cancel-btn:disabled { opacity: 0.55; cursor: not-allowed; }

/* ── 2FA ─────────────────────────────────────────────────── */
.st-2fa-header { display: flex; align-items: center; gap: 0.875rem; margin-bottom: 0.25rem; }
.st-2fa-icon   { width: 2.5rem; height: 2.5rem; border-radius: 0.625rem; background: #f9fafb; border: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.st-2fa-icon svg { width: 1.25rem; height: 1.25rem; color: #6b7280; }
.st-2fa-info   { flex: 1; }
.st-2fa-title  { font-size: 0.9375rem; font-weight: 700; color: #111827; }

.st-badge { font-size: 0.6875rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; padding: 0.25rem 0.625rem; border-radius: 9999px; flex-shrink: 0; }
.st-badge-green { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.st-badge-red   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

.st-qr { display: flex; justify-content: center; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1rem; }
.st-qr :deep(svg) { width: 180px; height: 180px; }
.st-secret { display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; flex-wrap: wrap; margin-bottom: 0.5rem; }
.st-code-input { width: 10rem !important; text-align: center; font-size: 1.25rem; font-weight: 600; letter-spacing: 0.35em; padding-left: 1em !important; font-family: 'SF Mono', 'Fira Code', monospace; max-width: 10rem !important; }
.st-codes-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; margin-top: 0.75rem; }
.st-code-block { font-family: 'SF Mono', 'Fira Code', monospace; background: #f3f4f6; padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 600; color: #111827; text-align: center; user-select: all; display: block; }

/* ── Usage ───────────────────────────────────────────────── */
.st-usage-item { margin-bottom: 1rem; }

.st-usage-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.5rem;
}
.st-usage-name { font-size: 0.9375rem; font-weight: 500; color: #111827; }
.st-usage-pct  { font-size: 0.875rem; font-weight: 600; white-space: nowrap; }
.st-usage-track { height: 0.5rem; background: #f3f4f6; border-radius: 9999px; overflow: hidden; }
.st-usage-fill  { height: 100%; border-radius: 9999px; transition: width 0.5s ease; }

.st-stats-row {
    display: flex;
    gap: 2.5rem;
    margin-top: 1.25rem;
    padding-top: 1.25rem;
    border-top: 1px solid #f3f4f6;
}
.st-stat-num   { font-size: 1.375rem; font-weight: 700; color: #111827; }
.st-stat-num span { font-size: 0.875rem; color: #9ca3af; font-weight: 400; }
.st-stat-label { font-size: 0.75rem; color: #9ca3af; margin-top: 0.125rem; }

.st-extra-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.25rem 0;
}

.st-extra-info-row {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.st-refill-fields {
    display: flex;
    gap: 1.5rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}

/* ── Toggle ──────────────────────────────────────────────── */
.st-toggle { position: relative; width: 2.75rem; height: 1.5rem; border-radius: 9999px; background: #d1d5db; border: none; cursor: pointer; transition: background 0.2s; flex-shrink: 0; padding: 0; }
.st-toggle-on { background: #7E75CE; }
.st-toggle-thumb { position: absolute; top: 0.1875rem; left: 0.1875rem; width: 1.125rem; height: 1.125rem; border-radius: 9999px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.2); transition: transform 0.2s; display: block; }
.st-toggle-on .st-toggle-thumb { transform: translateX(1.25rem); }

/* ── Wallet top-up modal ─────────────────────────────────── */
.wt-overlay {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.45);
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
}
.wt-modal {
    background: #fff;
    border-radius: 1rem;
    box-shadow: 0 20px 60px rgba(0,0,0,0.18);
    width: 100%; max-width: 26rem;
    padding: 1.75rem;
    display: flex; flex-direction: column; gap: 1.25rem;
}
.wt-header {
    display: flex; align-items: center; justify-content: space-between;
}
.wt-title {
    font-size: 1.0625rem; font-weight: 700; color: #111827; margin: 0;
}
.wt-close {
    background: none; border: none; cursor: pointer;
    color: #9ca3af; padding: 0.25rem; border-radius: 0.375rem;
    display: flex; transition: color 0.15s, background 0.15s;
}
.wt-close:hover { color: #374151; background: #f3f4f6; }
.wt-close svg { width: 1.125rem; height: 1.125rem; }

.wt-sub {
    font-size: 0.875rem; color: #6b7280; line-height: 1.5; margin: 0;
}

.wt-presets {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.625rem;
}
.wt-preset {
    padding: 0.625rem 0.5rem; font-size: 0.875rem; font-weight: 600;
    color: #374151; background: #f9fafb;
    border: 1.5px solid #e5e7eb; border-radius: 0.625rem;
    cursor: pointer; text-align: center;
    transition: border-color 0.15s, background 0.15s, color 0.15s;
}
.wt-preset:hover { border-color: #7E75CE; background: #f5f3ff; color: #5b21b6; }
.wt-preset-active {
    border-color: #7E75CE; background: #f5f3ff; color: #5b21b6;
}

.wt-custom-row {
    display: flex; align-items: center; gap: 0.875rem;
}
.wt-custom-label {
    font-size: 0.8125rem; font-weight: 500; color: #6b7280; white-space: nowrap;
}
.wt-custom-wrap {
    display: flex; align-items: center; flex: 1;
    border: 1.5px solid #e5e7eb; border-radius: 0.5rem;
    background: #fafafa; overflow: hidden;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.wt-custom-wrap:focus-within {
    border-color: #7E75CE; box-shadow: 0 0 0 3px rgba(126,117,206,0.1); background: #fff;
}
.wt-custom-input {
    flex: 1; border: none; outline: none; background: transparent;
    padding: 0.5625rem 0.75rem; font-size: 0.9375rem; color: #111827;
    min-width: 0;
}
.wt-custom-input::-webkit-outer-spin-button,
.wt-custom-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.wt-custom-suffix {
    padding: 0 0.75rem; font-size: 0.875rem; color: #9ca3af; font-weight: 500;
}

.wt-summary {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.875rem 1rem;
    background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.625rem;
    font-size: 0.875rem; color: #374151; font-weight: 500;
}
.wt-summary-amount {
    font-size: 1.0625rem; font-weight: 700; color: #111827;
}

.wt-actions {
    display: flex; gap: 0.75rem; justify-content: flex-end;
}
.wt-btn-cancel {
    padding: 0.5625rem 1.125rem; font-size: 0.875rem; font-weight: 500;
    color: #6b7280; background: #fff;
    border: 1.5px solid #e5e7eb; border-radius: 0.5rem;
    cursor: pointer; transition: background 0.15s, border-color 0.15s;
}
.wt-btn-cancel:hover { background: #f9fafb; border-color: #9ca3af; color: #374151; }
.wt-btn-pay {
    padding: 0.5625rem 1.375rem; font-size: 0.875rem; font-weight: 600;
    color: #fff; background: #7E75CE;
    border: none; border-radius: 0.5rem;
    cursor: pointer; transition: background 0.15s, opacity 0.15s;
    display: inline-flex; align-items: center; gap: 0.375rem;
}
.wt-btn-pay:hover:not(:disabled) { background: #6d64bd; }
.wt-btn-pay:disabled { opacity: 0.55; cursor: not-allowed; }

/* ── Modal transition ────────────────────────────────────── */
.modal-fade-enter-active,
.modal-fade-leave-active { transition: opacity 0.2s ease; }
.modal-fade-enter-active .wt-modal,
.modal-fade-leave-active .wt-modal { transition: transform 0.2s ease, opacity 0.2s ease; }
.modal-fade-enter-from,
.modal-fade-leave-to { opacity: 0; }
.modal-fade-enter-from .wt-modal,
.modal-fade-leave-to .wt-modal { transform: translateY(0.5rem) scale(0.97); opacity: 0; }
</style>
