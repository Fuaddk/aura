<script setup>
import { ref, computed } from 'vue';
import ChatLayout from '@/Layouts/ChatLayout.vue';
import ChatSidebar from '@/Components/ChatSidebar.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    tasks: Array,
    cases: Array,
});

const sidebarOpen = ref(true);
const calendarDate = ref(new Date());
const selectedDate = ref(null);

const calendarYear = computed(() => calendarDate.value.getFullYear());
const calendarMonth = computed(() => calendarDate.value.getMonth());

const monthNames = ['Januar', 'Februar', 'Marts', 'April', 'Maj', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'December'];
const dayNames = ['Mandag', 'Tirsdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lørdag', 'Søndag'];
const dayNamesShort = ['Ma', 'Ti', 'On', 'To', 'Fr', 'Lø', 'Sø'];

const priorityLabels = { low: 'Lav', medium: 'Normal', high: 'Høj', critical: 'Kritisk' };

// Normalize due_date to YYYY-MM-DD (Laravel date cast sends full ISO datetime)
const toShortDate = (d) => d ? d.substring(0, 10) : null;

const tasks = computed(() =>
    props.tasks.map(t => ({ ...t, due_date: toShortDate(t.due_date) }))
);

const toDateStr = (year, month, day) => {
    return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
};

const calendarDays = computed(() => {
    const year = calendarYear.value;
    const month = calendarMonth.value;
    const lastDay = new Date(year, month + 1, 0);
    let startDay = new Date(year, month, 1).getDay() - 1;
    if (startDay < 0) startDay = 6;

    const days = [];
    const today = new Date();

    // Padding from previous month
    const prevLastDay = new Date(year, month, 0).getDate();
    for (let i = startDay - 1; i >= 0; i--) {
        days.push({ day: prevLastDay - i, current: false, dateStr: null, isToday: false, tasks: [] });
    }

    // Current month days
    for (let d = 1; d <= lastDay.getDate(); d++) {
        const dateStr = toDateStr(year, month, d);
        const isToday = today.getFullYear() === year && today.getMonth() === month && today.getDate() === d;
        const tasksOnDay = tasks.value.filter(t => t.due_date === dateStr);
        days.push({ day: d, current: true, dateStr, isToday, tasks: tasksOnDay });
    }

    // Padding from next month
    const remaining = 42 - days.length;
    for (let i = 1; i <= remaining; i++) {
        days.push({ day: i, current: false, dateStr: null, isToday: false, tasks: [] });
    }

    return days;
});

const selectedTasks = computed(() => {
    if (!selectedDate.value) return [];
    return tasks.value.filter(t => t.due_date === selectedDate.value);
});

const selectedDateFormatted = computed(() => {
    if (!selectedDate.value) return '';
    const d = new Date(selectedDate.value);
    const dayIndex = d.getDay() === 0 ? 6 : d.getDay() - 1;
    return `${dayNames[dayIndex]} ${d.getDate()}. ${monthNames[d.getMonth()]}`;
});

const upcomingTasks = computed(() => {
    const today = new Date().toISOString().split('T')[0];
    return tasks.value
        .filter(t => t.due_date >= today)
        .sort((a, b) => a.due_date.localeCompare(b.due_date))
        .slice(0, 5);
});

const overdueTasks = computed(() => {
    const today = new Date().toISOString().split('T')[0];
    return tasks.value.filter(t => t.due_date < today);
});

const prevMonth = () => {
    calendarDate.value = new Date(calendarYear.value, calendarMonth.value - 1, 1);
    selectedDate.value = null;
};

const nextMonth = () => {
    calendarDate.value = new Date(calendarYear.value, calendarMonth.value + 1, 1);
    selectedDate.value = null;
};

const goToday = () => {
    calendarDate.value = new Date();
    selectedDate.value = new Date().toISOString().split('T')[0];
};

const selectDay = (d) => {
    if (d.current && d.dateStr) {
        selectedDate.value = selectedDate.value === d.dateStr ? null : d.dateStr;
    }
};

const openTaskChat = (task) => {
    router.visit(route('chat.task', { task: task.id }));
};

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('da-DK', { day: 'numeric', month: 'short' });
};
</script>

<template>
    <Head title="Kalender - Aura" />

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
                    <h2 class="page-topbar-title">Kalender</h2>
                </div>

                <div class="page-scroll">
                    <div class="cal-page">
                        <!-- Calendar -->
                        <div class="cal-main">
                            <!-- Calendar Header -->
                            <div class="cal-header">
                                <div class="cal-header-left">
                                    <h3 class="cal-month-title">{{ monthNames[calendarMonth] }} {{ calendarYear }}</h3>
                                </div>
                                <div class="cal-header-right">
                                    <button @click="goToday" class="cal-today-btn">I dag</button>
                                    <button @click="prevMonth" class="cal-nav-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                                    </button>
                                    <button @click="nextMonth" class="cal-nav-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Calendar Grid -->
                            <div class="cal-grid">
                                <div v-for="name in dayNamesShort" :key="name" class="cal-dayname">{{ name }}</div>
                                <div
                                    v-for="(d, i) in calendarDays"
                                    :key="i"
                                    :class="['cal-cell', {
                                        'cal-cell-other': !d.current,
                                        'cal-cell-today': d.isToday,
                                        'cal-cell-selected': d.dateStr === selectedDate,
                                        'cal-cell-has-task': d.tasks.length > 0,
                                    }]"
                                    @click="selectDay(d)"
                                >
                                    <span class="cal-cell-num">{{ d.day }}</span>
                                    <div v-if="d.tasks.length > 0" class="cal-cell-dots">
                                        <span
                                            v-for="(task, ti) in d.tasks.slice(0, 3)"
                                            :key="ti"
                                            :class="['cal-cell-dot', `cal-dot-${task.priority}`]"
                                        ></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right panel -->
                        <div class="cal-panel">
                            <!-- Selected date tasks -->
                            <div v-if="selectedDate" class="cal-panel-section">
                                <h4 class="cal-panel-title">{{ selectedDateFormatted }}</h4>
                                <div v-if="selectedTasks.length === 0" class="cal-panel-empty">
                                    Ingen frister denne dag
                                </div>
                                <div v-for="task in selectedTasks" :key="task.id" class="cal-task-card" @click="openTaskChat(task)">
                                    <div class="cal-task-priority" :class="`cal-task-priority-${task.priority}`"></div>
                                    <div class="cal-task-info">
                                        <span class="cal-task-title">{{ task.title }}</span>
                                        <span class="cal-task-meta">{{ priorityLabels[task.priority] || 'Normal' }}</span>
                                    </div>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="cal-task-arrow" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                    </svg>
                                </div>
                            </div>

                            <!-- Overdue -->
                            <div v-if="overdueTasks.length > 0 && !selectedDate" class="cal-panel-section">
                                <h4 class="cal-panel-title cal-panel-overdue">Overskredet</h4>
                                <div v-for="task in overdueTasks" :key="task.id" class="cal-task-card" @click="openTaskChat(task)">
                                    <div class="cal-task-priority cal-task-priority-critical"></div>
                                    <div class="cal-task-info">
                                        <span class="cal-task-title">{{ task.title }}</span>
                                        <span class="cal-task-meta">{{ formatDate(task.due_date) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Upcoming -->
                            <div v-if="upcomingTasks.length > 0 && !selectedDate" class="cal-panel-section">
                                <h4 class="cal-panel-title">Kommende frister</h4>
                                <div v-for="task in upcomingTasks" :key="task.id" class="cal-task-card" @click="openTaskChat(task)">
                                    <div class="cal-task-priority" :class="`cal-task-priority-${task.priority}`"></div>
                                    <div class="cal-task-info">
                                        <span class="cal-task-title">{{ task.title }}</span>
                                        <span class="cal-task-meta">{{ formatDate(task.due_date) }}</span>
                                    </div>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="cal-task-arrow" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                    </svg>
                                </div>
                            </div>

                            <!-- Empty state -->
                            <div v-if="tasks.length === 0 && !selectedDate" class="cal-panel-section">
                                <div class="cal-panel-empty">
                                    <p>Ingen frister endnu</p>
                                    <span>Frister oprettes automatisk fra opgaver</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ChatLayout>
</template>
