<script setup>
import { ref, computed } from 'vue';
import ChatLayout from '@/Layouts/ChatLayout.vue';
import ChatSidebar from '@/Components/ChatSidebar.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    cases:    Array,
    accounts: Array,
    emails:   Array,
});

const page          = usePage();
const sidebarOpen   = ref(true);
const showForm      = ref(false);
const selectedProvider = ref(null);
const syncingId     = ref(null);
const syncResult    = ref(null);

const providers = {
    gmail:   { name: 'Gmail',   host: 'imap.gmail.com',        port: 993, color: '#EA4335', bg: '#fef2f2' },
    outlook: { name: 'Outlook', host: 'outlook.office365.com', port: 993, color: '#0078D4', bg: '#eff6ff' },
    other:   { name: 'Anden',   host: '',                      port: 993, color: '#6b7280', bg: '#f3f4f6' },
};

const form = useForm({
    provider:  '',
    email:     '',
    password:  '',
    imap_host: '',
    imap_port: 993,
});

const selectProvider = (key) => {
    selectedProvider.value = key;
    form.provider  = key;
    form.imap_host = providers[key]?.host || '';
    form.imap_port = providers[key]?.port || 993;
    showForm.value = true;
};

const cancelForm = () => {
    showForm.value      = false;
    selectedProvider.value = null;
    form.reset();
    form.clearErrors();
};

const submitConnect = () => {
    form.post(route('inbox.connect'), {
        onSuccess: () => {
            showForm.value = false;
            selectedProvider.value = null;
            form.reset();
        },
    });
};

const disconnect = (accountId) => {
    if (!confirm('Er du sikker på at du vil fjerne denne mailkonto?')) return;
    router.delete(route('inbox.disconnect', { account: accountId }));
};

const toggleAutoSync = (accountId) => {
    router.patch(route('inbox.auto-sync', { account: accountId }), {}, {
        preserveScroll: true,
    });
};

const getCsrfToken = () => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

const sync = async (accountId) => {
    syncingId.value  = accountId;
    syncResult.value = null;
    try {
        const res = await fetch(route('inbox.sync', { account: accountId }), {
            method:  'POST',
            headers: {
                'X-XSRF-TOKEN': getCsrfToken(),
                'Accept':       'application/json',
            },
        });
        const data = await res.json();
        if (data.error) {
            syncResult.value = { error: data.error };
        } else {
            syncResult.value = data;
            // Reload to show new emails/tasks
            setTimeout(() => router.reload({ only: ['accounts', 'emails'] }), 1500);
        }
    } catch {
        syncResult.value = { error: 'Synkronisering fejlede. Prøv igen.' };
    } finally {
        syncingId.value = null;
    }
};

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('da-DK', { day: 'numeric', month: 'short', year: 'numeric' });
};

const formatSyncTime = (dt) => {
    if (!dt) return 'Aldrig synkroniseret';
    const d = new Date(dt);
    const now = new Date();
    const diffMin = Math.floor((now - d) / 60000);
    if (diffMin < 1)  return 'Lige nu';
    if (diffMin < 60) return `${diffMin} min. siden`;
    const diffH = Math.floor(diffMin / 60);
    if (diffH < 24)   return `${diffH} time${diffH > 1 ? 'r' : ''} siden`;
    return formatDate(dt);
};

const providerLabel = (key) => providers[key]?.name || key;
const providerColor = (key) => providers[key]?.color || '#6b7280';
const providerBg    = (key) => providers[key]?.bg    || '#f3f4f6';

const priorityLabel = { low: 'Lav', medium: 'Normal', high: 'Høj', critical: 'Kritisk' };
const priorityColor = { low: '#6b7280', medium: '#2563eb', high: '#d97706', critical: '#dc2626' };
const priorityBg    = { low: '#f3f4f6', medium: '#dbeafe', high: '#fef3c7', critical: '#fee2e2' };

const flash = computed(() => page.props.flash || {});
</script>

<template>
    <Head title="Indbakke - Aura" />

    <ChatLayout>
        <div class="chat-container">
            <ChatSidebar
                :active-case="null"
                :cases="cases"
                :open="sidebarOpen"
                @toggle="sidebarOpen = !sidebarOpen"
            />

            <div class="chat-main">
                <!-- Topbar -->
                <div class="chat-topbar">
                    <h2 class="page-topbar-title">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="topbar-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z" />
                        </svg>
                        Indbakke
                    </h2>
                    <NotificationBell style="margin-left: auto;" />
                </div>

                <div class="page-scroll">
                    <div class="page-content">

                        <!-- Flash messages -->
                        <div v-if="flash.success" class="inbox-flash inbox-flash-success">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            {{ flash.success }}
                        </div>

                        <!-- Sync result -->
                        <div v-if="syncResult" :class="['inbox-flash', syncResult.error ? 'inbox-flash-error' : 'inbox-flash-success']">
                            <template v-if="syncResult.error">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                                {{ syncResult.error }}
                            </template>
                            <template v-else>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                Synkroniseret — {{ syncResult.new }} nye mails analyseret, {{ syncResult.tasks }} opgave{{ syncResult.tasks !== 1 ? 'r' : '' }} oprettet.
                            </template>
                        </div>

                        <!-- ── CONNECTED ACCOUNTS ─────────────────────────── -->
                        <div v-if="accounts.length > 0" class="inbox-section">
                            <div class="inbox-section-header">
                                <h3 class="inbox-section-title">Tilsluttede konti</h3>
                                <button class="inbox-add-btn" @click="showForm = !showForm">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                    Tilføj konto
                                </button>
                            </div>

                            <div class="inbox-account-list">
                                <div v-for="account in accounts" :key="account.id" class="inbox-account-card">
                                    <div class="inbox-account-icon" :style="{ backgroundColor: providerBg(account.provider), color: providerColor(account.provider) }">
                                        <!-- Gmail -->
                                        <svg v-if="account.provider === 'gmail'" viewBox="0 0 24 24" fill="currentColor" style="width:1.1rem;height:1.1rem"><path d="M24 5.457v13.909c0 .904-.732 1.636-1.636 1.636h-3.819V11.73L12 16.64l-6.545-4.91v9.273H1.636A1.636 1.636 0 0 1 0 19.366V5.457c0-2.023 2.309-3.178 3.927-1.964L5.455 4.64 12 9.548l6.545-4.910 1.528-1.145C21.69 2.28 24 3.434 24 5.457z"/></svg>
                                        <!-- Outlook -->
                                        <svg v-else-if="account.provider === 'outlook'" viewBox="0 0 24 24" fill="currentColor" style="width:1.1rem;height:1.1rem"><path d="M7.462 0H0v14.31L7.462 12V0zm9.23 4.923H7.693v7.384l8.999 2.77V4.923zM24 3.692l-6.308 1.846v13.539L24 21.23V3.692zM7.462 13.077L0 15.385V24h7.462v-10.923z"/></svg>
                                        <!-- Other -->
                                        <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="width:1.1rem;height:1.1rem"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                                    </div>
                                    <div class="inbox-account-info">
                                        <div class="inbox-account-email">{{ account.email }}</div>
                                        <div class="inbox-account-meta">
                                            {{ providerLabel(account.provider) }} · {{ formatSyncTime(account.last_synced_at) }}
                                            <span v-if="account.tasks_created > 0"> · {{ account.tasks_created }} opgave{{ account.tasks_created !== 1 ? 'r' : '' }} oprettet</span>
                                        </div>
                                    </div>
                                    <div class="inbox-account-actions">
                                        <!-- Auto-sync toggle -->
                                        <button
                                            class="inbox-autosync-toggle"
                                            :class="{ 'inbox-autosync-on': account.auto_sync }"
                                            @click="toggleAutoSync(account.id)"
                                            :title="account.auto_sync ? 'Auto-sync til — klik for at slå fra' : 'Auto-sync fra — klik for at slå til'"
                                        >
                                            <span class="inbox-autosync-dot"></span>
                                            <span>Auto</span>
                                        </button>

                                        <button
                                            class="inbox-sync-btn"
                                            :disabled="syncingId === account.id"
                                            @click="sync(account.id)"
                                            title="Synkroniser nu"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" :class="{ 'inbox-spin': syncingId === account.id }">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                            </svg>
                                            {{ syncingId === account.id ? 'Synkroniserer…' : 'Sync' }}
                                        </button>
                                        <button class="inbox-remove-btn" @click="disconnect(account.id)" title="Fjern konto">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ── ADD ACCOUNT FORM ────────────────────────────── -->
                        <div v-if="accounts.length === 0 || showForm" class="inbox-section">
                            <h3 v-if="accounts.length > 0" class="inbox-section-title">Tilslut ny konto</h3>

                            <!-- Hero (only when no accounts) -->
                            <div v-if="accounts.length === 0" class="inbox-hero">
                                <div class="inbox-hero-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z" />
                                    </svg>
                                </div>
                                <h3 class="inbox-hero-title">Tilslut din indbakke</h3>
                                <p class="inbox-hero-desc">Giv Aura adgang til dine mails, så hun automatisk kan finde vigtige beskeder, frister og opgaver relateret til din situation.</p>
                            </div>

                            <!-- Step 1: Pick provider -->
                            <div v-if="!showForm || accounts.length === 0" class="inbox-providers">
                                <p class="inbox-providers-title">Vælg mailudbyder</p>
                                <div class="inbox-provider-list">
                                    <button
                                        v-for="(p, key) in providers"
                                        :key="key"
                                        class="inbox-provider-card"
                                        :class="{ 'inbox-provider-selected': selectedProvider === key }"
                                        @click="selectProvider(key)"
                                    >
                                        <div class="inbox-provider-icon" :style="{ backgroundColor: p.bg, color: p.color }">
                                            <svg v-if="key === 'gmail'" viewBox="0 0 24 24" fill="currentColor" style="width:1.1rem;height:1.1rem"><path d="M24 5.457v13.909c0 .904-.732 1.636-1.636 1.636h-3.819V11.73L12 16.64l-6.545-4.91v9.273H1.636A1.636 1.636 0 0 1 0 19.366V5.457c0-2.023 2.309-3.178 3.927-1.964L5.455 4.64 12 9.548l6.545-4.910 1.528-1.145C21.69 2.28 24 3.434 24 5.457z"/></svg>
                                            <svg v-else-if="key === 'outlook'" viewBox="0 0 24 24" fill="currentColor" style="width:1.1rem;height:1.1rem"><path d="M7.462 0H0v14.31L7.462 12V0zm9.23 4.923H7.693v7.384l8.999 2.77V4.923zM24 3.692l-6.308 1.846v13.539L24 21.23V3.692zM7.462 13.077L0 15.385V24h7.462v-10.923z"/></svg>
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="width:1.1rem;height:1.1rem"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                                        </div>
                                        <div class="inbox-provider-info">
                                            <div class="inbox-provider-name">{{ p.name }}</div>
                                        </div>
                                        <svg v-if="selectedProvider === key" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:1rem;height:1rem;color:#2563eb;flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Step 2: Credentials form -->
                            <form v-if="showForm" @submit.prevent="submitConnect" class="inbox-connect-form">
                                <!-- App password hint -->
                                <div v-if="selectedProvider === 'gmail'" class="inbox-hint">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                                    <span>Gmail kræver et <strong>App-adgangskode</strong> — opret det via <em>Google-konto → Sikkerhed → 2-trins-godkendelse → App-adgangskoder</em>.</span>
                                </div>
                                <div v-else-if="selectedProvider === 'outlook'" class="inbox-hint">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                                    <span>Outlook kræver muligvis et <strong>App-adgangskode</strong> hvis 2-trins-godkendelse er aktiveret på din konto.</span>
                                </div>

                                <div class="inbox-form-group">
                                    <label class="inbox-label">Mailadresse</label>
                                    <input
                                        v-model="form.email"
                                        type="email"
                                        placeholder="din@email.dk"
                                        class="inbox-input"
                                        :class="{ 'inbox-input-error': form.errors.email }"
                                        autocomplete="email"
                                        required
                                    />
                                    <p v-if="form.errors.email" class="inbox-error-msg">{{ form.errors.email }}</p>
                                </div>

                                <div class="inbox-form-group">
                                    <label class="inbox-label">Adgangskode / App-adgangskode</label>
                                    <input
                                        v-model="form.password"
                                        type="password"
                                        placeholder="••••••••••••••••"
                                        class="inbox-input"
                                        :class="{ 'inbox-input-error': form.errors.password }"
                                        autocomplete="current-password"
                                        required
                                    />
                                    <p v-if="form.errors.password" class="inbox-error-msg">{{ form.errors.password }}</p>
                                </div>

                                <!-- Custom IMAP server for 'other' -->
                                <template v-if="selectedProvider === 'other'">
                                    <div class="inbox-form-row">
                                        <div class="inbox-form-group" style="flex:1">
                                            <label class="inbox-label">IMAP-server</label>
                                            <input v-model="form.imap_host" type="text" placeholder="imap.example.com" class="inbox-input" :class="{ 'inbox-input-error': form.errors.imap_host }" required />
                                            <p v-if="form.errors.imap_host" class="inbox-error-msg">{{ form.errors.imap_host }}</p>
                                        </div>
                                        <div class="inbox-form-group" style="width:90px">
                                            <label class="inbox-label">Port</label>
                                            <input v-model="form.imap_port" type="number" placeholder="993" class="inbox-input" />
                                        </div>
                                    </div>
                                </template>

                                <p v-if="form.errors.provider" class="inbox-error-msg">{{ form.errors.provider }}</p>

                                <div class="inbox-form-actions">
                                    <button type="button" class="inbox-cancel-btn" @click="cancelForm">Annuller</button>
                                    <button type="submit" class="inbox-submit-btn" :disabled="form.processing">
                                        {{ form.processing ? 'Forbinder…' : 'Tilslut konto' }}
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- ── FOUND EMAILS ──────────────────────────────────── -->
                        <div v-if="accounts.length > 0" class="inbox-section">
                            <h3 class="inbox-section-title">
                                Relevante mails
                                <span class="inbox-count-badge">{{ emails.length }}</span>
                            </h3>

                            <div v-if="emails.length === 0" class="inbox-empty">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <p>Ingen relevante mails fundet endnu</p>
                                <span>Tryk "Sync" på din konto for at analysere dine mails</span>
                            </div>

                            <div v-else class="inbox-email-list">
                                <div v-for="email in emails" :key="email.id" class="inbox-email-card">
                                    <div class="inbox-email-header">
                                        <div class="inbox-email-subject">{{ email.subject }}</div>
                                        <div class="inbox-email-date">{{ formatDate(email.received_at) }}</div>
                                    </div>
                                    <div class="inbox-email-from">Fra: {{ email.from_name || email.from_email }}</div>
                                    <div v-if="email.snippet" class="inbox-email-snippet">{{ email.snippet.substring(0, 150) }}{{ email.snippet.length > 150 ? '…' : '' }}</div>

                                    <!-- Found tasks -->
                                    <div v-if="email.analysis_result?.tasks?.length" class="inbox-email-tasks">
                                        <div class="inbox-email-tasks-label">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                            {{ email.tasks_created }} opgave{{ email.tasks_created !== 1 ? 'r' : '' }} oprettet
                                        </div>
                                        <div v-for="(task, i) in email.analysis_result.tasks" :key="i" class="inbox-task-chip">
                                            <span class="inbox-task-priority" :style="{ backgroundColor: priorityBg[task.priority], color: priorityColor[task.priority] }">
                                                {{ priorityLabel[task.priority] || task.priority }}
                                            </span>
                                            {{ task.title }}
                                            <span v-if="task.due_date" class="inbox-task-due">· {{ task.due_date }}</span>
                                        </div>
                                    </div>

                                    <div v-if="email.analysis_result?.reason" class="inbox-email-reason">
                                        {{ email.analysis_result.reason }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Privacy note -->
                        <div class="inbox-privacy">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                            <span>Aura læser kun mails relateret til din situation. Vi gemmer aldrig dine mails — kun de opgaver og analyseresultater vi finder.</span>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </ChatLayout>
</template>

<style scoped>
/* Flash */
.inbox-flash {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    border-radius: 0.75rem;
    font-size: 0.875rem;
    margin-bottom: 1rem;
    max-width: 600px;
}
.inbox-flash svg { width: 1.1rem; height: 1.1rem; flex-shrink: 0; }
.inbox-flash-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.inbox-flash-error   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

/* Sections */
.inbox-section { max-width: 600px; margin-bottom: 2rem; }

.inbox-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.75rem;
}

.inbox-section-title {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.inbox-count-badge {
    background: #e5e7eb;
    color: #374151;
    border-radius: 9999px;
    font-size: 0.75rem;
    padding: 0.1rem 0.5rem;
    text-transform: none;
    letter-spacing: 0;
}

.inbox-add-btn {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #2563eb;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    border-radius: 0.5rem;
    transition: background 0.15s;
}
.inbox-add-btn:hover { background: #eff6ff; }
.inbox-add-btn svg { width: 0.9rem; height: 0.9rem; }

/* Hero */
.inbox-hero { text-align: center; padding: 1.5rem 0 1rem; }
.inbox-hero-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 3rem; height: 3rem; background: #eff6ff; color: #2563eb;
    border-radius: 0.875rem; margin-bottom: 0.875rem;
}
.inbox-hero-icon svg { width: 1.5rem; height: 1.5rem; }
.inbox-hero-title { font-size: 1.1rem; font-weight: 700; color: #111827; margin-bottom: 0.4rem; }
.inbox-hero-desc  { font-size: 0.875rem; color: #6b7280; line-height: 1.6; max-width: 440px; margin: 0 auto; }

/* Provider list */
.inbox-providers { margin-top: 1.25rem; }
.inbox-providers-title { font-size: 0.8125rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; }
.inbox-provider-list { display: flex; flex-direction: column; gap: 0.5rem; }

.inbox-provider-card {
    display: flex; align-items: center; gap: 0.75rem;
    background: #fff; border: 1.5px solid #e5e7eb; border-radius: 0.75rem;
    padding: 0.75rem 1rem; cursor: pointer; transition: border-color 0.15s, box-shadow 0.15s;
    text-align: left; width: 100%;
}
.inbox-provider-card:hover { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.07); }
.inbox-provider-selected   { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); }

.inbox-provider-icon {
    width: 2.25rem; height: 2.25rem; border-radius: 0.5rem;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.inbox-provider-name { font-size: 0.875rem; font-weight: 600; color: #111827; }

/* Account cards */
.inbox-account-list { display: flex; flex-direction: column; gap: 0.5rem; }
.inbox-account-card {
    display: flex; align-items: center; gap: 0.75rem;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 0.875rem;
    padding: 0.875rem 1rem;
}
.inbox-account-icon { width: 2.25rem; height: 2.25rem; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.inbox-account-info { flex: 1; min-width: 0; }
.inbox-account-email { font-size: 0.875rem; font-weight: 600; color: #111827; truncate: true; }
.inbox-account-meta  { font-size: 0.8rem; color: #9ca3af; margin-top: 0.1rem; }
.inbox-account-actions { display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; }

.inbox-sync-btn {
    display: flex; align-items: center; gap: 0.3rem;
    font-size: 0.8rem; font-weight: 600; color: #2563eb;
    background: #eff6ff; border: none; border-radius: 0.5rem;
    padding: 0.35rem 0.7rem; cursor: pointer; transition: background 0.15s;
    white-space: nowrap;
}
.inbox-sync-btn:hover:not(:disabled) { background: #dbeafe; }
.inbox-sync-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.inbox-sync-btn svg { width: 0.875rem; height: 0.875rem; }

.inbox-remove-btn {
    width: 1.75rem; height: 1.75rem; display: flex; align-items: center; justify-content: center;
    background: none; border: none; border-radius: 0.5rem; cursor: pointer;
    color: #d1d5db; transition: color 0.15s, background 0.15s;
}
.inbox-remove-btn:hover { color: #ef4444; background: #fef2f2; }
.inbox-remove-btn svg { width: 0.9rem; height: 0.9rem; }

/* Auto-sync toggle */
.inbox-autosync-toggle {
    display: flex; align-items: center; gap: 0.3rem;
    font-size: 0.75rem; font-weight: 600;
    padding: 0.3rem 0.6rem; border-radius: 9999px; border: 1.5px solid #e5e7eb;
    cursor: pointer; transition: all 0.2s; white-space: nowrap;
    background: #f9fafb; color: #9ca3af;
}
.inbox-autosync-toggle:hover { border-color: #d1d5db; }
.inbox-autosync-toggle.inbox-autosync-on {
    background: #f0fdf4; color: #16a34a; border-color: #86efac;
}
.inbox-autosync-dot {
    width: 6px; height: 6px; border-radius: 9999px;
    background: #d1d5db; flex-shrink: 0; transition: background 0.2s;
}
.inbox-autosync-on .inbox-autosync-dot { background: #16a34a; }

@keyframes spin { to { transform: rotate(360deg); } }
.inbox-spin { animation: spin 0.8s linear infinite; }

/* Connect form */
.inbox-connect-form { margin-top: 1rem; background: #f9fafb; border: 1px solid #f3f4f6; border-radius: 1rem; padding: 1.25rem; }

.inbox-hint {
    display: flex; align-items: flex-start; gap: 0.5rem;
    background: #fffbeb; border: 1px solid #fde68a; border-radius: 0.625rem;
    padding: 0.75rem; font-size: 0.8125rem; color: #92400e;
    margin-bottom: 1rem; line-height: 1.5;
}
.inbox-hint svg { width: 1rem; height: 1rem; flex-shrink: 0; margin-top: 0.1rem; }

.inbox-form-group { margin-bottom: 0.875rem; }
.inbox-form-row { display: flex; gap: 0.75rem; }
.inbox-label { display: block; font-size: 0.8125rem; font-weight: 500; color: #374151; margin-bottom: 0.3rem; }
.inbox-input {
    width: 100%; border: 1.5px solid #e5e7eb; border-radius: 0.625rem;
    padding: 0.6rem 0.75rem; font-size: 0.875rem; outline: none;
    transition: border-color 0.15s; background: #fff; box-sizing: border-box;
}
.inbox-input:focus { border-color: #2563eb; }
.inbox-input-error { border-color: #f87171; }
.inbox-error-msg { font-size: 0.8rem; color: #ef4444; margin-top: 0.25rem; }

.inbox-form-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem; }
.inbox-cancel-btn {
    font-size: 0.875rem; font-weight: 500; color: #6b7280;
    background: #fff; border: 1.5px solid #e5e7eb; border-radius: 0.625rem;
    padding: 0.55rem 1rem; cursor: pointer; transition: background 0.15s;
}
.inbox-cancel-btn:hover { background: #f9fafb; }
.inbox-submit-btn {
    font-size: 0.875rem; font-weight: 600; color: #fff;
    background: #2563eb; border: none; border-radius: 0.625rem;
    padding: 0.55rem 1.25rem; cursor: pointer; transition: background 0.15s;
}
.inbox-submit-btn:hover:not(:disabled) { background: #1d4ed8; }
.inbox-submit-btn:disabled { opacity: 0.6; cursor: not-allowed; }

/* Email list */
.inbox-email-list { display: flex; flex-direction: column; gap: 0.75rem; }
.inbox-email-card {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 0.875rem; padding: 1rem;
}
.inbox-email-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.25rem; }
.inbox-email-subject { font-size: 0.9rem; font-weight: 600; color: #111827; flex: 1; }
.inbox-email-date    { font-size: 0.8rem; color: #9ca3af; flex-shrink: 0; }
.inbox-email-from    { font-size: 0.8125rem; color: #6b7280; margin-bottom: 0.5rem; }
.inbox-email-snippet { font-size: 0.8125rem; color: #9ca3af; line-height: 1.5; margin-bottom: 0.75rem; }
.inbox-email-reason  { font-size: 0.8rem; color: #6b7280; font-style: italic; margin-top: 0.5rem; }

.inbox-email-tasks { margin-top: 0.5rem; }
.inbox-email-tasks-label {
    display: flex; align-items: center; gap: 0.35rem;
    font-size: 0.8rem; font-weight: 600; color: #059669;
    margin-bottom: 0.4rem;
}
.inbox-email-tasks-label svg { width: 0.9rem; height: 0.9rem; }
.inbox-task-chip { display: flex; align-items: center; gap: 0.4rem; font-size: 0.8125rem; color: #374151; margin-bottom: 0.3rem; }
.inbox-task-priority { font-size: 0.7rem; font-weight: 600; border-radius: 9999px; padding: 0.1rem 0.5rem; white-space: nowrap; }
.inbox-task-due { color: #9ca3af; }

/* Empty state */
.inbox-empty {
    text-align: center; padding: 2rem 1rem;
    background: #f9fafb; border-radius: 0.875rem;
    border: 1px dashed #e5e7eb;
}
.inbox-empty svg { width: 2rem; height: 2rem; color: #d1d5db; margin: 0 auto 0.5rem; }
.inbox-empty p { font-size: 0.9rem; font-weight: 600; color: #374151; margin-bottom: 0.25rem; }
.inbox-empty span { font-size: 0.8125rem; color: #9ca3af; }

/* Privacy */
.inbox-privacy {
    display: flex; align-items: flex-start; gap: 0.5rem;
    max-width: 600px; font-size: 0.8rem; color: #9ca3af; line-height: 1.5;
    padding-bottom: 2rem;
}
.inbox-privacy svg { width: 1rem; height: 1rem; flex-shrink: 0; color: #10b981; margin-top: 0.1rem; }
</style>
