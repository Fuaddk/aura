<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import axios from 'axios';

const $page = usePage();

const notifOpen     = ref(false);
const notifRef      = ref(null);
const notifications = ref([...$page.props.notifications ?? []]);
const unreadCount   = ref($page.props.unreadNotificationCount ?? 0);

// Sync with server props on every Inertia navigation (incl. back/forward)
watch(() => $page.props.unreadNotificationCount, (val) => {
    unreadCount.value = val ?? 0;
});
watch(() => $page.props.notifications, (val) => {
    if (val) notifications.value = [...val];
});

const notifIcons = {
    new_tasks:             '✅',
    subscription_upgraded: '⭐',
    subscription_renewed:  '🔄',
    deadline_soon:         '⏰',
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

const handleNotifClick = async (n) => {
    if (!n.is_read) {
        await axios.post(route('notifications.read', { notification: n.id }));
        n.is_read = true;
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    }
    notifOpen.value = false;
    if (n.action_url) router.visit(n.action_url);
};

const outsideClick = (e) => {
    if (notifRef.value && !notifRef.value.contains(e.target)) {
        notifOpen.value = false;
    }
};

onMounted(() => {
    const userId = $page.props.auth.user?.id;
    if (userId && window.Echo) {
        window.Echo.private(`notifications.${userId}`)
            .listen('.NotificationCreated', (data) => {
                notifications.value.unshift({ ...data, is_read: false });
                unreadCount.value++;
            });
    }
    document.addEventListener('click', outsideClick);
});

onUnmounted(() => {
    const userId = $page.props.auth.user?.id;
    if (userId && window.Echo) {
        window.Echo.leave(`notifications.${userId}`);
    }
    document.removeEventListener('click', outsideClick);
});
</script>

<template>
    <div class="chat-notif-wrapper" ref="notifRef">
        <button @click.stop="notifOpen = !notifOpen" class="chat-notif-btn" title="Notifikationer">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
            </svg>
            <span v-if="unreadCount > 0" class="chat-notif-badge">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
        </button>

        <Transition name="notif-fade">
        <div v-if="notifOpen" class="chat-notif-panel" @click.stop>
            <div class="chat-notif-header">
                <span class="chat-notif-header-title">Notifikationer</span>
                <button v-if="unreadCount > 0" @click="markAllRead" class="chat-notif-readall">Markér alle</button>
            </div>
            <div class="chat-notif-list">
                <div
                    v-for="n in notifications"
                    :key="n.id"
                    :class="['chat-notif-item', { 'unread': !n.is_read }]"
                    @click="handleNotifClick(n)"
                >
                    <span class="chat-notif-icon">{{ notifIcons[n.type] || '🔔' }}</span>
                    <div class="chat-notif-body">
                        <p class="chat-notif-title">{{ n.title }}</p>
                        <p class="chat-notif-msg">{{ n.message }}</p>
                        <p class="chat-notif-time">{{ formatNotifTime(n.created_at) }}</p>
                    </div>
                    <span v-if="!n.is_read" class="chat-notif-dot"></span>
                </div>
                <p v-if="!notifications.length" class="chat-notif-empty">Ingen notifikationer endnu</p>
            </div>
        </div>
        </Transition>
    </div>
</template>
