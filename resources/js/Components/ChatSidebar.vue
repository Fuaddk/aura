<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';

const SIDEBAR_KEY = 'aura_sidebar_open';

const props = defineProps({
    activeCase: Object,
    cases: {
        type: Array,
        default: () => [],
    },
    open: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close', 'toggle']);

// Persist sidebar state across page navigations
const stored = localStorage.getItem(SIDEBAR_KEY);
const isOpen = ref(stored !== null ? stored !== 'false' : props.open);

const handleToggle = () => {
    isOpen.value = !isOpen.value;
    localStorage.setItem(SIDEBAR_KEY, String(isOpen.value));
    emit('toggle');
};

const page = usePage();
const userMenuOpen = ref(false);

const isMenuActive = (path) => page.url.startsWith(path);
const openChatMenu = ref(null);
const openGroupMenu = ref(null);
const deletingCase = ref(null);
const deletingGroup = ref(null);

// Plan feature access
const plan = computed(() => page.props.auth?.user?.subscription_plan ?? 'free');
const canCalendar = computed(() => ['basis', 'pro', 'business'].includes(plan.value));
const canInbox    = computed(() => ['pro', 'business'].includes(plan.value));

// Task data from shared props
const pendingTaskCount = computed(() => page.props.pendingTaskCount || 0);
const newTaskCount = computed(() => page.props.newTaskCount || 0);
const urgentTaskCount = computed(() => page.props.urgentTaskCount || 0);
const warningTaskCount = computed(() => page.props.warningTaskCount || 0);
const soonTaskCount = computed(() => page.props.soonTaskCount || 0);
const connectedEmailCount = computed(() => page.props.connectedEmailCount || 0);

// Bestem badge-farve baseret på hastegrad
const taskBadgeLevel = computed(() => {
    if (urgentTaskCount.value > 0) return 'urgent';    // rød (≤3 dage)
    if (warningTaskCount.value > 0) return 'warning';  // orange (4-7 dage)
    if (soonTaskCount.value > 0)   return 'soon';      // gul (8-14 dage)
    if (pendingTaskCount.value > 0) return 'ok';       // grøn
    return null;
});
const taskBadgeCount = computed(() => {
    if (taskBadgeLevel.value === 'urgent') return urgentTaskCount.value;
    if (taskBadgeLevel.value === 'warning') return warningTaskCount.value;
    if (taskBadgeLevel.value === 'soon')   return soonTaskCount.value;
    return pendingTaskCount.value;
});

const startNewChat = () => {
    router.visit(route('dashboard', { new: 1 }));
};

const switchCase = (caseId) => {
    router.visit(route('dashboard', { case: caseId }));
};

const closeUserMenu = () => {
    userMenuOpen.value = false;
};

const closeMenus = () => {
    openChatMenu.value = null;
    openGroupMenu.value = null;
};

const toggleChatMenu = (caseId, event) => {
    event.stopPropagation();
    openGroupMenu.value = null;
    openChatMenu.value = openChatMenu.value === caseId ? null : caseId;
};

const toggleGroupMenu = (label, event) => {
    event.stopPropagation();
    openChatMenu.value = null;
    openGroupMenu.value = openGroupMenu.value === label ? null : label;
};

const deleteCase = async (caseId) => {
    deletingCase.value = caseId;
    openChatMenu.value = null;
    try {
        await axios.delete(route('cases.destroy', { case: caseId }));
        // If we deleted the active case, go to empty dashboard
        if (props.activeCase?.id === caseId) {
            router.visit(route('dashboard'));
        } else {
            router.reload({ only: ['cases'] });
        }
    } catch (error) {
        console.error('Error deleting case:', error);
    } finally {
        deletingCase.value = null;
    }
};

const periodMap = {
    'I dag': 'today',
    'I går': 'yesterday',
    'Sidste 7 dage': 'week',
    'Sidste 30 dage': 'month',
    'Ældre': 'older',
};

const deleteGroup = async (label) => {
    const period = periodMap[label];
    if (!period) return;
    deletingGroup.value = label;
    openGroupMenu.value = null;
    try {
        await axios.delete(route('cases.destroy.period', { period }));
        router.reload({ only: ['cases'] });
        // If active case was in this group, go to empty dashboard
        if (props.activeCase) {
            router.visit(route('dashboard'));
        }
    } catch (error) {
        console.error('Error deleting group:', error);
    } finally {
        deletingGroup.value = null;
    }
};

const truncateSummary = (text, maxLength = 30) => {
    if (!text) return 'Ny samtale';
    return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
};

const groupedCases = computed(() => {
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    const weekAgo = new Date(today);
    weekAgo.setDate(weekAgo.getDate() - 7);
    const monthAgo = new Date(today);
    monthAgo.setDate(monthAgo.getDate() - 30);

    const groups = {
        'I dag': [],
        'I går': [],
        'Sidste 7 dage': [],
        'Sidste 30 dage': [],
        'Ældre': [],
    };

    props.cases.forEach((c) => {
        const date = new Date(c.created_at);
        if (date >= today) {
            groups['I dag'].push(c);
        } else if (date >= yesterday) {
            groups['I går'].push(c);
        } else if (date >= weekAgo) {
            groups['Sidste 7 dage'].push(c);
        } else if (date >= monthAgo) {
            groups['Sidste 30 dage'].push(c);
        } else {
            groups['Ældre'].push(c);
        }
    });

    return Object.entries(groups).filter(([, cases]) => cases.length > 0);
});
</script>

<template>
    <aside :class="['chat-sidebar', { 'chat-sidebar-closed': !isOpen }]">
        <!-- Close menus overlay -->
        <div v-if="openChatMenu || openGroupMenu" class="fixed inset-0 z-40" @click="closeMenus"></div>

        <!-- Sidebar Header -->
        <div class="sidebar-header flex items-center justify-between p-4">
            <button
                @click="handleToggle"
                class="p-1.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                :title="isOpen ? 'Luk sidebar' : 'Åbn sidebar'"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                </svg>
            </button>

            <button
                @click="startNewChat"
                class="sidebar-new-btn p-1.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                title="Ny samtale"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                </svg>
            </button>
        </div>

        <!-- Menu Items -->
        <div class="sidebar-menu">
            <Link :href="route('tasks.index')" :class="['sidebar-menu-item', { 'sidebar-menu-item-active': isMenuActive('/tasks') }]">
                <span class="sb-icon-wrap">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <span v-if="urgentTaskCount > 0 || warningTaskCount > 0 || soonTaskCount > 0" class="sb-dot sb-dot-red"></span>
                </span>
                <span class="sidebar-menu-label flex-1">Mine opgaver</span>
                <span v-if="urgentTaskCount > 0" class="sb-badge-urgent">{{ urgentTaskCount }}</span>
                <span v-if="warningTaskCount > 0" class="sb-badge-warning">{{ warningTaskCount }}</span>
                <span v-if="soonTaskCount > 0" class="sb-badge-soon">{{ soonTaskCount }}</span>
            </Link>
            <Link :href="route('documents.index')" :class="['sidebar-menu-item', { 'sidebar-menu-item-active': isMenuActive('/documents') }]">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                <span class="sidebar-menu-label flex-1">Mine dokumenter</span>
            </Link>
            <Link v-if="canCalendar" :href="route('calendar.index')" :class="['sidebar-menu-item', { 'sidebar-menu-item-active': isMenuActive('/calendar') }]">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
                <span class="sidebar-menu-label flex-1">Kalender</span>
            </Link>
            <Link v-else :href="route('subscription.plans')" class="sidebar-menu-item sidebar-menu-item-locked" title="Kræver Basis-plan eller højere">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
                <span class="sidebar-menu-label flex-1">Kalender</span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="sb-lock-icon"><path fill-rule="evenodd" d="M8 1a3.5 3.5 0 0 0-3.5 3.5V7A1.5 1.5 0 0 0 3 8.5v4A1.5 1.5 0 0 0 4.5 14h7a1.5 1.5 0 0 0 1.5-1.5v-4A1.5 1.5 0 0 0 11 7V4.5A3.5 3.5 0 0 0 8 1Zm2 6V4.5a2 2 0 1 0-4 0V7h4Z" clip-rule="evenodd" /></svg>
            </Link>

            <Link v-if="canInbox" :href="route('inbox.index')" :class="['sidebar-menu-item', { 'sidebar-menu-item-active': isMenuActive('/inbox') }]">
                <span class="sb-icon-wrap">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z" />
                    </svg>
                    <span v-if="connectedEmailCount > 0" class="sb-dot sb-dot-green"></span>
                </span>
                <span class="sidebar-menu-label flex-1">Indbakke</span>
                <span v-if="connectedEmailCount > 0" class="sb-badge-connected">
                    <span style="width:5px;height:5px;border-radius:9999px;background:#86efac;flex-shrink:0;display:inline-block;"></span>
                    {{ connectedEmailCount }} tilsluttet
                </span>
            </Link>
            <Link v-else :href="route('subscription.plans')" class="sidebar-menu-item sidebar-menu-item-locked" title="Kræver Pro-plan eller højere">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z" />
                </svg>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="position:absolute;left:0.9rem">
                </svg>
                <span class="sidebar-menu-label flex-1">Indbakke</span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="sb-lock-icon"><path fill-rule="evenodd" d="M8 1a3.5 3.5 0 0 0-3.5 3.5V7A1.5 1.5 0 0 0 3 8.5v4A1.5 1.5 0 0 0 4.5 14h7a1.5 1.5 0 0 0 1.5-1.5v-4A1.5 1.5 0 0 0 11 7V4.5A3.5 3.5 0 0 0 8 1Zm2 6V4.5a2 2 0 1 0-4 0V7h4Z" clip-rule="evenodd" /></svg>
            </Link>
        </div>

        <!-- Conversations List -->
        <div class="sidebar-conversations flex-1 overflow-y-auto px-3">
            <div v-if="cases.length === 0" class="px-2 py-4 text-sm text-gray-400 text-center">
                Ingen samtaler endnu
            </div>

            <div v-for="[label, groupCases] in groupedCases" :key="label" class="mb-4">
                <!-- Group Header with dot menu -->
                <div class="sb-group-header">
                    <p class="sb-group-label">{{ label }}</p>
                    <div class="sb-group-menu-wrap">
                        <button @click="toggleGroupMenu(label, $event)" class="sb-dot-btn" title="Flere muligheder">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                            </svg>
                        </button>
                        <div v-if="openGroupMenu === label" class="sb-dropdown">
                            <button @click="deleteGroup(label)" class="sb-dropdown-item sb-dropdown-danger" :disabled="deletingGroup === label">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                                {{ deletingGroup === label ? 'Sletter...' : `Slet alle i "${label}"` }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="space-y-0.5">
                    <!-- Chat item with dot menu -->
                    <div v-for="c in groupCases" :key="c.id" class="sb-chat-item" :class="{ 'sb-chat-item-active': activeCase?.id === c.id }">
                        <button @click="switchCase(c.id)" class="sb-chat-btn">
                            {{ c.title || truncateSummary(c.situation_summary) }}
                        </button>
                        <div class="sb-chat-menu-wrap">
                            <button @click="toggleChatMenu(c.id, $event)" class="sb-dot-btn sb-dot-btn-chat" title="Flere muligheder">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                                </svg>
                            </button>
                            <div v-if="openChatMenu === c.id" class="sb-dropdown">
                                <button @click="deleteCase(c.id)" class="sb-dropdown-item sb-dropdown-danger" :disabled="deletingCase === c.id">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                    {{ deletingCase === c.id ? 'Sletter...' : 'Slet samtale' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Footer - User -->
        <div class="relative border-t border-gray-200 p-3">
            <transition
                enter-active-class="transition ease-out duration-150"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-2"
            >
                <div
                    v-if="userMenuOpen"
                    class="sidebar-user-popup absolute bottom-full left-3 right-3 mb-2 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-50"
                >
                    <Link
                        :href="route('profile.edit')"
                        class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        Profil
                    </Link>
                    <Link
                        v-if="$page.props.auth.user.is_admin"
                        :href="route('admin.dashboard')"
                        class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        Admin panel
                    </Link>
                    <Link
                        v-if="$page.props.auth.user.subscription_plan === 'free' || !$page.props.auth.user.subscription_plan"
                        :href="route('subscription.plans')"
                        class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-purple-600 hover:bg-purple-50 transition-colors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                        </svg>
                        Opgrader til Basis
                    </Link>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="flex items-center gap-3 w-full px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                        </svg>
                        Log ud
                    </Link>
                </div>
            </transition>

            <div
                v-if="userMenuOpen"
                class="fixed inset-0 z-40"
                @click="closeUserMenu"
            ></div>

            <div
                @click="userMenuOpen = !userMenuOpen"
                class="sidebar-footer-row relative z-50 flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-gray-100 cursor-pointer transition-colors"
            >
                <div class="sidebar-user-avatar">
                    {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                </div>
                <div class="sidebar-footer-text flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ $page.props.auth.user.name }}</p>
                    <p class="text-xs text-gray-400 truncate">{{ $page.props.auth.user.email }}</p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" class="sidebar-footer-chevron w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
        </div>
    </aside>
</template>
