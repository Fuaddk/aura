<script setup>
import { ref, onMounted, nextTick, computed } from 'vue';
import ChatLayout from '@/Layouts/ChatLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

marked.setOptions({ breaks: true, gfm: true });
const renderMarkdown = (text) => {
    if (!text) return '';
    return DOMPurify.sanitize(marked.parse(text));
};

const props = defineProps({
    task: Object,
    conversations: Array,
    documents: Array,
    cases: Array,
});

const message = ref('');
const messages = ref(props.conversations.map(msg => ({
    ...msg,
    document: msg.retrieved_chunks?.document || null,
})));
const isLoading = ref(false);
const chatContainer = ref(null);
const textarea = ref(null);
const taskStatus = ref(props.task.status);
const savedDocuments = ref(props.documents || []);
const savingDoc = ref(null);

const priorityLabels = { low: 'Lav', medium: 'Normal', high: 'Høj', critical: 'Kritisk' };
const statusLabels = { pending: 'Afventer', in_progress: 'I gang', completed: 'Fuldført' };

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('da-DK', { day: 'numeric', month: 'long', year: 'numeric' });
};

const daysUntilDue = computed(() => {
    if (!props.task.due_date) return null;
    const now = new Date();
    const due = new Date(props.task.due_date);
    const diff = Math.ceil((due - now) / (1000 * 60 * 60 * 24));
    return diff;
});

const isCompleted = computed(() => taskStatus.value === 'completed');

const toggleComplete = async () => {
    const newStatus = isCompleted.value ? 'pending' : 'completed';
    try {
        await axios.patch(route('tasks.status', { task: props.task.id }), {
            status: newStatus,
        });
        taskStatus.value = newStatus;
    } catch (error) {
        console.error('Error updating task status:', error);
    }
};

const saveDocument = async (doc, index) => {
    savingDoc.value = index;
    try {
        const response = await axios.post(route('tasks.documents.save', { task: props.task.id }), {
            title: doc.title,
            content: doc.content,
        });
        if (response.data.success) {
            savedDocuments.value.unshift(response.data.document);
            // Mark the doc as saved in the message
            messages.value[index].documentSaved = true;
        }
    } catch (error) {
        console.error('Error saving document:', error);
    } finally {
        savingDoc.value = null;
    }
};

const sendMessage = async () => {
    if (!message.value.trim() || isLoading.value) return;

    const userMessage = message.value;
    message.value = '';
    resetTextareaHeight();

    messages.value.push({
        role: 'user',
        content: userMessage,
        created_at: new Date().toISOString(),
    });

    scrollToBottom();
    isLoading.value = true;

    try {
        const response = await axios.post(route('chat.task.send', { task: props.task.id }), {
            message: userMessage,
        });

        messages.value.push({
            role: 'assistant',
            content: response.data.message,
            document: response.data.document || null,
            tasks: response.data.tasks || [],
            created_at: new Date().toISOString(),
        });

        scrollToBottom();
    } catch (error) {
        console.error('Error sending message:', error);
        messages.value.push({
            role: 'assistant',
            content: 'Beklager, der skete en fejl. Prøv venligst igen.',
            created_at: new Date().toISOString(),
        });
        scrollToBottom();
    } finally {
        isLoading.value = false;
    }
};

const scrollToBottom = () => {
    nextTick(() => {
        if (chatContainer.value) {
            chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
        }
    });
};

const autoResizeTextarea = () => {
    if (textarea.value) {
        textarea.value.style.height = 'auto';
        textarea.value.style.height = Math.min(textarea.value.scrollHeight, 200) + 'px';
    }
};

const resetTextareaHeight = () => {
    if (textarea.value) {
        textarea.value.style.height = 'auto';
    }
};

const goBack = () => {
    router.visit(route('tasks.index'));
};

const hasMessages = computed(() => messages.value.length > 0);

onMounted(() => {
    scrollToBottom();

    // Auto-send first message if no conversation yet
    if (!hasMessages.value) {
        const taskMsg = props.task.description
            ? `Hjælp mig med opgaven: "${props.task.title}" - ${props.task.description}`
            : `Hjælp mig med opgaven: "${props.task.title}"`;
        message.value = taskMsg;
        nextTick(() => sendMessage());
    }
});
</script>

<template>
    <Head :title="`${task.title} - Aura`" />

    <ChatLayout>
        <div class="tc-container">
            <!-- Left: Task Details -->
            <div class="tc-sidebar">
                <div class="tc-sidebar-header">
                    <button @click="goBack" class="tc-back-btn" title="Tilbage til opgaver">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                    </button>
                    <h2 class="tc-sidebar-title">Opgavedetaljer</h2>
                </div>

                <div class="tc-sidebar-content">
                    <!-- Task Title -->
                    <div class="tc-task-title">{{ task.title }}</div>

                    <!-- Complete Button -->
                    <button
                        @click="toggleComplete"
                        :class="['tc-complete-btn', { 'tc-complete-btn-done': isCompleted }]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        {{ isCompleted ? 'Fuldført' : 'Marker som løst' }}
                    </button>

                    <!-- Badges -->
                    <div class="tc-badges">
                        <span :class="['tc-badge', `tc-badge-priority-${task.priority}`]">
                            {{ priorityLabels[task.priority] || 'Normal' }}
                        </span>
                        <span :class="['tc-badge', `tc-badge-status-${taskStatus}`]">
                            {{ statusLabels[taskStatus] || taskStatus }}
                        </span>
                    </div>

                    <!-- Description -->
                    <div v-if="task.description" class="tc-section">
                        <div class="tc-section-label">Beskrivelse</div>
                        <p class="tc-section-text">{{ task.description }}</p>
                    </div>

                    <!-- Due Date -->
                    <div v-if="task.due_date" class="tc-section">
                        <div class="tc-section-label">Frist</div>
                        <div class="tc-due-card">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                            </svg>
                            <div class="tc-due-info">
                                <span class="tc-due-date">{{ formatDate(task.due_date) }}</span>
                                <span v-if="daysUntilDue !== null" :class="['tc-due-remaining', { 'tc-due-overdue': daysUntilDue < 0, 'tc-due-soon': daysUntilDue >= 0 && daysUntilDue <= 3 }]">
                                    {{ daysUntilDue < 0 ? `${Math.abs(daysUntilDue)} dage overskredet` : daysUntilDue === 0 ? 'I dag' : `${daysUntilDue} dage tilbage` }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Saved Documents -->
                    <div v-if="savedDocuments.length > 0" class="tc-section">
                        <div class="tc-section-label">Gemte dokumenter</div>
                        <div class="tc-doc-list">
                            <div v-for="doc in savedDocuments" :key="doc.id" class="tc-doc-card">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                                <span class="tc-doc-name">{{ doc.ai_summary || doc.original_filename }}</span>
                                <a :href="route('documents.download', { document: doc.id })" class="tc-doc-download" title="Download">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- AI Reasoning -->
                    <div v-if="task.ai_reasoning" class="tc-section">
                        <div class="tc-section-label">Auras begrundelse</div>
                        <p class="tc-section-text tc-reasoning">{{ task.ai_reasoning }}</p>
                    </div>

                    <!-- Task Type -->
                    <div class="tc-section">
                        <div class="tc-section-label">Type</div>
                        <span class="tc-type-badge">
                            {{ { samvaer: 'Samvær & Børn', bolig: 'Bolig & Ejendom', oekonomi: 'Økonomi & Gæld', juridisk: 'Juridisk', kommune: 'Kommune & Myndigheder', dokument: 'Dokumenter & Aftaler', forsikring: 'Forsikring & Pension', personlig: 'Personlig Trivsel' }[task.task_type] || task.task_type }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Right: Chat -->
            <div class="tc-chat">
                <div class="tc-chat-header">
                    <img src="/cirkel.png" alt="Aura" class="tc-chat-avatar" />
                    <div>
                        <div class="tc-chat-header-title">Chat med Aura</div>
                        <div class="tc-chat-header-sub">Få hjælp til at løse denne opgave</div>
                    </div>
                </div>

                <!-- Messages -->
                <div ref="chatContainer" class="tc-chat-messages">
                    <div class="tc-chat-messages-inner">
                        <div v-if="!hasMessages" class="tc-chat-empty">
                            <p>Aura analyserer din opgave...</p>
                        </div>

                        <div v-for="(msg, index) in messages" :key="index">
                            <!-- User Message -->
                            <div v-if="msg.role === 'user'" class="chat-msg-user">
                                <div class="chat-msg-user-bubble">
                                    <p>{{ msg.content }}</p>
                                </div>
                                <div class="chat-msg-user-avatar">
                                    {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                                </div>
                            </div>

                            <!-- Assistant Message -->
                            <div v-else class="chat-msg-assistant">
                                <img src="/cirkel.png" alt="Aura" class="chat-msg-avatar-img" />
                                <div class="chat-msg-assistant-content">
                                    <div class="chat-msg-body" v-html="renderMarkdown(msg.content)"></div>

                                    <!-- Document generated by AI -->
                                    <div v-if="msg.document" class="chat-document">
                                        <div class="chat-document-header">
                                            <div class="chat-document-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                </svg>
                                            </div>
                                            <span class="chat-document-title">{{ msg.document.title }}</span>
                                            <button
                                                v-if="!msg.documentSaved"
                                                class="tc-doc-save-btn"
                                                @click="saveDocument(msg.document, index)"
                                                :disabled="savingDoc === index"
                                                title="Gem på opgaven"
                                            >
                                                <svg v-if="savingDoc !== index" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                                </svg>
                                                <span v-if="savingDoc !== index">Gem</span>
                                                <span v-else>Gemmer...</span>
                                            </button>
                                            <span v-else class="tc-doc-saved-badge">Gemt</span>
                                        </div>
                                        <div class="chat-document-body">
                                            <pre class="chat-document-content">{{ msg.document.content }}</pre>
                                        </div>
                                    </div>

                                    <!-- Tasks generated by AI -->
                                    <div v-if="msg.tasks && msg.tasks.length" class="chat-tasks">
                                        <div class="chat-tasks-header">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                            </svg>
                                            <span>{{ msg.tasks.length }} {{ msg.tasks.length === 1 ? 'opgave oprettet' : 'opgaver oprettet' }}</span>
                                        </div>
                                        <a v-for="t in msg.tasks" :key="t.id" :href="route('chat.task', { task: t.id })" class="chat-task-item chat-task-item-link">
                                            <div class="chat-task-dot"></div>
                                            <div class="chat-task-info">
                                                <span class="chat-task-title">{{ t.title }}</span>
                                                <span v-if="t.due_date" class="chat-task-due">Frist: {{ new Date(t.due_date).toLocaleDateString('da-DK') }}</span>
                                            </div>
                                            <span :class="'chat-task-priority chat-task-priority-' + t.priority">
                                                {{ {low: 'Lav', medium: 'Normal', high: 'Høj', critical: 'Kritisk'}[t.priority] || t.priority }}
                                            </span>
                                            <svg class="chat-task-arrow" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Loading -->
                        <div v-if="isLoading" class="chat-loading">
                            <img src="/cirkel.png" alt="Aura" class="chat-msg-avatar-img" />
                            <div class="chat-loading-dots">
                                <div class="chat-loading-dot"></div>
                                <div class="chat-loading-dot"></div>
                                <div class="chat-loading-dot"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input -->
                <div class="tc-chat-input">
                    <div class="chat-input-container">
                        <textarea
                            ref="textarea"
                            v-model="message"
                            @input="autoResizeTextarea"
                            @keydown.enter.exact.prevent="sendMessage"
                            placeholder="Stil et spørgsmål om opgaven..."
                            rows="1"
                            class="chat-textarea"
                            :disabled="isLoading"
                        ></textarea>
                        <button
                            @click="sendMessage"
                            :disabled="!message.trim() || isLoading"
                            class="chat-send-btn"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </ChatLayout>
</template>
