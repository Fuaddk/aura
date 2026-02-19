<script setup>
import { ref, onMounted, nextTick, computed } from 'vue';
import ChatLayout from '@/Layouts/ChatLayout.vue';
import ChatSidebar from '@/Components/ChatSidebar.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

// Configure marked for clean output
marked.setOptions({ breaks: true, gfm: true });

const renderMarkdown = (text) => {
    if (!text) return '';
    return DOMPurify.sanitize(marked.parse(text));
};

const renderWithCursor = (text) => {
    if (!text) return '';
    const html = DOMPurify.sanitize(marked.parse(text));
    // Insert cursor before the last closing tag so it sits inline with the text
    return html.replace(/(<\/\w+>)\s*$/, '<span class="streaming-cursor"></span>$1');
};

// Read XSRF-TOKEN cookie (Laravel sets it automatically)
const getCsrfToken = () => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

// Typewriter — mutates through messages.value[index] so Vue detects the change
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
    activeCase: Object,
    conversations: Array,
    cases: Array,
    tasks: Array,
    documents: Array,
    isFirstTime: Boolean,
});

const message = ref('');
// Map conversations to include document/task data from retrieved_chunks
const messages = ref(props.conversations.map(msg => ({
    ...msg,
    document: msg.retrieved_chunks?.document || null,
    tasks: msg.retrieved_chunks?.tasks || [],
})));
const isLoading = ref(false);
const chatContainer = ref(null);
const sidebarOpen = ref(true);
const textarea = ref(null);
const page = usePage();
const usagePercent = computed(() => {
    const user = page.props.auth.user;
    if (!user || !user.ai_tokens_limit) return 0;
    return Math.round((user.ai_tokens_used / user.ai_tokens_limit) * 100);
});
const canSend = computed(() => {
    if (usagePercent.value < 100) return true;
    const user = page.props.auth.user;
    return !!(user?.extra_usage_enabled && (user?.wallet_balance ?? 0) > 0);
});
const currentCaseId = ref(props.activeCase?.id || null);
let abortController = null;

// File upload state
const uploadFile = ref(null);
const uploadPreview = ref(null);
const fileInputRef = ref(null);

const handleFileSelect = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    uploadFile.value = file;
    uploadPreview.value = {
        name: file.name,
        size: file.size,
        type: file.type,
    };
    // Reset so the same file can be re-selected after clearing
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
    if (!canSend.value) return;
    isLoading.value = true;

    const userMessage = message.value;
    const fileToSend  = uploadFile.value;
    const previewSnap = uploadPreview.value;
    message.value = '';
    resetTextareaHeight();
    clearUpload();

    // Push user message (show filename chip if file attached)
    const userContent = hasFile
        ? (userMessage.trim() ? `📎 ${previewSnap.name}\n\n${userMessage}` : `📎 ${previewSnap.name}`)
        : userMessage;
    messages.value.push({
        role: 'user',
        content: userContent,
        created_at: new Date().toISOString(),
        uploadedFile: hasFile ? previewSnap : null,
    });

    // Add a streaming placeholder — track by index so typewriter uses the reactive proxy
    messages.value.push({
        role: 'assistant',
        content: '',
        created_at: new Date().toISOString(),
        tasks: [],
        document: null,
        isStreaming: true,
    });
    const msgIndex = messages.value.length - 1;

    scrollToBottom();
    abortController = new AbortController();

    try {
        let res;
        if (hasFile) {
            const formData = new FormData();
            formData.append('file', fileToSend);
            if (currentCaseId.value) formData.append('case_id', currentCaseId.value);
            if (userMessage.trim()) formData.append('message', userMessage);
            res = await fetch(route('chat.upload'), {
                method: 'POST',
                headers: {
                    'Accept': 'text/event-stream',
                    'X-XSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
                signal: abortController.signal,
            });
        } else {
            res = await fetch(route('chat.send'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/event-stream',
                    'X-XSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    message: userMessage,
                    case_id: currentCaseId.value,
                }),
                signal: abortController.signal,
            });
        }

        if (!res.ok) {
            const errData = await res.json().catch(() => ({}));
            messages.value[msgIndex].content = res.status === 429
                ? (errData.message || 'Du har brugt alle dine AI-beskeder denne måned.')
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
                    // Hold tasks/document back — only reveal after typing is done
                    const pendingTasks    = evt.tasks || [];
                    const pendingDocument = evt.document || null;
                    messages.value[msgIndex].content = '';

                    if (evt.case_id && !currentCaseId.value) {
                        currentCaseId.value = evt.case_id;
                        window.history.replaceState({}, '', route('dashboard', { case: evt.case_id }));
                    }

                    typewriter(msgIndex, evt.message, () => {
                        messages.value[msgIndex].isStreaming = false;
                        messages.value[msgIndex].tasks    = pendingTasks;
                        messages.value[msgIndex].document = pendingDocument;
                        scrollToBottom();
                        router.reload({
                            only: [
                                'cases',
                                'tasks',
                                'activeCase',
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
        const msg = messages.value[msgIndex];
        if (error.name === 'AbortError') {
            msg.content = msg.content || 'Svar stoppet.';
        } else {
            console.error('Error sending message:', error);
            msg.content = 'Beklager, der skete en fejl. Prøv venligst igen.';
        }
        msg.isStreaming = false;
        scrollToBottom();
        router.reload({
            only: [
                'cases',
                'activeCase',
                'pendingTaskCount',
                'urgentTaskCount',
                'warningTaskCount',
                'soonTaskCount',
                'taskDueDates'
            ]
        });
    } finally {
        isLoading.value = false;
        abortController = null;
    }
};

const stopGenerating = () => {
    abortController?.abort();
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

const copyDocument = (doc) => {
    const text = doc.title + '\n\n' + doc.content;
    navigator.clipboard.writeText(text);
};

const hasMessages = computed(() => messages.value.length > 0);

onMounted(() => {
    scrollToBottom();
});
</script>

<template>
    <Head title="Aura" />

    <ChatLayout>
        <div class="chat-container">

            <!-- Left Sidebar -->
            <ChatSidebar
                :active-case="activeCase"
                :cases="cases"
                :open="sidebarOpen"
                @toggle="sidebarOpen = !sidebarOpen"
            />

            <!-- Main Chat Area -->
            <div class="chat-main">

                <!-- Top Bar -->
                <div class="chat-header">
                    <img src="/cirkel.png" alt="Aura" class="chat-header-avatar" />
                    <div>
                        <div class="chat-header-title">Aura</div>
                        <div class="chat-header-sub">Din personlige sparringspartner</div>
                    </div>

                    <NotificationBell style="margin-left: auto;" />
                </div>

                <!-- Messages Area -->
                <div ref="chatContainer" class="chat-messages">
                    <div class="chat-messages-inner">

                        <!-- Welcome / Empty State -->
                        <Transition name="welcome-fade" appear>
                        <div v-if="!hasMessages" class="chat-welcome">

                            <div class="chat-welcome-greeting">
                                <h1 class="chat-welcome-title">
                                    Hej {{ $page.props.auth.user.name.split(' ')[0] }} 👋
                                </h1>
                                <p class="chat-welcome-sub">
                                    Jeg er Aura — din fortrolige støtte i en svær tid.<br>
                                    Jeg lytter, guider og hjælper dig videre, ét skridt ad gangen.
                                </p>
                            </div>

                            <!-- Divider -->
                            <p class="chat-welcome-prompt">Hvad kan jeg hjælpe dig med i dag?</p>

                            <!-- Suggestion Cards -->
                            <div class="chat-suggestions">
                                <button
                                    @click="message = 'Jeg er i starten af en skilsmisse og har brug for hjælp til at forstå processen.'; sendMessage()"
                                    class="chat-suggestion-card"
                                >
                                    <div class="chat-suggestion-icon chat-suggestion-icon--blue">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" /></svg>
                                    </div>
                                    <div class="chat-suggestion-text">
                                        <p class="chat-suggestion-card-title">Forstå processen</p>
                                        <p class="chat-suggestion-card-desc">Få overblik over skilsmisseforløbet</p>
                                    </div>
                                </button>
                                <button
                                    @click="message = 'Vi har fælles børn og skal finde ud af bopæl og samvær.'; sendMessage()"
                                    class="chat-suggestion-card"
                                >
                                    <div class="chat-suggestion-icon chat-suggestion-icon--green">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z" /></svg>
                                    </div>
                                    <div class="chat-suggestion-text">
                                        <p class="chat-suggestion-card-title">Børn og samvær</p>
                                        <p class="chat-suggestion-card-desc">Bopæl, samvær og forældremyndighed</p>
                                    </div>
                                </button>
                                <button
                                    @click="message = 'Vi skal dele fælles ejendom og økonomi. Hvad skal jeg være opmærksom på?'; sendMessage()"
                                    class="chat-suggestion-card"
                                >
                                    <div class="chat-suggestion-icon chat-suggestion-icon--amber">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg>
                                    </div>
                                    <div class="chat-suggestion-text">
                                        <p class="chat-suggestion-card-title">Økonomi og bolig</p>
                                        <p class="chat-suggestion-card-desc">Bodeling, ejendom og fælles gæld</p>
                                    </div>
                                </button>
                                <button
                                    @click="message = 'Jeg har det svært emotionelt og har brug for støtte og vejledning.'; sendMessage()"
                                    class="chat-suggestion-card"
                                >
                                    <div class="chat-suggestion-icon chat-suggestion-icon--rose">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" /></svg>
                                    </div>
                                    <div class="chat-suggestion-text">
                                        <p class="chat-suggestion-card-title">Emotionel støtte</p>
                                        <p class="chat-suggestion-card-desc">Coaching og mental trivsel</p>
                                    </div>
                                </button>
                            </div>
                        </div>
                        </Transition>

                        <!-- Messages -->
                        <div v-if="hasMessages" class="chat-messages-list">
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
                                        <!-- Streaming: show loading dots until first chunk -->
                                        <div v-if="msg.isStreaming && !msg.content" class="chat-loading-dots" style="padding: 4px 0;">
                                            <div class="chat-loading-dot"></div>
                                            <div class="chat-loading-dot"></div>
                                            <div class="chat-loading-dot"></div>
                                        </div>
                                        <div v-else class="chat-msg-body" v-html="msg.isStreaming ? renderWithCursor(msg.content) : renderMarkdown(msg.content)"></div>

                                        <!-- Document generated by AI -->
                                        <div v-if="msg.document" class="chat-document">
                                            <div class="chat-document-header">
                                                <div class="chat-document-icon">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                    </svg>
                                                </div>
                                                <span class="chat-document-title">{{ msg.document.title }}</span>
                                                <button class="chat-document-copy" @click="copyDocument(msg.document)" title="Kopier">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="chat-document-body">
                                                <pre class="chat-document-content">{{ msg.document.content }}</pre>
                                            </div>
                                        </div>

                                        <!-- Tasks created by AI -->
                                        <div v-if="msg.tasks && msg.tasks.length" class="chat-tasks">
                                            <div class="chat-tasks-header">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                                <span>{{ msg.tasks.length }} {{ msg.tasks.length === 1 ? 'opgave oprettet' : 'opgaver oprettet' }}</span>
                                            </div>
                                            <a v-for="task in msg.tasks" :key="task.id" :href="route('chat.task', { task: task.id })" class="chat-task-item chat-task-item-link">
                                                <div class="chat-task-dot"></div>
                                                <div class="chat-task-info">
                                                    <span class="chat-task-title">{{ task.title }}</span>
                                                    <span v-if="task.due_date" class="chat-task-due">Frist: {{ new Date(task.due_date).toLocaleDateString('da-DK') }}</span>
                                                </div>
                                                <span :class="'chat-task-priority chat-task-priority-' + task.priority">
                                                    {{ {low: 'Lav', medium: 'Normal', high: 'Høj', critical: 'Kritisk'}[task.priority] || task.priority }}
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
                </div>

                <!-- Input Area -->
                <div class="chat-input-area">
                    <div class="chat-input-inner" :style="isLoading ? { pointerEvents: 'none', userSelect: 'none' } : {}">
                        <button v-if="isLoading" @click="stopGenerating" class="chat-stop-btn" style="pointer-events: auto">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 7.5A2.25 2.25 0 0 1 7.5 5.25h9a2.25 2.25 0 0 1 2.25 2.25v9a2.25 2.25 0 0 1-2.25 2.25h-9a2.25 2.25 0 0 1-2.25-2.25v-9Z" />
                            </svg>
                            Stop svar
                        </button>

                        <!-- File preview pill -->
                        <div v-if="uploadPreview" class="chat-file-preview">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="chat-file-preview-icon">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                            </svg>
                            <span class="chat-file-preview-name">{{ uploadPreview.name }}</span>
                            <span class="chat-file-preview-size">{{ formatBytes(uploadPreview.size) }}</span>
                            <button @click="clearUpload" class="chat-file-preview-remove" title="Fjern fil">×</button>
                        </div>

                        <!-- Usage warning -->
                        <div v-if="usagePercent >= 80" class="chat-usage-warning" :class="!canSend ? 'chat-usage-critical' : ''">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="chat-usage-icon">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                            </svg>
                            <template v-if="usagePercent >= 100">
                                <span v-if="$page.props.auth.user?.extra_usage_enabled && ($page.props.auth.user?.wallet_balance ?? 0) > 0">
                                    Du bruger nu din saldo · 0,00015 kr/token · Saldo: {{ Number($page.props.auth.user.wallet_balance).toFixed(2).replace('.', ',') }} kr.
                                </span>
                                <span v-else-if="$page.props.auth.user?.extra_usage_enabled">
                                    Din saldo er tom. <a :href="route('profile.edit', { section: 'usage' })" class="chat-usage-link">Køb mere saldo →</a>
                                </span>
                                <span v-else>
                                    Du har brugt alle dine tokens denne måned. <a :href="route('subscription.plans')" class="chat-usage-link">Opgrader din plan →</a>
                                </span>
                            </template>
                            <span v-else>Du har brugt {{ usagePercent }}% af dit månedlige forbrug. <a :href="route('subscription.plans')" class="chat-usage-link">Opgrader plan →</a></span>
                        </div>

                        <div class="chat-input-container">
                            <!-- Hidden file input -->
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
                                :placeholder="!canSend ? 'Ingen adgang – opgradér plan eller køb saldo...' : uploadPreview ? 'Tilføj en besked til dokumentet (valgfrit)...' : 'Skriv en besked...'"
                                rows="1"
                                class="chat-textarea"
                                :disabled="isLoading || !canSend"
                            ></textarea>
                            <!-- Paperclip button -->
                            <button
                                @click="fileInputRef.click()"
                                :disabled="isLoading || !canSend"
                                class="chat-attach-btn"
                                title="Vedhæft dokument (PDF, billede, TXT)"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                                </svg>
                            </button>
                            <button
                                @click="sendMessage"
                                :disabled="(!message.trim() && !uploadFile) || isLoading || !canSend"
                                class="chat-send-btn"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
                                </svg>
                            </button>
                        </div>
                        <p class="chat-disclaimer">Aura kan lave fejl. Overvej at tjekke vigtig information.</p>
                    </div>
                </div>
            </div>
        </div>
    </ChatLayout>
</template>
