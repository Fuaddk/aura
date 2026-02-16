<script setup>
import { ref } from 'vue';
import ChatLayout from '@/Layouts/ChatLayout.vue';
import ChatSidebar from '@/Components/ChatSidebar.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    tasks: Array,
    cases: Array,
    category: String,
    categoryCounts: Object,
});

const sidebarOpen = ref(true);

const categories = {
    samvaer:    { label: 'Samvær & Børn',         desc: 'Forældremyndighed, bopæl og samværsaftaler',   color: '#e11d48', bg: '#ffe4e6' },
    bolig:      { label: 'Bolig & Ejendom',        desc: 'Boligfordeling, flytning og ejendomssalg',    color: '#0891b2', bg: '#cffafe' },
    oekonomi:   { label: 'Økonomi & Gæld',         desc: 'Bodeling, fælles gæld og bankkonti',          color: '#b45309', bg: '#fef3c7' },
    juridisk:   { label: 'Juridisk',               desc: 'Advokat, retsmøder og juridiske dokumenter',  color: '#7c3aed', bg: '#ede9fe' },
    kommune:    { label: 'Kommune & Myndigheder',   desc: 'Familieretshuset, adresseændring og tilskud', color: '#2563eb', bg: '#dbeafe' },
    dokument:   { label: 'Dokumenter & Aftaler',    desc: 'Skilsmisseaftaler, samværsplaner og formularer', color: '#4f46e5', bg: '#e0e7ff' },
    forsikring: { label: 'Forsikring & Pension',    desc: 'Forsikringsændringer, pension og sundhed',   color: '#059669', bg: '#dcfce7' },
    personlig:  { label: 'Personlig Trivsel',       desc: 'Emotionel støtte, terapi og selvpleje',       color: '#d97706', bg: '#fef9c3' },
};

const totalTasks = Object.values(props.categoryCounts || {}).reduce((a, b) => a + b, 0);

const openCategory = (type) => {
    router.visit(route('tasks.index', { category: type }));
};

const goBack = () => {
    router.visit(route('tasks.index'));
};

const priorityLabel = (priority) => {
    const labels = { low: 'Lav', medium: 'Normal', high: 'Høj', critical: 'Kritisk' };
    return labels[priority] || 'Normal';
};

const statusLabel = (status) => {
    const labels = { pending: 'Afventer', in_progress: 'I gang', completed: 'Fuldført' };
    return labels[status] || status;
};

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('da-DK', { day: 'numeric', month: 'short', year: 'numeric' });
};

const openTaskChat = (task) => {
    router.visit(route('chat.task', { task: task.id }));
};
</script>

<template>
    <Head :title="category ? `${categories[category]?.label || 'Opgaver'} - Aura` : 'Opgaver - Aura'" />

    <ChatLayout>
        <div class="chat-container">
            <ChatSidebar
                :active-case="null"
                :cases="cases"
                :open="sidebarOpen"
                @close="sidebarOpen = false"
            />

            <div class="chat-main">
                <div class="chat-topbar">
                    <button
                        v-if="!sidebarOpen"
                        @click="sidebarOpen = true"
                        class="chat-topbar-toggle"
                        title="Åbn sidebar"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                        </svg>
                    </button>

                    <button v-if="category" @click="goBack" class="cat-back-btn" title="Tilbage til kategorier">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                    </button>

                    <h2 class="page-topbar-title">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="topbar-icon"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        {{ category ? categories[category]?.label || 'Opgaver' : 'Opgaver' }}
                    </h2>
                </div>

                <div class="page-scroll">
                    <div class="page-content">

                        <!-- No tasks at all -->
                        <div v-if="totalTasks === 0 && !category" class="page-empty">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <p>Ingen opgaver endnu</p>
                            <span>Opgaver oprettes automatisk når du chatter med Aura</span>
                        </div>

                        <!-- Category Cards (overview page) -->
                        <div v-if="!category && totalTasks > 0" class="cat-grid">
                            <button
                                v-for="(cat, type) in categories"
                                :key="type"
                                class="cat-card"
                                :class="{ 'cat-card-empty': !categoryCounts[type] }"
                                @click="categoryCounts[type] ? openCategory(type) : null"
                            >
                                <div class="cat-card-top">
                                    <div class="cat-card-icon" :style="{ backgroundColor: cat.bg, color: cat.color }">
                                        <!-- Samvær & Børn -->
                                        <svg v-if="type === 'samvaer'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                        </svg>
                                        <!-- Bolig & Ejendom -->
                                        <svg v-else-if="type === 'bolig'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                        </svg>
                                        <!-- Økonomi & Gæld -->
                                        <svg v-else-if="type === 'oekonomi'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                                        </svg>
                                        <!-- Juridisk -->
                                        <svg v-else-if="type === 'juridisk'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z" />
                                        </svg>
                                        <!-- Kommune & Myndigheder -->
                                        <svg v-else-if="type === 'kommune'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                        </svg>
                                        <!-- Dokumenter & Aftaler -->
                                        <svg v-else-if="type === 'dokument'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                        <!-- Forsikring & Pension -->
                                        <svg v-else-if="type === 'forsikring'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                        </svg>
                                        <!-- Personlig Trivsel -->
                                        <svg v-else-if="type === 'personlig'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                                        </svg>
                                    </div>
                                    <span v-if="categoryCounts[type]" class="cat-card-badge">{{ categoryCounts[type] }}</span>
                                </div>
                                <div class="cat-card-info">
                                    <span class="cat-card-label">{{ cat.label }}</span>
                                    <span class="cat-card-desc">{{ cat.desc }}</span>
                                </div>
                            </button>
                        </div>

                        <!-- Task List (category page) -->
                        <div v-if="category">
                            <div v-if="tasks.length === 0" class="page-empty">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <p>Ingen opgaver i denne kategori</p>
                            </div>

                            <div v-else class="task-list">
                                <div v-for="task in tasks" :key="task.id" class="task-card task-card-clickable" @click="openTaskChat(task)">
                                    <div class="task-card-header">
                                        <span :class="['task-priority', `task-priority-${task.priority}`]">
                                            {{ priorityLabel(task.priority) }}
                                        </span>
                                        <span :class="['task-status', `task-status-${task.status}`]">
                                            {{ statusLabel(task.status) }}
                                        </span>
                                    </div>
                                    <h3 class="task-title">{{ task.title }}</h3>
                                    <p v-if="task.description" class="task-desc">{{ task.description }}</p>
                                    <div class="task-card-footer">
                                        <div v-if="task.due_date" class="task-due">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                            </svg>
                                            {{ formatDate(task.due_date) }}
                                        </div>
                                        <div class="task-chat-hint">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                                            </svg>
                                            <span>Chat med Aura</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </ChatLayout>
</template>
