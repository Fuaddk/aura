<script setup>
import { ref, computed } from 'vue';
import ChatLayout from '@/Layouts/ChatLayout.vue';
import ChatSidebar from '@/Components/ChatSidebar.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    tasks: Array,
    cases: Array,
});

const sidebarOpen = ref(true);
const calendarDate = ref(new Date());
const selectedDate = ref(null);
const showSyncModal = ref(false);
const calendarUrl = window.location.origin + '/calendar/ics';
const webcalUrl = calendarUrl.replace(/^https?:/, 'webcal:');

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

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
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
                @toggle="sidebarOpen = !sidebarOpen"
            />

            <div class="chat-main">
                <div class="chat-topbar">
                    <h2 class="page-topbar-title">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="topbar-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        Kalender
                    </h2>
                    <NotificationBell style="margin-left: auto;" />
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
                                    <button @click="showSyncModal = true" class="cal-sync-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                                        </svg>
                                        Synkronisér
                                    </button>
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

        <!-- Sync Modal -->
        <div v-if="showSyncModal" class="modal-overlay" @click.self="showSyncModal = false">
            <div class="modal-card">
                <div class="modal-header">
                    <div class="modal-header-content">
                        <div class="modal-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                            </svg>
                        </div>
                        <div>
                            <h3>Synkronisér kalender</h3>
                            <p class="modal-subtitle">Hold styr på dine frister direkte i din telefon</p>
                        </div>
                    </div>
                    <button @click="showSyncModal = false" class="modal-close">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="platform-cards">
                        <div class="platform-card">
                            <div class="platform-card-header">
                                <div class="platform-icon platform-icon-ios">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09l.01-.01zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/>
                                    </svg>
                                </div>
                                <h4>iPhone / iPad</h4>
                            </div>
                            <ol class="platform-steps">
                                <li><strong>Indstillinger</strong> → <strong>Kalender</strong> → <strong>Konti</strong></li>
                                <li><strong>Tilføj konto</strong> → <strong>Andet</strong></li>
                                <li><strong>Tilføj abonnementskalender</strong></li>
                                <li>Indsæt URL'en nedenfor</li>
                            </ol>
                        </div>

                        <div class="platform-card">
                            <div class="platform-card-header">
                                <div class="platform-icon platform-icon-android">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M17.6 9.48l1.84-3.18c.16-.31.04-.69-.26-.85a.637.637 0 0 0-.83.22l-1.88 3.24a11.46 11.46 0 0 0-8.94 0L5.65 5.67a.643.643 0 0 0-.87-.2c-.28.18-.37.54-.22.83L6.4 9.48A10.78 10.78 0 0 0 1 18h22a10.78 10.78 0 0 0-5.4-8.52zM7 15.25a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5zm10 0a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5z"/>
                                    </svg>
                                </div>
                                <h4>Android / Google</h4>
                            </div>
                            <ol class="platform-steps">
                                <li>Åbn <a :href="webcalUrl" target="_blank" class="inline-link">dette link</a> på din telefon, eller:</li>
                                <li><strong>Google Kalender</strong> på computer</li>
                                <li><strong>+</strong> ved "Andre kalendere" → <strong>Fra URL</strong></li>
                                <li>Indsæt URL'en nedenfor</li>
                            </ol>
                        </div>
                    </div>

                    <div class="sync-url-section">
                        <label class="sync-url-label">Kalender URL</label>
                        <div class="sync-url-box">
                            <input
                                type="text"
                                :value="webcalUrl"
                                readonly
                                class="sync-url-input"
                                @click="$event.target.select()"
                            />
                            <button @click="copyToClipboard(webcalUrl)" class="sync-copy-btn" title="Kopiér">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="sync-info-box">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        </svg>
                        <div>
                            <strong>Automatisk opdatering</strong>
                            <p>Kalenderen opdateres automatisk når du opretter nye opgaver med frister i Aura.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ChatLayout>
</template>

<style scoped>
.cal-sync-btn {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.5rem 1rem;
    background: #8B7FF5;
    color: white;
    border: none;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.cal-sync-btn:hover {
    background: #7A6EE4;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(139, 127, 245, 0.3);
}

.cal-sync-btn svg {
    width: 1.125rem;
    height: 1.125rem;
}

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 1rem;
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-card {
    background: white;
    border-radius: 1rem;
    max-width: 800px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 2rem;
    background: linear-gradient(135deg, #f8f7ff 0%, #ffffff 100%);
    border-bottom: 1px solid #e5e7eb;
}

.modal-header-content {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}

.modal-icon {
    width: 3rem;
    height: 3rem;
    background: linear-gradient(135deg, #8B7FF5 0%, #7A6EE4 100%);
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.modal-icon svg {
    width: 1.5rem;
    height: 1.5rem;
}

.modal-header h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
    margin: 0 0 0.25rem 0;
    letter-spacing: -0.02em;
}

.modal-subtitle {
    font-size: 0.9375rem;
    color: #6b7280;
    margin: 0;
}

.modal-close {
    background: white;
    border: 1px solid #e5e7eb;
    color: #6b7280;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 0.5rem;
    transition: all 0.15s;
    flex-shrink: 0;
}

.modal-close:hover {
    background: #f9fafb;
    border-color: #d1d5db;
    color: #111827;
}

.modal-close svg {
    width: 1.25rem;
    height: 1.25rem;
    display: block;
}

.modal-body {
    padding: 2rem;
}

.platform-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.platform-card {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 1.5rem;
    transition: all 0.2s;
}

.platform-card:hover {
    border-color: #d1d5db;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.platform-card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.platform-icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.625rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.platform-icon svg {
    width: 1.5rem;
    height: 1.5rem;
}

.platform-icon-ios {
    background: linear-gradient(135deg, #555555 0%, #000000 100%);
    color: white;
}

.platform-icon-android {
    background: linear-gradient(135deg, #3DDC84 0%, #2BB86F 100%);
    color: white;
}

.platform-card h4 {
    font-size: 1.0625rem;
    font-weight: 600;
    color: #111827;
    margin: 0;
}

.platform-steps {
    margin: 0;
    padding-left: 1.25rem;
    color: #4b5563;
    font-size: 0.875rem;
    line-height: 1.6;
    list-style: none;
    counter-reset: step-counter;
}

.platform-steps li {
    margin-bottom: 0.625rem;
    counter-increment: step-counter;
    position: relative;
    padding-left: 0.5rem;
}

.platform-steps li::before {
    content: counter(step-counter);
    position: absolute;
    left: -1.25rem;
    width: 1.25rem;
    height: 1.25rem;
    background: #e5e7eb;
    color: #6b7280;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6875rem;
    font-weight: 600;
    line-height: 1.25rem;
    text-align: center;
}

.platform-steps li strong {
    font-weight: 600;
    color: #111827;
}

.inline-link {
    color: #8B7FF5;
    text-decoration: none;
    font-weight: 500;
    border-bottom: 1px solid transparent;
    transition: border-color 0.15s;
}

.inline-link:hover {
    border-bottom-color: #8B7FF5;
}

.sync-url-section {
    margin-bottom: 1.5rem;
}

.sync-url-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
}

.sync-url-box {
    display: flex;
    gap: 0.5rem;
}

.sync-url-input {
    flex: 1;
    font-family: 'SF Mono', 'Monaco', 'Menlo', 'Courier New', monospace;
    font-size: 0.8125rem;
    color: #111827;
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    outline: none;
    transition: all 0.15s;
}

.sync-url-input:focus {
    border-color: #8B7FF5;
    box-shadow: 0 0 0 3px rgba(139, 127, 245, 0.1);
}

.sync-copy-btn {
    flex-shrink: 0;
    padding: 0.75rem 1rem;
    background: #8B7FF5;
    border: none;
    border-radius: 0.5rem;
    cursor: pointer;
    color: white;
    transition: all 0.15s;
}

.sync-copy-btn:hover {
    background: #7A6EE4;
    transform: translateY(-1px);
}

.sync-copy-btn svg {
    width: 1.125rem;
    height: 1.125rem;
    display: block;
}

.sync-info-box {
    display: flex;
    gap: 0.875rem;
    padding: 1rem 1.25rem;
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border: 1px solid #bbf7d0;
    border-radius: 0.75rem;
}

.sync-info-box svg {
    width: 1.25rem;
    height: 1.25rem;
    color: #16a34a;
    flex-shrink: 0;
    margin-top: 0.125rem;
}

.sync-info-box strong {
    display: block;
    font-size: 0.9375rem;
    font-weight: 600;
    color: #166534;
    margin-bottom: 0.25rem;
}

.sync-info-box p {
    font-size: 0.875rem;
    color: #15803d;
    margin: 0;
    line-height: 1.5;
}
</style>
