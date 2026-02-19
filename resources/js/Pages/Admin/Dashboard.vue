<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    stats:              Object,
    plans:              Object,
    users:              Object,
    knowledgeSources:   { type: Array, default: () => [] },
    predefinedSources:  { type: Array, default: () => [] },
    viewUser:           { type: Object, default: null },
    userCases:          { type: Array, default: () => [] },
    appSettings:        { type: Object, default: () => ({}) },
    subscriptionPlans:  { type: Array, default: () => [] },
});

const page = usePage();
const flash = computed(() => page.props.flash || {});

const displayId = (id) => {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let seed = id;
    const next = () => { seed = (seed * 1664525 + 1013904223) >>> 0; return chars[seed % chars.length]; };
    return `${next()}${next()}${next()}${next()}-${next()}${next()}${next()}${next()}-${next()}${next()}${next()}${next()}`;
};

// Active tab — auto-switch to 'chat' if viewUser prop present
const activeTab = ref(props.viewUser ? 'chat' : 'overview');
watch(() => props.viewUser, (v) => { if (v) activeTab.value = 'chat'; });

// Navigate to /admin when switching tabs so URL always reflects state
const handleTabClick = (tabId) => {
    activeTab.value = tabId;
    // If we're on a sub-URL (e.g. /admin/users/1/conversations), go back to /admin
    if (!page.url.match(/^\/admin\/?$/)) {
        router.visit(route('admin.dashboard'), { preserveScroll: false });
    }
};

/* ── Users ──────────────────────────────────────────────── */
const deletingUser  = ref(null);
const updatingPlan  = ref(null);

const deleteUser = (user) => {
    if (!confirm(`Slet bruger "${user.name}" (${user.email})? Dette kan ikke fortrydes.`)) return;
    deletingUser.value = user.id;
    router.delete(route('admin.users.destroy', user.id), {
        preserveScroll: true,
        onFinish: () => { deletingUser.value = null; },
    });
};

const updatePlan = (user, plan) => {
    if (user.subscription_plan === plan) return;
    updatingPlan.value = user.id;
    router.patch(route('admin.users.plan', user.id), { plan }, {
        preserveScroll: true,
        onFinish: () => { updatingPlan.value = null; },
    });
};

/* ── Notifications ──────────────────────────────────────── */
const notifTarget  = ref('all');
const notifUserId  = ref('');
const notifTitle   = ref('');
const notifMessage = ref('');
const sendingNotif = ref(false);

const sendNotification = () => {
    if (!notifTitle.value.trim() || !notifMessage.value.trim()) return;
    if (notifTarget.value === 'user' && !notifUserId.value) return;
    sendingNotif.value = true;
    router.post(route('admin.notifications.send'), {
        target:  notifTarget.value,
        user_id: notifTarget.value === 'user' ? notifUserId.value : null,
        title:   notifTitle.value,
        message: notifMessage.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { notifTitle.value = ''; notifMessage.value = ''; notifUserId.value = ''; },
        onFinish:  () => { sendingNotif.value = false; },
    });
};

/* ── Knowledge base ─────────────────────────────────────── */
const indexingSource = ref(null);

const indexPredefined = (src) => {
    if (indexingSource.value) return;
    indexingSource.value = src.url;
    router.post(route('admin.knowledge.index-predefined'), { url: src.url, title: src.title, category: src.category }, {
        preserveScroll: true,
        onFinish: () => { indexingSource.value = null; },
    });
};

const kUrl      = ref('');
const kTitle    = ref('');
const kCategory = ref('separation');
const addingUrl = ref(false);

const addUrl = () => {
    if (!kUrl.value || !kTitle.value) return;
    addingUrl.value = true;
    router.post(route('admin.knowledge.url'), { url: kUrl.value, title: kTitle.value, category: kCategory.value }, {
        preserveScroll: true,
        onSuccess: () => { kUrl.value = ''; kTitle.value = ''; },
        onFinish:  () => { addingUrl.value = false; },
    });
};

const docFile     = ref(null);
const docTitle    = ref('');
const docCategory = ref('separation');
const uploadingDoc = ref(false);

const uploadDoc = () => {
    if (!docFile.value || !docTitle.value) return;
    uploadingDoc.value = true;
    const form = new FormData();
    form.append('file', docFile.value);
    form.append('title', docTitle.value);
    form.append('category', docCategory.value);
    router.post(route('admin.knowledge.document'), form, {
        preserveScroll: true,
        onSuccess: () => { docFile.value = null; docTitle.value = ''; },
        onFinish:  () => { uploadingDoc.value = false; },
    });
};

const handleFileInput = (e) => { docFile.value = e.target.files[0] || null; };

const deleteSource = (sourceUrl) => {
    if (!confirm('Slet alle chunks fra denne kilde?')) return;
    router.delete(route('admin.knowledge.source.destroy'), { data: { source_url: sourceUrl }, preserveScroll: true });
};

/* ── User conversations ─────────────────────────────────── */
const selectedChatUser = ref(props.viewUser?.id || '');
const openCase         = ref(null);

const loadConversations = () => {
    if (!selectedChatUser.value) return;
    router.get(route('admin.users.conversations', selectedChatUser.value), {}, { preserveState: false });
};

/* ── API settings ───────────────────────────────────────── */
const settingsForm = ref({
    stripe_key:            '',
    stripe_secret:         '',
    stripe_webhook_secret: '',
    google_client_id:      '',
    google_client_secret:  '',
    openai_api_key:        '',
    anthropic_api_key:     '',
    mistral_api_key:       '',
});
const savingSettings = ref(false);

const saveSettings = () => {
    savingSettings.value = true;
    router.patch(route('admin.settings.update'), settingsForm.value, {
        preserveScroll: true,
        onSuccess: () => { Object.keys(settingsForm.value).forEach(k => { settingsForm.value[k] = ''; }); },
        onFinish:  () => { savingSettings.value = false; },
    });
};

/* ── Subscription plan management ───────────────────────── */
const emptyPlan = () => ({
    slug: '', name: '', description: '', price: 0, messages_limit: 50,
    features: '', stripe_price_id: '', color: '#7E75CE',
    is_popular: false, is_active: true, sort_order: 0,
});

const showNewPlanForm = ref(false);
const newPlan = ref(emptyPlan());
const savingNewPlan = ref(false);
const editingPlanId = ref(null);
const editPlan = ref(emptyPlan());
const savingEditPlan = ref(false);
const deletingPlanId = ref(null);

const featuresText = (arr) => Array.isArray(arr) ? arr.join('\n') : (arr || '');

const startEditPlan = (plan) => {
    editingPlanId.value = plan.id;
    editPlan.value = {
        ...plan,
        features: featuresText(plan.features),
    };
};

const cancelEditPlan = () => { editingPlanId.value = null; };

const savePlan = () => {
    savingEditPlan.value = true;
    router.patch(route('admin.subscription-plans.update', editingPlanId.value), editPlan.value, {
        preserveScroll: true,
        onSuccess: () => { editingPlanId.value = null; },
        onFinish: () => { savingEditPlan.value = false; },
    });
};

const createPlan = () => {
    savingNewPlan.value = true;
    router.post(route('admin.subscription-plans.store'), newPlan.value, {
        preserveScroll: true,
        onSuccess: () => { showNewPlanForm.value = false; newPlan.value = emptyPlan(); },
        onFinish: () => { savingNewPlan.value = false; },
    });
};

const deletePlan = (plan) => {
    if (!confirm(`Slet plan "${plan.name}"?`)) return;
    deletingPlanId.value = plan.id;
    router.delete(route('admin.subscription-plans.destroy', plan.id), {
        preserveScroll: true,
        onFinish: () => { deletingPlanId.value = null; },
    });
};

/* ── Helpers ────────────────────────────────────────────── */
const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('da-DK', { day: 'numeric', month: 'short', year: 'numeric' });
};

const planLabel = { free: 'Gratis', pro: 'Pro', business: 'Business' };
const planColor = { free: '#6b7280', pro: '#7c3aed', business: '#0891b2' };
const totalPlans = computed(() => Object.values(props.plans).reduce((a, b) => a + b, 0));

const categories = [
    { value: 'separation',       label: 'Separation' },
    { value: 'samvaer',          label: 'Samvær' },
    { value: 'bodeling',         label: 'Bodel­ing' },
    { value: 'vold',             label: 'Vold' },
    { value: 'boern',            label: 'Børn' },
    { value: 'foraldremyndighed',label: 'Forældremyndighed' },
    { value: 'generelt',         label: 'Generelt' },
];

const nav = [
    { id: 'overview',     label: 'Oversigt',          icon: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z' },
    { id: 'plans',        label: 'Abonnementer',       icon: 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z' },
    { id: 'users',        label: 'Brugere',            icon: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z' },
    { id: 'notify',       label: 'Notifikationer',     icon: 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0' },
    { id: 'knowledge',    label: 'Vidensbase',         icon: 'M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25' },
    { id: 'chat',         label: 'Brugerchat',         icon: 'M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V20.25a.75.75 0 0 0 1.28.53l3.72-3.72A48.666 48.666 0 0 0 11.25 17c2.115 0 4.198-.137 6.24-.402 1.608-.209 2.76-1.614 2.76-3.235v-3.752Z' },
    { id: 'appsettings',  label: 'API-indstillinger',  icon: 'M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 0 1 21.75 8.25Z' },
];
</script>

<template>
    <Head title="Admin Panel" />

    <div class="adm-layout">
        <!-- Sidebar -->
        <aside class="adm-sidebar">
            <div class="adm-sidebar-header">
                <div class="adm-sidebar-logo">
                    <svg xmlns="http://www.w3.org/2000/svg" class="adm-logo-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <span>Admin</span>
                </div>
            </div>

            <nav class="adm-nav">
                <button v-for="item in nav" :key="item.id"
                    :class="['adm-nav-item', { 'adm-nav-item-active': activeTab === item.id }]"
                    @click="handleTabClick(item.id)">
                    <svg xmlns="http://www.w3.org/2000/svg" class="adm-nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                    </svg>
                    <span>{{ item.label }}</span>
                    <span v-if="item.id === 'users'" class="adm-nav-badge">{{ stats.total_users }}</span>
                    <span v-if="item.id === 'knowledge'" class="adm-nav-badge">{{ knowledgeSources.length }}</span>
                </button>
            </nav>

            <div class="adm-sidebar-footer">
                <Link :href="route('dashboard')" class="adm-nav-item adm-nav-footer">
                    <svg xmlns="http://www.w3.org/2000/svg" class="adm-nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    <span>Tilbage til app</span>
                </Link>
            </div>
        </aside>

        <!-- Main -->
        <div class="adm-main">
            <!-- Flash -->
            <div v-if="flash.success" class="adm-flash adm-flash-green">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                {{ flash.success }}
            </div>
            <div v-if="flash.error" class="adm-flash adm-flash-red">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                {{ flash.error }}
            </div>

            <!-- OVERSIGT -->
            <section v-if="activeTab === 'overview'">
                <h2 class="adm-section-title">Statistikker</h2>
                <div class="adm-stats-grid">
                    <div class="adm-stat-card">
                        <div class="adm-stat-value">{{ stats.total_users }}</div>
                        <div class="adm-stat-label">Brugere i alt</div>
                    </div>
                    <div class="adm-stat-card">
                        <div class="adm-stat-value" style="color:#059669">{{ stats.users_today }}</div>
                        <div class="adm-stat-label">Nye i dag</div>
                    </div>
                    <div class="adm-stat-card">
                        <div class="adm-stat-value">{{ stats.total_conversations }}</div>
                        <div class="adm-stat-label">Sager</div>
                    </div>
                    <div class="adm-stat-card">
                        <div class="adm-stat-value">{{ stats.total_documents }}</div>
                        <div class="adm-stat-label">Dokumenter</div>
                    </div>
                    <div class="adm-stat-card">
                        <div class="adm-stat-value">{{ stats.total_tasks }}</div>
                        <div class="adm-stat-label">Opgaver</div>
                    </div>
                    <div class="adm-stat-card">
                        <div class="adm-stat-value" style="color:#7c3aed">{{ stats.ai_messages_total.toLocaleString('da-DK') }}</div>
                        <div class="adm-stat-label">AI-beskeder sendt</div>
                    </div>
                </div>
            </section>

            <!-- ABONNEMENTER -->
            <section v-if="activeTab === 'plans'">
                <!-- Fordeling -->
                <h2 class="adm-section-title">Abonnementsfordeling</h2>
                <div class="adm-plans-grid">
                    <div v-for="plan in ['free', 'pro', 'business']" :key="plan" class="adm-plan-card">
                        <div class="adm-plan-count" :style="{ color: planColor[plan] }">{{ plans[plan] || 0 }}</div>
                        <div class="adm-plan-name">{{ planLabel[plan] }}</div>
                        <div class="adm-plan-pct">{{ totalPlans > 0 ? Math.round(((plans[plan] || 0) / totalPlans) * 100) : 0 }}%</div>
                        <div class="adm-plan-bar-wrap">
                            <div class="adm-plan-bar" :style="{ width: totalPlans > 0 ? ((plans[plan] || 0) / totalPlans * 100) + '%' : '0%', background: planColor[plan] }"></div>
                        </div>
                    </div>
                </div>

                <!-- Plan konfiguration -->
                <div class="adm-sp-header">
                    <h2 class="adm-section-title" style="margin:0">Pakkekonfiguration</h2>
                    <button @click="showNewPlanForm = !showNewPlanForm" class="adm-btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        Ny pakke
                    </button>
                </div>

                <!-- Ny pakke formular -->
                <div v-if="showNewPlanForm" class="adm-sp-form adm-sp-form-new">
                    <h3 class="adm-sp-form-title">Opret ny pakke</h3>
                    <div class="adm-sp-grid">
                        <div class="adm-sp-field">
                            <label>Slug <span class="adm-sp-hint">(fx: premium)</span></label>
                            <input v-model="newPlan.slug" type="text" placeholder="custom-plan" class="adm-input" />
                        </div>
                        <div class="adm-sp-field">
                            <label>Navn</label>
                            <input v-model="newPlan.name" type="text" placeholder="Premium" class="adm-input" />
                        </div>
                        <div class="adm-sp-field">
                            <label>Pris (kr/md)</label>
                            <input v-model.number="newPlan.price" type="number" min="0" class="adm-input" />
                        </div>
                        <div class="adm-sp-field">
                            <label>AI-beskeder/md <span class="adm-sp-hint">(0 = ubegrænset)</span></label>
                            <input v-model.number="newPlan.messages_limit" type="number" min="0" class="adm-input" />
                        </div>
                        <div class="adm-sp-field">
                            <label>Stripe Price ID</label>
                            <input v-model="newPlan.stripe_price_id" type="text" placeholder="price_xxxxxxxxxxxxxxxx" class="adm-input adm-input-mono" />
                        </div>
                        <div class="adm-sp-field">
                            <label>Farve (hex)</label>
                            <div class="adm-sp-color-row">
                                <input type="color" v-model="newPlan.color" class="adm-color-input" />
                                <input v-model="newPlan.color" type="text" class="adm-input" style="flex:1" />
                            </div>
                        </div>
                        <div class="adm-sp-field">
                            <label>Rækkefølge</label>
                            <input v-model.number="newPlan.sort_order" type="number" min="0" class="adm-input" />
                        </div>
                        <div class="adm-sp-field adm-sp-checkboxes">
                            <label><input type="checkbox" v-model="newPlan.is_popular" /> Mest populær</label>
                            <label><input type="checkbox" v-model="newPlan.is_active" /> Aktiv</label>
                        </div>
                    </div>
                    <div class="adm-sp-field" style="margin-top:.75rem">
                        <label>Beskrivelse</label>
                        <input v-model="newPlan.description" type="text" placeholder="Kort beskrivelse af pakken" class="adm-input" />
                    </div>
                    <div class="adm-sp-field" style="margin-top:.75rem">
                        <label>Features <span class="adm-sp-hint">(én per linje)</span></label>
                        <textarea v-model="newPlan.features" rows="4" placeholder="500 AI-beskeder om måneden&#10;Ubegrænset sager&#10;Dokumentupload" class="adm-input adm-textarea"></textarea>
                    </div>
                    <div class="adm-sp-actions">
                        <button @click="createPlan" :disabled="savingNewPlan" class="adm-btn-primary">
                            {{ savingNewPlan ? 'Gemmer...' : 'Opret pakke' }}
                        </button>
                        <button @click="showNewPlanForm = false" class="adm-btn-ghost">Annuller</button>
                    </div>
                </div>

                <!-- Liste af planer -->
                <div class="adm-sp-list">
                    <div v-for="sp in subscriptionPlans" :key="sp.id" class="adm-sp-item">
                        <!-- Vis-tilstand -->
                        <div v-if="editingPlanId !== sp.id" class="adm-sp-row">
                            <div class="adm-sp-dot" :style="{ background: sp.color }"></div>
                            <div class="adm-sp-info">
                                <div class="adm-sp-name">
                                    {{ sp.name }}
                                    <span v-if="sp.is_popular" class="adm-badge-popular">Populær</span>
                                    <span v-if="!sp.is_active" class="adm-badge-inactive">Inaktiv</span>
                                </div>
                                <div class="adm-sp-meta">{{ sp.price }} kr/md · {{ sp.messages_limit === 0 ? 'Ubegrænset' : sp.messages_limit + ' beskeder' }}</div>
                            </div>
                            <div class="adm-sp-stripe" :class="sp.stripe_price_id ? 'adm-sp-stripe-ok' : 'adm-sp-stripe-missing'">
                                <span v-if="sp.stripe_price_id">
                                    <span class="adm-dot-ok">●</span> {{ sp.stripe_price_id.substring(0, 20) }}…
                                </span>
                                <span v-else><span class="adm-dot-off">○</span> Stripe ID mangler</span>
                            </div>
                            <div class="adm-sp-btns">
                                <button @click="startEditPlan(sp)" class="adm-icon-btn" title="Rediger">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                                </button>
                                <button @click="deletePlan(sp)" :disabled="deletingPlanId === sp.id" class="adm-delete-btn" title="Slet">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Rediger-tilstand -->
                        <div v-else class="adm-sp-form">
                            <h3 class="adm-sp-form-title">Rediger: {{ sp.name }}</h3>
                            <div class="adm-sp-grid">
                                <div class="adm-sp-field">
                                    <label>Navn</label>
                                    <input v-model="editPlan.name" type="text" class="adm-input" />
                                </div>
                                <div class="adm-sp-field">
                                    <label>Pris (kr/md)</label>
                                    <input v-model.number="editPlan.price" type="number" min="0" class="adm-input" />
                                </div>
                                <div class="adm-sp-field">
                                    <label>AI-beskeder/md <span class="adm-sp-hint">(0 = ubegrænset)</span></label>
                                    <input v-model.number="editPlan.messages_limit" type="number" min="0" class="adm-input" />
                                </div>
                                <div class="adm-sp-field">
                                    <label>Stripe Price ID</label>
                                    <input v-model="editPlan.stripe_price_id" type="text" placeholder="price_xxxxxxxxxxxxxxxx" class="adm-input adm-input-mono" />
                                </div>
                                <div class="adm-sp-field">
                                    <label>Farve (hex)</label>
                                    <div class="adm-sp-color-row">
                                        <input type="color" v-model="editPlan.color" class="adm-color-input" />
                                        <input v-model="editPlan.color" type="text" class="adm-input" style="flex:1" />
                                    </div>
                                </div>
                                <div class="adm-sp-field">
                                    <label>Rækkefølge</label>
                                    <input v-model.number="editPlan.sort_order" type="number" min="0" class="adm-input" />
                                </div>
                                <div class="adm-sp-field adm-sp-checkboxes">
                                    <label><input type="checkbox" v-model="editPlan.is_popular" /> Mest populær</label>
                                    <label><input type="checkbox" v-model="editPlan.is_active" /> Aktiv</label>
                                </div>
                            </div>
                            <div class="adm-sp-field" style="margin-top:.75rem">
                                <label>Beskrivelse</label>
                                <input v-model="editPlan.description" type="text" class="adm-input" />
                            </div>
                            <div class="adm-sp-field" style="margin-top:.75rem">
                                <label>Features <span class="adm-sp-hint">(én per linje)</span></label>
                                <textarea v-model="editPlan.features" rows="4" class="adm-input adm-textarea"></textarea>
                            </div>
                            <div class="adm-sp-actions">
                                <button @click="savePlan" :disabled="savingEditPlan" class="adm-btn-primary">
                                    {{ savingEditPlan ? 'Gemmer...' : 'Gem ændringer' }}
                                </button>
                                <button @click="cancelEditPlan" class="adm-btn-ghost">Annuller</button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- BRUGERE -->
            <section v-if="activeTab === 'users'">
                <h2 class="adm-section-title">Brugerliste</h2>
                <div class="adm-table-wrap">
                    <table class="adm-table">
                        <thead>
                            <tr>
                                <th>#</th><th>Navn</th><th>Email</th><th>Login</th><th>Plan</th><th>AI-forbrug</th><th>Tilmeldt</th><th>Chat</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in users.data" :key="user.id" :class="{ 'adm-row-admin': user.is_admin }">
                                <td class="adm-user-id" :title="'DB ID: ' + user.id">{{ displayId(user.id) }}</td>
                                <td>
                                    <span class="adm-user-name">{{ user.name }}</span>
                                    <span v-if="user.is_admin" class="adm-badge-admin">Admin</span>
                                </td>
                                <td class="adm-user-email">{{ user.email }}</td>
                                <td>
                                    <span v-if="user.google_id" class="adm-badge-google">Google</span>
                                    <span v-else class="adm-badge-email">Email</span>
                                </td>
                                <td>
                                    <select :value="user.subscription_plan" @change="updatePlan(user, $event.target.value)" :disabled="updatingPlan === user.id || user.is_admin" class="adm-plan-select">
                                        <option value="free">Gratis</option>
                                        <option value="pro">Pro</option>
                                        <option value="business">Business</option>
                                    </select>
                                </td>
                                <td><span class="adm-ai-usage">{{ user.ai_messages_used }} / {{ user.ai_messages_limit === 99999 ? '∞' : user.ai_messages_limit }}</span></td>
                                <td class="adm-date">{{ formatDate(user.created_at) }}</td>
                                <td>
                                    <button @click="router.get(route('admin.users.conversations', user.id))" class="adm-icon-btn" title="Se samtaler">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.068.157 2.148.279 3.238.364.466.037.893.281 1.153.671L12 21l2.652-3.978c.26-.39.687-.634 1.153-.67 1.09-.086 2.17-.208 3.238-.365 1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" /></svg>
                                    </button>
                                </td>
                                <td>
                                    <button v-if="!user.is_admin" @click="deleteUser(user)" :disabled="deletingUser === user.id" class="adm-delete-btn" title="Slet bruger">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="users.last_page > 1" class="adm-pagination">
                    <Link v-for="link in users.links" :key="link.label" :href="link.url || '#'" v-html="link.label" :class="['adm-page-btn', { 'adm-page-btn-active': link.active, 'adm-page-btn-disabled': !link.url }]" />
                </div>
            </section>

            <!-- SEND NOTIFIKATION -->
            <section v-if="activeTab === 'notify'">
                <h2 class="adm-section-title">Send notifikation</h2>
                <div class="adm-form-card">
                    <div class="adm-field">
                        <label class="adm-label">Modtager</label>
                        <div class="adm-radio-group">
                            <label class="adm-radio"><input type="radio" v-model="notifTarget" value="all" /> Alle brugere ({{ stats.total_users }})</label>
                            <label class="adm-radio"><input type="radio" v-model="notifTarget" value="user" /> Specifik bruger</label>
                        </div>
                    </div>
                    <div v-if="notifTarget === 'user'" class="adm-field">
                        <label class="adm-label">Vælg bruger</label>
                        <select v-model="notifUserId" class="adm-input">
                            <option value="">— Vælg —</option>
                            <option v-for="user in users.data" :key="user.id" :value="user.id">{{ user.name }} ({{ user.email }})</option>
                        </select>
                    </div>
                    <div class="adm-field">
                        <label class="adm-label">Titel <span class="adm-char-count">{{ notifTitle.length }}/120</span></label>
                        <input v-model="notifTitle" maxlength="120" placeholder="Fx: Opdatering fra Aura" class="adm-input" />
                    </div>
                    <div class="adm-field">
                        <label class="adm-label">Besked <span class="adm-char-count">{{ notifMessage.length }}/500</span></label>
                        <textarea v-model="notifMessage" maxlength="500" rows="4" placeholder="Skriv din besked her..." class="adm-input adm-textarea"></textarea>
                    </div>
                    <button @click="sendNotification" :disabled="sendingNotif || !notifTitle.trim() || !notifMessage.trim() || (notifTarget === 'user' && !notifUserId)" class="adm-send-btn">
                        {{ sendingNotif ? 'Sender...' : notifTarget === 'all' ? `Send til alle (${stats.total_users})` : 'Send notifikation' }}
                    </button>
                </div>
            </section>

            <!-- VIDENSBASE -->
            <section v-if="activeTab === 'knowledge'">
                <h2 class="adm-section-title">Vidensbase (RAG)</h2>

                <!-- Predefined sources -->
                <h3 class="adm-form-heading" style="margin-bottom:0.75rem">Foruddefinerede kilder</h3>
                <div class="adm-table-wrap" style="margin-bottom:2rem">
                    <table class="adm-table">
                        <thead>
                            <tr><th>Titel</th><th>Kategori</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="src in predefinedSources" :key="src.url">
                                <td>
                                    <a :href="src.url" target="_blank" class="adm-link adm-user-name">{{ src.title }}</a>
                                </td>
                                <td><span class="adm-badge-email">{{ src.category }}</span></td>
                                <td>
                                    <span v-if="src.indexed" class="adm-set-badge" style="font-size:0.75rem;padding:0.2rem 0.5rem">✓ Indekseret</span>
                                    <span v-else style="font-size:0.8125rem;color:#9ca3af">Ikke indekseret</span>
                                </td>
                                <td>
                                    <button @click="indexPredefined(src)" :disabled="indexingSource === src.url || !!indexingSource" class="adm-index-btn">
                                        {{ indexingSource === src.url ? 'Indekserer…' : src.indexed ? 'Genindekser' : 'Indekser' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Indexed sources list -->
                <h3 class="adm-form-heading" style="margin-bottom:0.75rem">Indekserede kilder i databasen</h3>
                <div class="adm-table-wrap" style="margin-bottom:2rem">
                    <table class="adm-table">
                        <thead>
                            <tr><th>Titel</th><th>Kilde / URL</th><th>Kategori</th><th>Chunks</th><th>Opdateret</th><th></th></tr>
                        </thead>
                        <tbody>
                            <tr v-if="knowledgeSources.length === 0">
                                <td colspan="6" style="text-align:center;color:#9ca3af;padding:2rem">Ingen kilder i vidensbasen endnu</td>
                            </tr>
                            <tr v-for="src in knowledgeSources" :key="src.source_url">
                                <td class="adm-user-name">{{ src.source_title }}</td>
                                <td><a :href="src.source_url.startsWith('upload:') ? '#' : src.source_url" target="_blank" class="adm-link" :title="src.source_url">{{ src.source_url.length > 50 ? src.source_url.slice(0,50)+'…' : src.source_url }}</a></td>
                                <td><span class="adm-badge-email">{{ src.category }}</span></td>
                                <td class="adm-ai-usage">{{ src.chunks }}</td>
                                <td class="adm-date">{{ formatDate(src.scraped_at) }}</td>
                                <td>
                                    <button @click="deleteSource(src.source_url)" class="adm-delete-btn" title="Slet kilde">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Add forms side by side -->
                <div class="adm-knowledge-forms">
                    <!-- Add URL -->
                    <div class="adm-form-card">
                        <h3 class="adm-form-heading">Tilføj URL</h3>
                        <div class="adm-field">
                            <label class="adm-label">URL</label>
                            <input v-model="kUrl" type="url" class="adm-input" placeholder="https://www.borger.dk/..." />
                        </div>
                        <div class="adm-field">
                            <label class="adm-label">Titel</label>
                            <input v-model="kTitle" class="adm-input" placeholder="Fx: Skilsmisse på borger.dk" />
                        </div>
                        <div class="adm-field">
                            <label class="adm-label">Kategori</label>
                            <select v-model="kCategory" class="adm-input">
                                <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
                            </select>
                        </div>
                        <button @click="addUrl" :disabled="addingUrl || !kUrl || !kTitle" class="adm-send-btn">
                            {{ addingUrl ? 'Scraper og indekserer…' : 'Tilføj URL' }}
                        </button>
                        <p class="adm-hint">Siden hentes, opdeles i chunks og indekseres med embeddings. Dette kan tage 10–30 sekunder.</p>
                    </div>

                    <!-- Upload document -->
                    <div class="adm-form-card">
                        <h3 class="adm-form-heading">Upload dokument</h3>
                        <div class="adm-field">
                            <label class="adm-label">Fil <span style="color:#9ca3af;font-weight:400">(PDF, Word eller TXT · maks 30 MB)</span></label>
                            <input type="file" accept=".txt,.pdf,.docx,.doc" @change="handleFileInput" class="adm-input adm-file-input" />
                        </div>
                        <div class="adm-field">
                            <label class="adm-label">Titel</label>
                            <input v-model="docTitle" class="adm-input" placeholder="Fx: Forældreansvarslovens vejledning" />
                        </div>
                        <div class="adm-field">
                            <label class="adm-label">Kategori</label>
                            <select v-model="docCategory" class="adm-input">
                                <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
                            </select>
                        </div>
                        <button @click="uploadDoc" :disabled="uploadingDoc || !docFile || !docTitle" class="adm-send-btn">
                            {{ uploadingDoc ? 'Uploader og indekserer…' : 'Upload dokument' }}
                        </button>
                        <p class="adm-hint">Teksten udtrækkes automatisk, opdeles i chunks og indekseres med AI-embeddings.</p>
                    </div>
                </div>
            </section>

            <!-- BRUGERCHAT -->
            <section v-if="activeTab === 'chat'">
                <h2 class="adm-section-title">Brugerchat</h2>

                <div style="display:flex;gap:0.75rem;align-items:flex-end;margin-bottom:1.5rem">
                    <div style="flex:1;max-width:22rem">
                        <label class="adm-label" style="margin-bottom:0.375rem">Vælg bruger</label>
                        <select v-model="selectedChatUser" class="adm-input">
                            <option value="">— Vælg bruger —</option>
                            <option v-for="u in users.data" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
                        </select>
                    </div>
                    <button @click="loadConversations" :disabled="!selectedChatUser" class="adm-send-btn" style="width:auto;padding:0.625rem 1.25rem">Vis samtaler</button>
                </div>

                <div v-if="viewUser" style="margin-bottom:1rem">
                    <span style="font-size:0.875rem;color:#6b7280">Samtaler for <strong style="color:#111827">{{ viewUser.name }}</strong> ({{ viewUser.email }})</span>
                </div>

                <div v-if="userCases.length === 0 && viewUser" class="adm-empty">Ingen sager fundet for denne bruger.</div>

                <div v-for="c in userCases" :key="c.id" class="adm-case-accordion">
                    <button class="adm-case-header" @click="openCase = openCase === c.id ? null : c.id">
                        <span class="adm-case-title">{{ c.title || 'Unavngivet sag' }}</span>
                        <span class="adm-badge-email" style="margin-left:0.5rem">{{ c.status }}</span>
                        <span style="margin-left:auto;font-size:0.8125rem;color:#9ca3af">{{ formatDate(c.created_at) }}</span>
                        <span style="margin-left:0.75rem;color:#9ca3af">{{ c.conversations?.length || 0 }} beskeder</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:1rem;height:1rem;margin-left:0.5rem;transition:transform 0.15s" :style="{ transform: openCase === c.id ? 'rotate(180deg)' : '' }"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>
                    <div v-if="openCase === c.id" class="adm-messages">
                        <div v-if="!c.conversations?.length" class="adm-empty" style="padding:1rem">Ingen beskeder.</div>
                        <div v-for="msg in c.conversations" :key="msg.id" :class="['adm-msg', msg.role === 'user' ? 'adm-msg-user' : 'adm-msg-assistant']">
                            <span class="adm-msg-role">{{ msg.role === 'user' ? 'Bruger' : 'Aura AI' }}</span>
                            <p class="adm-msg-content">{{ msg.content }}</p>
                            <span v-if="msg.model_used" class="adm-msg-meta">{{ msg.model_used }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- API-INDSTILLINGER -->
            <section v-if="activeTab === 'appsettings'">
                <h2 class="adm-section-title">API-indstillinger</h2>
                <p style="font-size:0.875rem;color:#6b7280;margin-bottom:1.5rem">Efterlad felter tomme for at beholde de nuværende værdier.</p>

                <!-- Stripe -->
                <div class="adm-settings-section">
                    <h3 class="adm-form-heading">
                        Stripe
                        <span class="adm-api-group-status">
                            <span :class="['adm-api-dot', (appSettings.stripe_key?.is_set && appSettings.stripe_secret?.is_set && appSettings.stripe_webhook_secret?.is_set) ? 'adm-api-dot-ok' : 'adm-api-dot-off']"></span>
                            {{ (appSettings.stripe_key?.is_set && appSettings.stripe_secret?.is_set && appSettings.stripe_webhook_secret?.is_set) ? 'Forbundet' : 'Ikke konfigureret' }}
                        </span>
                    </h3>
                    <div class="adm-settings-grid">
                        <div class="adm-field">
                            <label class="adm-label">
                                Stripe Public Key
                                <span :class="['adm-api-indicator', appSettings.stripe_key?.is_set ? 'adm-api-indicator-ok' : 'adm-api-indicator-off']">
                                    {{ appSettings.stripe_key?.is_set ? '● Sat' : '○ Ikke sat' }}
                                </span>
                            </label>
                            <input v-model="settingsForm.stripe_key" class="adm-input" placeholder="pk_live_..." />
                            <div v-if="appSettings.stripe_key?.preview" class="adm-api-preview">{{ appSettings.stripe_key.preview }}</div>
                        </div>
                        <div class="adm-field">
                            <label class="adm-label">
                                Stripe Secret Key
                                <span :class="['adm-api-indicator', appSettings.stripe_secret?.is_set ? 'adm-api-indicator-ok' : 'adm-api-indicator-off']">
                                    {{ appSettings.stripe_secret?.is_set ? '● Sat' : '○ Ikke sat' }}
                                </span>
                            </label>
                            <input v-model="settingsForm.stripe_secret" type="password" class="adm-input" placeholder="sk_live_..." />
                            <div v-if="appSettings.stripe_secret?.preview" class="adm-api-preview">{{ appSettings.stripe_secret.preview }}</div>
                        </div>
                        <div class="adm-field">
                            <label class="adm-label">
                                Stripe Webhook Secret
                                <span :class="['adm-api-indicator', appSettings.stripe_webhook_secret?.is_set ? 'adm-api-indicator-ok' : 'adm-api-indicator-off']">
                                    {{ appSettings.stripe_webhook_secret?.is_set ? '● Sat' : '○ Ikke sat' }}
                                </span>
                            </label>
                            <input v-model="settingsForm.stripe_webhook_secret" type="password" class="adm-input" placeholder="whsec_..." />
                            <div v-if="appSettings.stripe_webhook_secret?.preview" class="adm-api-preview">{{ appSettings.stripe_webhook_secret.preview }}</div>
                        </div>
                    </div>
                </div>

                <!-- Google -->
                <div class="adm-settings-section">
                    <h3 class="adm-form-heading">
                        Google OAuth
                        <span class="adm-api-group-status">
                            <span :class="['adm-api-dot', (appSettings.google_client_id?.is_set && appSettings.google_client_secret?.is_set) ? 'adm-api-dot-ok' : 'adm-api-dot-off']"></span>
                            {{ (appSettings.google_client_id?.is_set && appSettings.google_client_secret?.is_set) ? 'Forbundet' : 'Ikke konfigureret' }}
                        </span>
                    </h3>
                    <div class="adm-settings-grid">
                        <div class="adm-field">
                            <label class="adm-label">
                                Client ID
                                <span :class="['adm-api-indicator', appSettings.google_client_id?.is_set ? 'adm-api-indicator-ok' : 'adm-api-indicator-off']">
                                    {{ appSettings.google_client_id?.is_set ? '● Sat' : '○ Ikke sat' }}
                                </span>
                            </label>
                            <input v-model="settingsForm.google_client_id" class="adm-input" placeholder="123456789-abc.apps.googleusercontent.com" />
                            <div v-if="appSettings.google_client_id?.preview" class="adm-api-preview">{{ appSettings.google_client_id.preview }}</div>
                        </div>
                        <div class="adm-field">
                            <label class="adm-label">
                                Client Secret
                                <span :class="['adm-api-indicator', appSettings.google_client_secret?.is_set ? 'adm-api-indicator-ok' : 'adm-api-indicator-off']">
                                    {{ appSettings.google_client_secret?.is_set ? '● Sat' : '○ Ikke sat' }}
                                </span>
                            </label>
                            <input v-model="settingsForm.google_client_secret" type="password" class="adm-input" placeholder="GOCSPX-..." />
                            <div v-if="appSettings.google_client_secret?.preview" class="adm-api-preview">{{ appSettings.google_client_secret.preview }}</div>
                        </div>
                    </div>
                </div>

                <!-- AI -->
                <div class="adm-settings-section">
                    <h3 class="adm-form-heading">
                        AI-modeller
                        <span class="adm-api-group-status">
                            <span :class="['adm-api-dot', (appSettings.openai_api_key?.is_set || appSettings.anthropic_api_key?.is_set || appSettings.mistral_api_key?.is_set) ? 'adm-api-dot-ok' : 'adm-api-dot-off']"></span>
                            {{ (appSettings.openai_api_key?.is_set || appSettings.anthropic_api_key?.is_set || appSettings.mistral_api_key?.is_set) ? 'Min. 1 aktiv' : 'Ingen konfigureret' }}
                        </span>
                    </h3>
                    <div class="adm-settings-grid">
                        <div class="adm-field">
                            <label class="adm-label">
                                OpenAI API Key
                                <span :class="['adm-api-indicator', appSettings.openai_api_key?.is_set ? 'adm-api-indicator-ok' : 'adm-api-indicator-off']">
                                    {{ appSettings.openai_api_key?.is_set ? '● Aktiv' : '○ Ikke sat' }}
                                </span>
                            </label>
                            <input v-model="settingsForm.openai_api_key" type="password" class="adm-input" placeholder="sk-..." />
                            <div v-if="appSettings.openai_api_key?.preview" class="adm-api-preview">{{ appSettings.openai_api_key.preview }}</div>
                        </div>
                        <div class="adm-field">
                            <label class="adm-label">
                                Anthropic API Key
                                <span :class="['adm-api-indicator', appSettings.anthropic_api_key?.is_set ? 'adm-api-indicator-ok' : 'adm-api-indicator-off']">
                                    {{ appSettings.anthropic_api_key?.is_set ? '● Aktiv' : '○ Ikke sat' }}
                                </span>
                            </label>
                            <input v-model="settingsForm.anthropic_api_key" type="password" class="adm-input" placeholder="sk-ant-..." />
                            <div v-if="appSettings.anthropic_api_key?.preview" class="adm-api-preview">{{ appSettings.anthropic_api_key.preview }}</div>
                        </div>
                        <div class="adm-field">
                            <label class="adm-label">
                                Mistral API Key
                                <span :class="['adm-api-indicator', appSettings.mistral_api_key?.is_set ? 'adm-api-indicator-ok' : 'adm-api-indicator-off']">
                                    {{ appSettings.mistral_api_key?.is_set ? '● Aktiv' : '○ Ikke sat' }}
                                </span>
                            </label>
                            <input v-model="settingsForm.mistral_api_key" type="password" class="adm-input" placeholder="..." />
                            <div v-if="appSettings.mistral_api_key?.preview" class="adm-api-preview">{{ appSettings.mistral_api_key.preview }}</div>
                        </div>
                    </div>
                </div>

                <button @click="saveSettings" :disabled="savingSettings" class="adm-send-btn" style="max-width:16rem">
                    {{ savingSettings ? 'Gemmer…' : 'Gem indstillinger' }}
                </button>
            </section>
        </div>
    </div>
</template>

<style scoped>
/* Layout */
.adm-layout { display: flex; height: 100vh; background: #f9f9f9; font-family: inherit; overflow: hidden; }

/* Sidebar */
.adm-sidebar { display: flex; flex-direction: column; width: 15rem; height: 100%; background: #f9f9f9; border-right: 1px solid #e5e7eb; flex-shrink: 0; }
.adm-sidebar-header { padding: 1rem 1rem 0.75rem; border-bottom: 1px solid #e5e7eb; }
.adm-sidebar-logo { display: flex; align-items: center; gap: 0.5rem; font-size: 0.9375rem; font-weight: 600; color: #111827; }
.adm-logo-icon { width: 1.25rem; height: 1.25rem; color: #6b7280; flex-shrink: 0; }
.adm-nav { display: flex; flex-direction: column; gap: 0.125rem; padding: 0.75rem 0.75rem 0.5rem; flex: 1; overflow-y: auto; }
.adm-nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: 0.5rem; font-size: 0.875rem; color: #374151; background: transparent; border: none; cursor: pointer; text-align: left; text-decoration: none; transition: all 0.15s ease; width: 100%; }
.adm-nav-item:hover { background: #f3f4f6; color: #111827; }
.adm-nav-item-active { background: #f3f4f6; color: #111827; font-weight: 500; }
.adm-nav-icon { width: 1.25rem; height: 1.25rem; flex-shrink: 0; color: #6b7280; }
.adm-nav-item-active .adm-nav-icon, .adm-nav-item:hover .adm-nav-icon { color: #374151; }
.adm-nav-badge { margin-left: auto; font-size: 0.6875rem; font-weight: 600; background: #e5e7eb; color: #374151; padding: 0.125rem 0.4rem; border-radius: 9999px; }
.adm-sidebar-footer { padding: 0.75rem; border-top: 1px solid #e5e7eb; }
.adm-nav-footer { color: #6b7280; }

/* Main */
.adm-main { flex: 1; overflow-y: auto; padding: 2rem; background: #fff; }
.adm-section-title { font-size: 1rem; font-weight: 600; color: #111827; margin-bottom: 1.25rem; }

/* Flash */
.adm-flash { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; border-radius: 0.625rem; font-size: 0.875rem; margin-bottom: 1.5rem; }
.adm-flash-green { background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; }
.adm-flash-red { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }

/* Stats */
.adm-stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(165px, 1fr)); gap: 1rem; }
.adm-stat-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.25rem; }
.adm-stat-value { font-size: 2rem; font-weight: 700; color: #111827; line-height: 1; margin-bottom: 0.375rem; }
.adm-stat-label { font-size: 0.8125rem; color: #6b7280; }

/* Plans */
.adm-plans-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
.adm-plan-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; }
.adm-plan-count { font-size: 2.5rem; font-weight: 700; line-height: 1; margin-bottom: 0.25rem; }
.adm-plan-name { font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.25rem; }
.adm-plan-pct { font-size: 0.8125rem; color: #9ca3af; margin-bottom: 0.75rem; }
.adm-plan-bar-wrap { height: 4px; background: #e5e7eb; border-radius: 9999px; overflow: hidden; }
.adm-plan-bar { height: 100%; border-radius: 9999px; transition: width 0.4s ease; }

/* Table */
.adm-table-wrap { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.75rem; overflow: hidden; }
.adm-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.adm-table th { padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.03em; background: #f3f4f6; border-bottom: 1px solid #e5e7eb; }
.adm-table td { padding: 0.875rem 1rem; border-bottom: 1px solid #f3f4f6; color: #374151; vertical-align: middle; }
.adm-table tr:last-child td { border-bottom: none; }
.adm-table tr:hover td { background: #fafafa; }
.adm-row-admin td { background: #fffbeb; }
.adm-row-admin:hover td { background: #fef9c3; }
.adm-user-id { color: #9ca3af; font-size: 0.75rem; font-variant-numeric: tabular-nums; white-space: nowrap; }
.adm-user-name { font-weight: 500; color: #111827; }
.adm-user-email { color: #6b7280; font-size: 0.8125rem; }
.adm-ai-usage { font-size: 0.8125rem; color: #6b7280; font-family: monospace; }
.adm-date { font-size: 0.8125rem; color: #9ca3af; white-space: nowrap; }
.adm-badge-admin { display: inline-block; margin-left: 0.5rem; font-size: 0.625rem; font-weight: 700; background: #fef3c7; color: #92400e; padding: 0.125rem 0.375rem; border-radius: 9999px; text-transform: uppercase; }
.adm-badge-google { font-size: 0.6875rem; font-weight: 600; background: #dbeafe; color: #1d4ed8; padding: 0.125rem 0.5rem; border-radius: 9999px; }
.adm-badge-email { font-size: 0.6875rem; font-weight: 600; background: #f3f4f6; color: #6b7280; padding: 0.125rem 0.5rem; border-radius: 9999px; }
.adm-plan-select { padding: 0.25rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.8125rem; color: #374151; background: #fff; cursor: pointer; }
.adm-plan-select:disabled { opacity: 0.5; cursor: not-allowed; }
.adm-delete-btn { padding: 0.375rem; color: #9ca3af; background: transparent; border: none; border-radius: 0.375rem; cursor: pointer; transition: all 0.15s; }
.adm-delete-btn:hover { background: #fef2f2; color: #dc2626; }
.adm-delete-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.adm-icon-btn { padding: 0.375rem; color: #9ca3af; background: transparent; border: none; border-radius: 0.375rem; cursor: pointer; transition: all 0.15s; }
.adm-icon-btn:hover { background: #eff6ff; color: #2563eb; }
.adm-link { color: #6b7280; font-size: 0.8125rem; text-decoration: none; }
.adm-link:hover { color: #374151; text-decoration: underline; }
.adm-index-btn { padding: 0.3rem 0.75rem; font-size: 0.8125rem; font-weight: 500; color: #374151; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 0.375rem; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
.adm-index-btn:hover:not(:disabled) { background: #e5e7eb; }
.adm-index-btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* Pagination */
.adm-pagination { display: flex; gap: 0.375rem; justify-content: center; margin-top: 1.25rem; }
.adm-page-btn { padding: 0.375rem 0.75rem; font-size: 0.875rem; color: #374151; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; text-decoration: none; transition: all 0.15s; }
.adm-page-btn:hover { background: #f3f4f6; }
.adm-page-btn-active { background: #111827; color: #fff; border-color: #111827; }
.adm-page-btn-disabled { opacity: 0.4; pointer-events: none; }

/* Forms */
.adm-form-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; }
.adm-form-heading { font-size: 0.9375rem; font-weight: 600; color: #111827; margin: 0 0 1.25rem; }
.adm-field { margin-bottom: 1.25rem; }
.adm-label { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem; }
.adm-char-count { font-size: 0.75rem; font-weight: 400; color: #9ca3af; margin-left: auto; }
.adm-input { width: 100%; padding: 0.625rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; color: #111827; background: #fff; transition: border-color 0.15s; box-sizing: border-box; }
.adm-input:focus { outline: none; border-color: #9ca3af; }
.adm-textarea { resize: vertical; font-family: inherit; }
.adm-file-input { padding: 0.4rem 0.75rem; }
.adm-radio-group { display: flex; gap: 1.5rem; }
.adm-radio { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: #374151; cursor: pointer; }
.adm-send-btn { width: 100%; padding: 0.75rem; background: #111827; color: #fff; border: none; border-radius: 0.625rem; font-size: 0.9375rem; font-weight: 500; cursor: pointer; transition: all 0.15s; }
.adm-send-btn:hover:not(:disabled) { background: #1f2937; }
.adm-send-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.adm-hint { font-size: 0.8125rem; color: #9ca3af; margin-top: 0.75rem; }
.adm-empty { font-size: 0.875rem; color: #9ca3af; text-align: center; padding: 2rem; }

/* Knowledge forms */
.adm-knowledge-forms { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }

/* Chat accordions */
.adm-case-accordion { border: 1px solid #e5e7eb; border-radius: 0.75rem; overflow: hidden; margin-bottom: 0.75rem; }
.adm-case-header { display: flex; align-items: center; gap: 0.5rem; width: 100%; padding: 0.875rem 1rem; background: #f9fafb; border: none; cursor: pointer; font-size: 0.875rem; text-align: left; transition: background 0.15s; }
.adm-case-header:hover { background: #f3f4f6; }
.adm-case-title { font-weight: 500; color: #111827; }
.adm-messages { padding: 1rem; display: flex; flex-direction: column; gap: 0.75rem; max-height: 30rem; overflow-y: auto; }
.adm-msg { padding: 0.75rem 1rem; border-radius: 0.625rem; }
.adm-msg-user { background: #f3f4f6; }
.adm-msg-assistant { background: #eff6ff; }
.adm-msg-role { font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; display: block; margin-bottom: 0.375rem; }
.adm-msg-content { font-size: 0.875rem; color: #374151; white-space: pre-wrap; margin: 0; }
.adm-msg-meta { font-size: 0.6875rem; color: #9ca3af; display: block; margin-top: 0.375rem; }

/* Settings */
.adm-settings-section { margin-bottom: 2rem; }
.adm-settings-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 0 1.25rem; }
.adm-set-badge { font-size: 0.625rem; font-weight: 700; background: #dcfce7; color: #166534; padding: 0.125rem 0.4rem; border-radius: 9999px; }

/* API group status (in settings headings) */
.adm-api-group-status { display: inline-flex; align-items: center; gap: 0.375rem; margin-left: 0.75rem; font-size: 0.75rem; font-weight: 400; color: #6b7280; vertical-align: middle; }
.adm-api-dot { width: 0.5rem; height: 0.5rem; border-radius: 9999px; flex-shrink: 0; }
.adm-api-dot-ok  { background: #22c55e; box-shadow: 0 0 0 2px #dcfce7; }
.adm-api-dot-off { background: #d1d5db; }
.adm-api-indicator { margin-left: auto; font-size: 0.7rem; font-weight: 500; }
.adm-api-indicator-ok  { color: #16a34a; }
.adm-api-indicator-off { color: #9ca3af; }
.adm-api-preview { margin-top: 0.375rem; font-size: 0.75rem; font-family: monospace; color: #6b7280; background: #f3f4f6; border-radius: 0.375rem; padding: 0.25rem 0.5rem; word-break: break-all; }

/* Subscription plan management */
.adm-sp-header { display: flex; align-items: center; justify-content: space-between; margin: 2rem 0 1rem; }
.adm-btn-primary { display: inline-flex; align-items: center; gap: 0.375rem; background: #7E75CE; color: #fff; border: none; border-radius: 0.625rem; padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: background 0.15s; }
.adm-btn-primary:hover { background: #6d64be; }
.adm-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.adm-btn-ghost { background: none; border: 1.5px solid #e5e7eb; color: #6b7280; border-radius: 0.625rem; padding: 0.5rem 1rem; font-size: 0.875rem; cursor: pointer; transition: background 0.15s; }
.adm-btn-ghost:hover { background: #f3f4f6; }
.adm-sp-list { display: flex; flex-direction: column; gap: 0.75rem; }
.adm-sp-item { border: 1.5px solid #e5e7eb; border-radius: 0.875rem; overflow: hidden; }
.adm-sp-row { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.25rem; }
.adm-sp-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
.adm-sp-info { flex: 1; min-width: 0; }
.adm-sp-name { font-size: 0.9375rem; font-weight: 600; color: #111827; display: flex; align-items: center; gap: 0.5rem; }
.adm-sp-meta { font-size: 0.8125rem; color: #9ca3af; margin-top: 0.125rem; }
.adm-sp-stripe { font-size: 0.75rem; font-family: monospace; color: #6b7280; flex-shrink: 0; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.adm-sp-stripe-missing { color: #ef4444; }
.adm-sp-btns { display: flex; gap: 0.5rem; }
.adm-badge-popular { font-size: 0.65rem; font-weight: 700; background: #ede9fe; color: #7c3aed; padding: 0.15rem 0.5rem; border-radius: 9999px; }
.adm-badge-inactive { font-size: 0.65rem; font-weight: 700; background: #f3f4f6; color: #9ca3af; padding: 0.15rem 0.5rem; border-radius: 9999px; }
.adm-dot-ok { color: #22c55e; }
.adm-dot-off { color: #d1d5db; }
.adm-sp-form { padding: 1.25rem; background: #fafafa; border-top: 1px solid #f3f4f6; }
.adm-sp-form-new { background: #f0f9ff; border: 1.5px solid #bae6fd; border-radius: 0.875rem; margin-bottom: 1rem; }
.adm-sp-form-title { font-size: 0.9375rem; font-weight: 600; color: #111827; margin: 0 0 1rem; }
.adm-sp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.75rem; }
.adm-sp-field { display: flex; flex-direction: column; gap: 0.3rem; }
.adm-sp-field label { font-size: 0.8125rem; font-weight: 500; color: #374151; }
.adm-sp-hint { font-weight: 400; color: #9ca3af; font-size: 0.75rem; }
.adm-input { padding: 0.5rem 0.625rem; border: 1.5px solid #e5e7eb; border-radius: 0.5rem; font-size: 0.875rem; color: #111827; background: #fff; width: 100%; box-sizing: border-box; transition: border-color 0.15s; }
.adm-input:focus { outline: none; border-color: #7E75CE; }
.adm-input-mono { font-family: monospace; }
.adm-textarea { resize: vertical; min-height: 80px; font-family: inherit; }
.adm-sp-color-row { display: flex; align-items: center; gap: 0.5rem; }
.adm-color-input { width: 2.25rem; height: 2.25rem; border-radius: 0.375rem; border: 1.5px solid #e5e7eb; cursor: pointer; padding: 0; }
.adm-sp-checkboxes { flex-direction: row; gap: 1rem; align-items: center; padding-top: 1.2rem; }
.adm-sp-checkboxes label { display: flex; align-items: center; gap: 0.35rem; font-size: 0.875rem; color: #374151; cursor: pointer; }
.adm-sp-actions { display: flex; gap: 0.75rem; margin-top: 1rem; }

</style>
