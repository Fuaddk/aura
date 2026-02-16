<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';

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

const emit = defineEmits(['close']);

const page = usePage();
const userMenuOpen = ref(false);
const openChatMenu = ref(null);
const openGroupMenu = ref(null);
const deletingCase = ref(null);
const deletingGroup = ref(null);

// Task data from shared props
const pendingTaskCount = computed(() => page.props.pendingTaskCount || 0);
const newTaskCount = computed(() => page.props.newTaskCount || 0);

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
    <aside :class="['chat-sidebar', { 'chat-sidebar-closed': !open }]">
        <!-- Close menus overlay -->
        <div v-if="openChatMenu || openGroupMenu" class="fixed inset-0 z-40" @click="closeMenus"></div>

        <!-- Sidebar Header -->
        <div class="flex items-center justify-between p-4">
            <button
                @click="emit('close')"
                class="p-1.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                title="Luk sidebar"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                </svg>
            </button>

            <button
                @click="startNewChat"
                class="p-1.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                title="Ny samtale"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                </svg>
            </button>
        </div>

        <!-- Menu Items -->
        <div class="sidebar-menu">
            <Link :href="route('tasks.index')" class="sidebar-menu-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span class="flex-1">Opgaver</span>
                <span v-if="newTaskCount > 0" class="sb-badge-new">{{ newTaskCount }}</span>
                <span v-else-if="pendingTaskCount > 0" class="sb-badge-count">{{ pendingTaskCount }}</span>
            </Link>
            <Link :href="route('documents.index')" class="sidebar-menu-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                <span class="flex-1">Mine dokumenter</span>
            </Link>
            <Link :href="route('calendar.index')" class="sidebar-menu-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
                <span class="flex-1">Kalender</span>
            </Link>
        </div>

        <!-- Conversations List -->
        <div class="flex-1 overflow-y-auto px-3">
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
                    class="absolute bottom-full left-3 right-3 mb-2 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-50"
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
                class="relative z-50 flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-gray-100 cursor-pointer transition-colors"
            >
                <div class="sidebar-user-avatar">
                    {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ $page.props.auth.user.name }}</p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
        </div>
    </aside>
</template>
