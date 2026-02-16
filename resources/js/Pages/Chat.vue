<script setup>
import { ref, onMounted, nextTick, computed } from 'vue';
import ChatLayout from '@/Layouts/ChatLayout.vue';
import ChatSidebar from '@/Components/ChatSidebar.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

// Configure marked for clean output
marked.setOptions({ breaks: true, gfm: true });

const renderMarkdown = (text) => {
    if (!text) return '';
    return DOMPurify.sanitize(marked.parse(text));
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
const currentCaseId = ref(props.activeCase?.id || null);
let abortController = null;

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
    abortController = new AbortController();

    try {
        const response = await axios.post(route('chat.send'), {
            message: userMessage,
            case_id: currentCaseId.value,
        }, { signal: abortController.signal });

        messages.value.push({
            role: 'assistant',
            content: response.data.message,
            created_at: new Date().toISOString(),
            tasks: response.data.tasks || [],
            document: response.data.document || null,
        });

        // Update case ID if a new case was created
        if (response.data.case_id && !currentCaseId.value) {
            currentCaseId.value = response.data.case_id;
            // Update URL so reload gets the right activeCase
            window.history.replaceState({}, '', route('dashboard', { case: response.data.case_id }));
        }

        scrollToBottom();

        // Reload sidebar data (cases, tasks, activeCase)
        router.reload({ only: ['cases', 'tasks', 'activeCase'] });
    } catch (error) {
        if (axios.isCancel(error)) {
            messages.value.push({
                role: 'assistant',
                content: 'Svar stoppet.',
                created_at: new Date().toISOString(),
            });
            scrollToBottom();
        } else {
            console.error('Error sending message:', error);
            messages.value.push({
                role: 'assistant',
                content: 'Beklager, der skete en fejl. Prøv venligst igen.',
                created_at: new Date().toISOString(),
            });
            scrollToBottom();
        }
        // Still reload cases in case the case was created before the error
        router.reload({ only: ['cases', 'activeCase'] });
    } finally {
        isLoading.value = false;
        abortController = null;
    }
};

const stopGenerating = () => {
    if (abortController) {
        abortController.abort();
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
    <Head title="Aura - Din AI Assistent" />

    <ChatLayout>
        <div class="chat-container">

            <!-- Left Sidebar -->
            <ChatSidebar
                :active-case="activeCase"
                :cases="cases"
                :open="sidebarOpen"
                @close="sidebarOpen = false"
            />

            <!-- Main Chat Area -->
            <div class="chat-main">

                <!-- Top Bar -->
                <div class="chat-header">
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
                    <img src="/cirkel.png" alt="Aura" class="chat-header-avatar" />
                    <div>
                        <div class="chat-header-title">Aura</div>
                        <div class="chat-header-sub">Din personlige sparringspartner</div>
                    </div>
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
                                    Jeg er Aura — din fortrolige assistent gennem skilsmisseprocessen.<br>
                                    Jeg er her for at lytte, guide og hjælpe dig videre, ét skridt ad gangen.
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

                            <!-- Loading Indicator -->
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
                </div>

                <!-- Input Area -->
                <div class="chat-input-area">
                    <div class="chat-input-inner">
                        <button v-if="isLoading" @click="stopGenerating" class="chat-stop-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 7.5A2.25 2.25 0 0 1 7.5 5.25h9a2.25 2.25 0 0 1 2.25 2.25v9a2.25 2.25 0 0 1-2.25 2.25h-9a2.25 2.25 0 0 1-2.25-2.25v-9Z" />
                            </svg>
                            Stop svar
                        </button>
                        <div class="chat-input-container">
                            <textarea
                                ref="textarea"
                                v-model="message"
                                @input="autoResizeTextarea"
                                @keydown.enter.exact.prevent="sendMessage"
                                placeholder="Skriv en besked..."
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
                        <p class="chat-disclaimer">Aura kan lave fejl. Overvej at tjekke vigtig information.</p>
                    </div>
                </div>
            </div>
        </div>
    </ChatLayout>
</template>
