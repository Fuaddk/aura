<script setup>
import { ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';

const mounted = ref(false);

const stats = [
    { value: '3 min', label: 'Gennemsnitlig svartid' },
    { value: '100%', label: 'Fortroligt og sikkert' },
    { value: '24/7', label: 'Tilgængelig' },
];

onMounted(() => {
    setTimeout(() => { mounted.value = true; }, 50);
});
</script>

<template>
    <section class="lp-hero" id="hero">

        <!-- Hero content -->
        <div class="lp-hero-content" :class="{ 'anim-ready': mounted }">

            <div class="lp-hero-badge">
                <span class="lp-badge-pulse"></span>
                AI-drevet juridisk vejledning
            </div>

            <h1 class="lp-hero-title">
                Din trygge guide<br>
                <span class="lp-gradient-text">gennem skilsmissen</span>
            </h1>

            <p class="lp-hero-sub">
                Aura kombinerer avanceret AI med dansk juridisk viden.<br class="lp-br-hide">
                Få svar, hold styr på deadlines og navigér processen trin for trin.
            </p>

            <div class="lp-hero-actions">
                <Link :href="route('register')" class="lp-btn-primary">
                    Opret gratis konto
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                    </svg>
                </Link>
                <Link :href="route('login')" class="lp-btn-ghost">Log ind</Link>
            </div>

            <p class="lp-hero-trust">Gratis at starte &middot; Ingen kreditkort påkrævet &middot; Cancel anytime</p>

            <div class="lp-stats">
                <div v-for="s in stats" :key="s.label" class="lp-stat">
                    <span class="lp-stat-value">{{ s.value }}</span>
                    <span class="lp-stat-label">{{ s.label }}</span>
                </div>
            </div>

        </div>

        <!-- Floating chat demo -->
        <div class="lp-demo-float" :class="{ 'anim-ready': mounted }">
            <div class="lp-demo-glow" aria-hidden="true"></div>
            <div class="chat-demo">
                <div class="chat-demo-header">
                    <div class="chat-demo-status-dot"></div>
                    <span class="chat-demo-header-title">Aura</span>
                    <span class="chat-demo-online">online</span>
                </div>
                <div class="chat-demo-body">
                    <div class="chat-demo-row chat-demo-row-ai msg-appear-1">
                        <div class="chat-demo-avatar">A</div>
                        <div class="chat-demo-bubble chat-demo-bubble-ai">
                            Hej! Jeg er Aura. Hvad kan jeg hjælpe dig med i dag?
                        </div>
                    </div>
                    <div class="chat-demo-row chat-demo-row-user msg-appear-2">
                        <div class="chat-demo-bubble chat-demo-bubble-user">
                            Jeg skal skilles – hvad gør jeg nu?
                        </div>
                    </div>
                    <div class="chat-demo-row chat-demo-row-ai msg-appear-3">
                        <div class="chat-demo-avatar">A</div>
                        <div class="chat-demo-bubble chat-demo-bubble-ai">
                            Jeg hører dig. Første skridt er at kontakte Familieretshuset og søge om separation.
                        </div>
                    </div>
                    <div class="tasks-created-header msg-appear-4">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/></svg>
                        Aura oprettede 3 opgaver til dig
                    </div>
                    <div class="demo-task-card task-appear-1">
                        <div class="demo-task-dot demo-task-dot-high"></div>
                        <div class="demo-task-info">
                            <span class="demo-task-title">Kontakt Familieretshuset</span>
                            <span class="demo-task-due">Frist: om 7 dage</span>
                        </div>
                        <span class="demo-task-badge demo-task-badge-high">Høj</span>
                    </div>
                    <div class="demo-task-card task-appear-2">
                        <div class="demo-task-dot demo-task-dot-high"></div>
                        <div class="demo-task-info">
                            <span class="demo-task-title">Søg om separation online</span>
                            <span class="demo-task-due">Frist: om 14 dage</span>
                        </div>
                        <span class="demo-task-badge demo-task-badge-high">Høj</span>
                    </div>
                    <div class="demo-task-card task-appear-3">
                        <div class="demo-task-dot demo-task-dot-med"></div>
                        <div class="demo-task-info">
                            <span class="demo-task-title">Book møde med advokat</span>
                            <span class="demo-task-due">Frist: om 30 dage</span>
                        </div>
                        <span class="demo-task-badge demo-task-badge-med">Normal</span>
                    </div>
                </div>
                <div class="chat-demo-footer">
                    <span class="chat-demo-prompt">Stil et spørgsmål til Aura…</span>
                    <div class="chat-demo-send-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3.105 2.289a.75.75 0 0 0-.826.95l1.414 4.925A1.5 1.5 0 0 0 5.135 9.25h6.115a.75.75 0 0 1 0 1.5H5.135a1.5 1.5 0 0 0-1.442 1.086l-1.414 4.926a.75.75 0 0 0 .826.95 28.896 28.896 0 0 0 15.293-7.154.75.75 0 0 0 0-1.115A28.897 28.897 0 0 0 3.105 2.289Z"/></svg>
                    </div>
                </div>
            </div>
        </div>

    </section>
</template>
