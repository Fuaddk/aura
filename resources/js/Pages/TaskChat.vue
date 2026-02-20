<script setup>
import { ref, onMounted, nextTick, computed } from 'vue';
import ChatLayout from '@/Layouts/ChatLayout.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

marked.setOptions({ breaks: true, gfm: true });
const renderMarkdown = (text) => {
    if (!text) return '';
    return DOMPurify.sanitize(marked.parse(text));
};

const renderWithCursor = (text) => {
    if (!text) return '';
    const html = DOMPurify.sanitize(marked.parse(text));
    return html.replace(/(<\/\w+>)\s*$/, '<span class="streaming-cursor"></span>$1');
};

const getCsrfToken = () => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

let typewriterTimer = null;
const typewriter = (index, fullText, onComplete) => {
    if (typewriterTimer) clearInterval(typewriterTimer);
    const total = fullText.length;
    if (total === 0) { onComplete?.(); return; }
    let shown = 0;
    typewriterTimer = setInterval(() => {
        shown++;
        messages.value[index].content = fullText.slice(0, shown);
        if (shown >= total) {
            clearInterval(typewriterTimer);
            typewriterTimer = null;
            onComplete?.();
        }
    }, 18);
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

// File upload state
const uploadFile = ref(null);
const uploadPreview = ref(null);
const fileInputRef = ref(null);

const page = usePage();

// Model selector
const selectedModel = ref(page.props.auth?.user?.preferred_model ?? 'mistral-small-latest');
const isPaidPlan = computed(() => page.props.auth?.user?.subscription_plan !== 'free');
const selectModel = (model) => {
    if (model === 'mistral-large-latest' && !isPaidPlan.value) return;
    selectedModel.value = model;
    window.axios.patch(route('user.preferred-model'), { model });
};

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

const handleFileSelect = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    uploadFile.value = file;
    uploadPreview.value = {
        name: file.name,
        size: file.size,
        type: file.type,
    };
    e.target.value = '';
};

const clearUpload = () => {
    uploadFile.value = null;
    uploadPreview.value = null;
};

const formatBytes = (bytes) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
};

const sendMessage = async () => {
    const hasFile = !!uploadFile.value;
    if (!hasFile && !message.value.trim()) return;
    if (isLoading.value) return;
    isLoading.value = true;

    const userMessage = message.value;
    const fileToSend  = uploadFile.value;
    const previewSnap = uploadPreview.value;
    const modelForThisMessage = selectedModel.value;
    message.value = '';
    resetTextareaHeight();
    clearUpload();

    // Push user message (show filename if file attached)
    const userContent = hasFile
        ? (userMessage.trim() ? `📎 ${previewSnap.name}\n\n${userMessage}` : `📎 ${previewSnap.name}`)
        : userMessage;
    messages.value.push({
        role: 'user',
        content: userContent,
        created_at: new Date().toISOString(),
        uploadedFile: hasFile ? previewSnap : null,
    });

    messages.value.push({
        role: 'assistant',
        content: '',
        document: null,
        tasks: [],
        model_used: null,
        created_at: new Date().toISOString(),
        isStreaming: true,
    });
    const msgIndex = messages.value.length - 1;

    scrollToBottom();

    try {
        let res;
        if (hasFile) {
            const formData = new FormData();
            formData.append('file', fileToSend);
            if (userMessage.trim()) formData.append('message', userMessage);
            formData.append('model', modelForThisMessage);
            res = await fetch(route('chat.task.upload', { task: props.task.id }), {
                method: 'POST',
                headers: {
                    'Accept': 'text/event-stream',
                    'X-XSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });
        } else {
            res = await fetch(route('chat.task.send', { task: props.task.id }), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/event-stream',
                    'X-XSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ message: userMessage, model: modelForThisMessage }),
            });
        }

        if (!res.ok) {
            const errData = await res.json().catch(() => ({}));
            messages.value[msgIndex].content = res.status === 429
                ? (errData.message || 'Du har brugt alle dine AI-beskeder denne måned.')
                : res.status === 403 && errData.message === 'upload_not_allowed'
                ? 'Filopload kræver et aktivt abonnement.'
                : 'Beklager, der skete en fejl. Prøv venligst igen.';
            messages.value[msgIndex].isStreaming = false;
            scrollToBottom();
            return;
        }

        const reader = res.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop();

            for (const line of lines) {
                if (!line.startsWith('data: ')) continue;
                const raw = line.slice(6).trim();
                if (!raw) continue;

                let evt;
                try { evt = JSON.parse(raw); } catch { continue; }

                if (evt.type === 'done') {
                    const pendingTasks    = evt.tasks || [];
                    const pendingDocument = evt.document || null;
                    messages.value[msgIndex].content = '';
                    typewriter(msgIndex, evt.message, () => {
                        messages.value[msgIndex].isStreaming = false;
                        messages.value[msgIndex].tasks      = pendingTasks;
                        messages.value[msgIndex].document   = pendingDocument;
                        messages.value[msgIndex].model_used = evt.model_used || modelForThisMessage;
                        scrollToBottom();
                        router.reload({
                            only: [
                                'task',
                                'documents',
                                'pendingTaskCount',
                                'urgentTaskCount',
                                'warningTaskCount',
                                'soonTaskCount',
                                'taskDueDates'
                            ]
                        });
                    });
                }
            }
        }
    } catch (error) {
        console.error('Error sending message:', error);
        messages.value[msgIndex].content = 'Beklager, der skete en fejl. Prøv venligst igen.';
        messages.value[msgIndex].isStreaming = false;
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
                    <NotificationBell style="margin-left: auto;" />
                </div>

                <!-- Messages -->
                <div ref="chatContainer" class="tc-chat-messages">
                    <div class="tc-chat-messages-inner">

                        <!-- Welcome / Empty State -->
                        <Transition name="welcome-fade" appear>
                        <div v-if="!hasMessages" class="tc-chat-welcome">
                            <div class="tc-welcome-greeting">
                                <h1 class="tc-welcome-title">
                                    {{ $page.props.auth.user.name.split(' ')[0] }}, hvordan kan jeg hjælpe dig?
                                    <svg class="tc-welcome-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                                    </svg>
                                </h1>
                                <p class="tc-welcome-sub">
                                    Lad os løse denne opgave sammen, ét skridt ad gangen.
                                </p>
                            </div>

                            <!-- Divider -->
                            <p class="tc-welcome-prompt">Vælg en tilgang eller stil dit eget spørgsmål</p>

                            <!-- Suggestion Cards -->
                            <div class="tc-suggestions">
                                <button
                                    @click="message = 'Giv mig et overblik over hvad jeg skal gøre for at løse denne opgave.'; sendMessage()"
                                    class="tc-suggestion-card"
                                >
                                    <div class="tc-suggestion-icon tc-suggestion-icon--blue">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                                        </svg>
                                    </div>
                                    <div class="tc-suggestion-text">
                                        <p class="tc-suggestion-card-title">Få et overblik</p>
                                        <p class="tc-suggestion-card-desc">Se hvad der skal gøres</p>
                                    </div>
                                </button>
                                <button
                                    @click="message = 'Hvilke trin skal jeg tage først, og i hvilken rækkefølge?'; sendMessage()"
                                    class="tc-suggestion-card"
                                >
                                    <div class="tc-suggestion-icon tc-suggestion-icon--green">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                                        </svg>
                                    </div>
                                    <div class="tc-suggestion-text">
                                        <p class="tc-suggestion-card-title">Hvor starter jeg?</p>
                                        <p class="tc-suggestion-card-desc">Få en trin-for-trin plan</p>
                                    </div>
                                </button>
                                <button
                                    @click="message = 'Hjælp mig med at oprette de dokumenter eller materialer jeg har brug for.'; sendMessage()"
                                    class="tc-suggestion-card"
                                >
                                    <div class="tc-suggestion-icon tc-suggestion-icon--amber">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                    </div>
                                    <div class="tc-suggestion-text">
                                        <p class="tc-suggestion-card-title">Opret dokumenter</p>
                                        <p class="tc-suggestion-card-desc">Skabeloner og materialer</p>
                                    </div>
                                </button>
                                <button
                                    @click="message = 'Hvad skal jeg være særligt opmærksom på når jeg løser denne opgave?'; sendMessage()"
                                    class="tc-suggestion-card"
                                >
                                    <div class="tc-suggestion-icon tc-suggestion-icon--rose">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                        </svg>
                                    </div>
                                    <div class="tc-suggestion-text">
                                        <p class="tc-suggestion-card-title">Vigtige opmærksomhedspunkter</p>
                                        <p class="tc-suggestion-card-desc">Undgå faldgruber</p>
                                    </div>
                                </button>
                            </div>
                        </div>
                        </Transition>

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
                                    <div v-if="msg.isStreaming && !msg.content" class="chat-loading-dots" style="padding: 4px 0;">
                                        <div class="chat-loading-dot"></div>
                                        <div class="chat-loading-dot"></div>
                                        <div class="chat-loading-dot"></div>
                                    </div>
                                    <div v-else class="chat-msg-body" v-html="msg.isStreaming ? renderWithCursor(msg.content) : renderMarkdown(msg.content)"></div>

                                    <!-- Model badge -->
                                    <div v-if="!msg.isStreaming && msg.model_used" class="chat-model-badge">
                                        {{ msg.model_used === 'mistral-large-latest' ? 'Aura-ML-o2' : 'Aura-MS-o1' }}
                                    </div>

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

                    </div>
                </div>

                <!-- Input -->
                <div class="tc-chat-input" :style="isLoading ? { pointerEvents: 'none', userSelect: 'none' } : {}">
                    <!-- File Preview -->
                    <div v-if="uploadPreview" class="chat-file-preview">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="chat-file-preview-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                        </svg>
                        <div class="chat-file-preview-info">
                            <span class="chat-file-preview-name">{{ uploadPreview.name }}</span>
                            <span class="chat-file-preview-size">{{ formatBytes(uploadPreview.size) }}</span>
                        </div>
                        <button @click="clearUpload" class="chat-file-preview-remove" title="Fjern fil">×</button>
                    </div>

                    <div class="chat-input-container">
                        <input
                            ref="fileInputRef"
                            type="file"
                            accept=".pdf,.txt,.jpg,.jpeg,.png"
                            class="chat-file-input-hidden"
                            @change="handleFileSelect"
                        />
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
                        <!-- Bottom toolbar: model selector + paperclip + send -->
                        <div class="chat-input-bottom">
                            <div class="chat-model-selector">
                                <button
                                    @click="selectModel('mistral-small-latest')"
                                    :class="['chat-model-btn', { 'chat-model-btn-active': selectedModel === 'mistral-small-latest' }]"
                                    title="Aura-MS-o1 — Mistral Small (hurtig, alle planer)"
                                >Aura-MS-o1</button>
                                <button
                                    @click="selectModel('mistral-large-latest')"
                                    :class="['chat-model-btn', { 'chat-model-btn-active': selectedModel === 'mistral-large-latest', 'chat-model-btn-locked': !isPaidPlan }]"
                                    :title="isPaidPlan ? 'Aura-ML-o2 — Mistral Large (kraftfuld)' : 'Kræver betalt plan'"
                                >
                                    Aura-ML-o2
                                    <svg v-if="!isPaidPlan" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:0.7em;height:0.7em;display:inline;vertical-align:middle;margin-left:2px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                    </svg>
                                </button>
                            </div>
                            <button
                                @click="isPaidPlan ? fileInputRef.click() : null"
                                :disabled="isLoading || !isPaidPlan"
                                class="chat-attach-btn"
                                :title="isPaidPlan ? 'Vedhæft dokument (PDF, billede, TXT)' : 'Kræver abonnement'"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                                </svg>
                            </button>
                            <button
                                @click="sendMessage"
                                :disabled="(!message.trim() && !uploadFile) || isLoading"
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
        </div>
    </ChatLayout>
</template>
