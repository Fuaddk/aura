<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import axios from 'axios';

const $page = usePage();

const notifOpen     = ref(false);
const notifRef      = ref(null);
const notifications = ref([...$page.props.notifications ?? []]);
const unreadCount   = ref($page.props.unreadNotificationCount ?? 0);
const selectedNotif = ref(null);
const bellShaking   = ref(false);

watch(() => $page.props.unreadNotificationCount, (val) => { unreadCount.value = val ?? 0; });
watch(() => $page.props.notifications, (val) => { if (val) notifications.value = [...val]; });

const notifIcons = {
    new_tasks:             '✅',
    subscription_upgraded: '⭐',
    subscription_renewed:  '🔄',
    deadline_soon:         '⏰',
    admin:                 '📢',
};

const formatNotifTime = (dateStr) => {
    const diff = Math.floor((Date.now() - new Date(dateStr)) / 60000);
    if (diff < 1)    return 'Lige nu';
    if (diff < 60)   return `${diff} min. siden`;
    if (diff < 1440) return `${Math.floor(diff / 60)} t. siden`;
    return new Date(dateStr).toLocaleDateString('da-DK', { day: 'numeric', month: 'short' });
};

const markAllRead = async () => {
    await axios.post(route('notifications.read-all'));
    notifications.value.forEach(n => (n.is_read = true));
    unreadCount.value = 0;
};

const clearAll = async () => {
    await axios.delete(route('notifications.clear-all'));
    notifications.value = [];
    unreadCount.value = 0;
};

const openDetail = async (n) => {
    if (!n.is_read) {
        await axios.post(route('notifications.read', { notification: n.id }));
        n.is_read = true;
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    }
    selectedNotif.value = n;
    notifOpen.value = false;
};

const closeDetail = () => { selectedNotif.value = null; };

const goToAction = () => {
    const url = selectedNotif.value?.action_url;
    closeDetail();
    if (url) router.visit(url);
};

const outsideClick = (e) => {
    if (notifRef.value && !notifRef.value.contains(e.target)) notifOpen.value = false;
};

const onKeydown = (e) => {
    if (e.key === 'Escape') {
        if (selectedNotif.value) closeDetail();
        else notifOpen.value = false;
    }
};

onMounted(() => {
    const userId = $page.props.auth.user?.id;
    if (userId && window.Echo) {
        window.Echo.private(`notifications.${userId}`)
            .listen('.NotificationCreated', (data) => {
                notifications.value.unshift({ ...data, is_read: false });
                unreadCount.value++;
                bellShaking.value = false;
                requestAnimationFrame(() => { bellShaking.value = true; });
                setTimeout(() => { bellShaking.value = false; }, 700);
            });
    }
    document.addEventListener('click', outsideClick);
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    const userId = $page.props.auth.user?.id;
    if (userId && window.Echo) window.Echo.leave(`notifications.${userId}`);
    document.removeEventListener('click', outsideClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div class="chat-notif-wrapper" ref="notifRef">

        <!-- ── Bell button ── -->
        <button
            @click.stop="notifOpen = !notifOpen"
            :class="['chat-notif-btn', { 'notif-bell-shake': bellShaking, 'notif-btn-active': notifOpen }]"
            title="Notifikationer"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
            </svg>
            <span v-if="unreadCount > 0" class="chat-notif-badge">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
        </button>

        <!-- ── Dropdown panel ── -->
        <Transition name="notif-fade">
        <div v-if="notifOpen" class="chat-notif-panel" @click.stop>

            <!-- Header -->
            <div class="chat-notif-header">
                <div class="chat-notif-header-left">
                    <span class="chat-notif-header-title">Notifikationer</span>
                    <span v-if="notifications.length" class="chat-notif-count">{{ notifications.length }}</span>
                </div>
                <div class="chat-notif-header-actions">
                    <button
                        v-if="unreadCount > 0"
                        @click="markAllRead"
                        class="chat-notif-action-btn"
                        title="Markér alle som læst"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </button>
                    <button
                        v-if="notifications.length"
                        @click="clearAll"
                        class="chat-notif-action-btn chat-notif-action-btn--danger"
                        title="Tøm alle notifikationer"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- List -->
            <div class="chat-notif-list">
                <TransitionGroup name="notif-item">
                <div
                    v-for="n in notifications"
                    :key="n.id"
                    :class="['chat-notif-item', { 'unread': !n.is_read }]"
                    @click="openDetail(n)"
                >
                    <div class="chat-notif-icon-wrap">
                        <span class="chat-notif-icon">{{ notifIcons[n.type] || '🔔' }}</span>
                    </div>
                    <div class="chat-notif-body">
                        <p class="chat-notif-title">{{ n.title }}</p>
                        <p class="chat-notif-msg">{{ n.message }}</p>
                        <p class="chat-notif-time">{{ formatNotifTime(n.created_at) }}</p>
                    </div>
                    <span v-if="!n.is_read" class="chat-notif-dot"></span>
                </div>
                </TransitionGroup>

                <!-- Empty state -->
                <div v-if="!notifications.length" class="chat-notif-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                    <p>Ingen notifikationer</p>
                </div>
            </div>
        </div>
        </Transition>

        <!-- ── Detail modal ── -->
        <Transition name="notif-modal">
        <div v-if="selectedNotif" class="chat-notif-modal-backdrop" @click.self="closeDetail">
            <div class="chat-notif-modal">
                <button class="chat-notif-modal-close" @click="closeDetail" title="Luk">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                <div class="chat-notif-modal-icon">{{ notifIcons[selectedNotif.type] || '🔔' }}</div>
                <h3 class="chat-notif-modal-title">{{ selectedNotif.title }}</h3>
                <p class="chat-notif-modal-time">{{ formatNotifTime(selectedNotif.created_at) }}</p>
                <p class="chat-notif-modal-msg">{{ selectedNotif.message }}</p>
                <div class="chat-notif-modal-actions">
                    <button v-if="selectedNotif.action_url" @click="goToAction" class="chat-notif-modal-go">
                        Gå til
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </button>
                    <button @click="closeDetail" class="chat-notif-modal-dismiss">Luk</button>
                </div>
            </div>
        </div>
        </Transition>

    </div>
</template>
