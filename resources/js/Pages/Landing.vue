<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';

const mounted = ref(false);
const scrollY = ref(0);
const activeFeature = ref(0);

let featureTimer = null;

const features = [
    {
        icon: 'chat',
        title: 'AI-rådgivning døgnet rundt',
        desc: 'Stil spørgsmål om din skilsmisse når som helst. Aura giver konkrete, juridisk funderede svar med empati.',
        color: '#7E75CE',
    },
    {
        icon: 'tasks',
        title: 'Automatiske opgavelister',
        desc: 'Ud fra din samtale opretter Aura personlige opgaver med frister, prioriteter og påmindelser.',
        color: '#5BC4E8',
    },
    {
        icon: 'inbox',
        title: 'Smart indbakke-analyse',
        desc: 'Aura læser og kategoriserer relevante emails automatisk, så du aldrig misser vigtige beskeder.',
        color: '#D9609A',
    },
    {
        icon: 'calendar',
        title: 'Kalender & deadlines',
        desc: 'Hold styr på frister for Familieretshuset, advokater og myndigheder med en samlet kalendervisning.',
        color: '#059669',
    },
    {
        icon: 'docs',
        title: 'Dokumenthåndtering',
        desc: 'Upload, analyser og organiser dine skilsmissedokumenter ét sted. Aura forklarer det juridiske sprog.',
        color: '#f59e0b',
    },
];

const stats = [
    { value: '3 min', label: 'Gennemsnitlig svartid' },
    { value: '100%', label: 'Fortroligt og sikkert' },
    { value: '24/7', label: 'Tilgængelig' },
];

const handleScroll = () => {
    scrollY.value = window.scrollY;
};

const scrollTo = (id) => {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

onMounted(() => {
    setTimeout(() => { mounted.value = true; }, 50);
    window.addEventListener('scroll', handleScroll, { passive: true });
    featureTimer = setInterval(() => {
        activeFeature.value = (activeFeature.value + 1) % features.length;
    }, 3000);
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
    clearInterval(featureTimer);
});
</script>

<template>
    <Head title="Aura – AI-rådgivning til din skilsmisse" />

    <div class="lp-root">

        <!-- ═══ HERO SECTION ═══ -->
        <section class="lp-hero">

            <!-- Animated background -->
            <div class="lp-hero-bg" aria-hidden="true">
                <div class="lp-orb lp-orb-1"></div>
                <div class="lp-orb lp-orb-2"></div>
                <div class="lp-orb lp-orb-3"></div>
                <div class="lp-grid-overlay"></div>
            </div>

            <!-- Nav -->
            <nav class="lp-nav" :class="{ 'lp-nav-scrolled': scrollY > 60 }">
                <div class="lp-nav-inner">
                    <img src="/logo.png" alt="Aura" class="lp-nav-logo" />
                    <div class="lp-nav-menu" :class="{ 'lp-nav-menu-visible': scrollY > 60 }">
                        <button @click="scrollTo('funktioner')" class="lp-nav-anchor">Funktioner</button>
                        <button @click="scrollTo('priser')" class="lp-nav-anchor">Priser</button>
                        <button @click="scrollTo('hvordan')" class="lp-nav-anchor">Sådan virker det</button>
                    </div>
                    <div class="lp-nav-links">
                        <Link :href="route('login')" class="lp-nav-link">Log ind</Link>
                        <Link :href="route('register')" class="lp-nav-cta">Opret konto</Link>
                    </div>
                </div>
            </nav>

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

        <!-- ═══ FEATURES SECTION ═══ -->
        <section class="lp-features-section" id="funktioner">
            <div class="lp-section-inner">

                <div class="lp-section-label">Hvad Aura kan</div>
                <h2 class="lp-section-title">Alt hvad du har brug for<br><span class="lp-gradient-text">samlet ét sted</span></h2>

                <div class="lp-features-layout">

                    <!-- Feature selector (left) -->
                    <div class="lp-feat-list">
                        <button
                            v-for="(f, i) in features"
                            :key="f.title"
                            class="lp-feat-item"
                            :class="{ 'lp-feat-item-active': activeFeature === i }"
                            :style="activeFeature === i ? { '--feat-color': f.color } : {}"
                            @click="activeFeature = i"
                        >
                            <div class="lp-feat-dot" :style="{ background: f.color }"></div>
                            <div class="lp-feat-text">
                                <strong>{{ f.title }}</strong>
                                <span v-if="activeFeature === i">{{ f.desc }}</span>
                            </div>
                            <div v-if="activeFeature === i" class="lp-feat-progress">
                                <div class="lp-feat-progress-bar" :style="{ background: f.color }"></div>
                            </div>
                        </button>
                    </div>

                    <!-- Feature visual (right) -->
                    <div class="lp-feat-visual">
                        <div
                            v-for="(f, i) in features"
                            :key="f.title"
                            class="lp-feat-panel"
                            :class="{ 'lp-feat-panel-active': activeFeature === i }"
                        >
                            <div class="lp-feat-panel-icon" :style="{ background: f.color + '22', borderColor: f.color + '44', color: f.color }">
                                <svg v-if="f.icon === 'chat'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.848 2.771A49.144 49.144 0 0 1 12 2.25c2.43 0 4.817.178 7.152.52 1.978.292 3.348 2.024 3.348 3.97v6.02c0 1.946-1.37 3.678-3.348 3.97a48.901 48.901 0 0 1-3.476.383.39.39 0 0 0-.297.17l-2.755 4.133a.75.75 0 0 1-1.248 0l-2.755-4.133a.39.39 0 0 0-.297-.17 48.9 48.9 0 0 1-3.476-.384c-1.978-.29-3.348-2.024-3.348-3.97V6.741c0-1.946 1.37-3.68 3.348-3.97Z" clip-rule="evenodd" />
                                </svg>
                                <svg v-else-if="f.icon === 'tasks'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                    <path fill-rule="evenodd" d="M7.502 6h7.128A3.375 3.375 0 0 1 18 9.375v9.375a3 3 0 0 0 3-3V6.108c0-1.505-1.125-2.811-2.664-2.94a48.972 48.972 0 0 0-.673-.05A3 3 0 0 0 15 1.5h-1.5a3 3 0 0 0-2.663 1.618c-.225.015-.45.032-.673.05C8.662 3.295 7.554 4.542 7.502 6ZM13.5 3A1.5 1.5 0 0 0 12 4.5h4.5A1.5 1.5 0 0 0 15 3h-1.5Z" clip-rule="evenodd" />
                                    <path fill-rule="evenodd" d="M3 9.375C3 8.339 3.84 7.5 4.875 7.5h9.75c1.036 0 1.875.84 1.875 1.875v11.25c0 1.035-.84 1.875-1.875 1.875h-9.75A1.875 1.875 0 0 1 3 20.625V9.375Zm9.586 4.594a.75.75 0 0 0-1.172-.938l-2.476 3.096-.908-.907a.75.75 0 0 0-1.06 1.06l1.5 1.5a.75.75 0 0 0 1.116-.062l3-3.75Z" clip-rule="evenodd" />
                                </svg>
                                <svg v-else-if="f.icon === 'inbox'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.478 5.559A1.5 1.5 0 0 1 6.912 4.5H9A.75.75 0 0 0 9 3H6.912a3 3 0 0 0-2.868 2.118l-2.411 7.838a3 3 0 0 0-.133.882V18a3 3 0 0 0 3 3h15a3 3 0 0 0 3-3v-4.162c0-.299-.045-.596-.133-.882l-2.412-7.838A3 3 0 0 0 17.088 3H15a.75.75 0 0 0 0 1.5h2.088a1.5 1.5 0 0 1 1.434 1.059l2.213 7.191H17.89a3 3 0 0 0-2.684 1.658l-.256.513a1.5 1.5 0 0 1-1.342.829h-3.218a1.5 1.5 0 0 1-1.342-.83l-.256-.512a3 3 0 0 0-2.684-1.658H3.265l2.213-7.191Z" clip-rule="evenodd" />
                                </svg>
                                <svg v-else-if="f.icon === 'calendar'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12.75 12.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM7.5 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM8.25 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM9.75 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM10.5 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM12.75 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM14.25 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM15 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM16.5 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM15 12.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM16.5 13.5a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" />
                                    <path fill-rule="evenodd" d="M6.75 2.25A.75.75 0 0 1 7.5 3v1.5h9V3A.75.75 0 0 1 18 3v1.5h.75a3 3 0 0 1 3 3v11.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V7.5a3 3 0 0 1 3-3H6V3a.75.75 0 0 1 .75-.75Zm13.5 9a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5v7.5a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5v-7.5Z" clip-rule="evenodd" />
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.625 1.5c-1.036 0-1.875.84-1.875 1.875v17.25c0 1.035.84 1.875 1.875 1.875h12.75c1.035 0 1.875-.84 1.875-1.875V12.75A3.75 3.75 0 0 0 16.5 9h-1.875a1.875 1.875 0 0 1-1.875-1.875V5.25A3.75 3.75 0 0 0 9 1.5H5.625ZM7.5 15a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5A.75.75 0 0 1 7.5 15Zm.75 2.25a.75.75 0 0 0 0 1.5H12a.75.75 0 0 0 0-1.5H8.25Z" clip-rule="evenodd" />
                                    <path d="M12.971 1.816A5.23 5.23 0 0 1 14.25 5.25v1.875c0 .207.168.375.375.375H16.5a5.23 5.23 0 0 1 3.434 1.279 9.768 9.768 0 0 0-6.963-6.963Z" />
                                </svg>
                            </div>
                            <h3 class="lp-feat-panel-title">{{ f.title }}</h3>
                            <p class="lp-feat-panel-desc">{{ f.desc }}</p>

                            <div class="lp-feat-lines" aria-hidden="true">
                                <div class="lp-feat-line" v-for="n in 4" :key="n" :style="{ width: (60 + n * 10) + '%', opacity: 0.05 + n * 0.03, animationDelay: n * 0.15 + 's' }"></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ═══ PRICING SECTION ═══ -->
        <section class="lp-pricing" id="priser">
            <div class="lp-section-inner">

                <div class="lp-section-label">Prisplaner</div>
                <h2 class="lp-section-title">Vælg den plan<br><span class="lp-gradient-text">der passer til dig</span></h2>

                <div class="lp-plans">

                    <!-- Gratis -->
                    <div class="lp-plan">
                        <div class="lp-plan-header">
                            <h3 class="lp-plan-name">Gratis</h3>
                            <div class="lp-plan-price">
                                <span class="lp-plan-amount">0</span>
                                <span class="lp-plan-period">kr/md</span>
                            </div>
                            <p class="lp-plan-desc">Kom i gang med grundlæggende rådgivning</p>
                        </div>
                        <ul class="lp-plan-features">
                            <li>Grundlæggende opgavestyring</li>
                            <li>Dokumentopbevaring</li>
                            <li class="lp-plan-feat-no">Kalenderintegration</li>
                            <li class="lp-plan-feat-no">E-mail indbakke</li>
                        </ul>
                        <Link :href="route('register')" class="lp-plan-cta lp-plan-cta-ghost">
                            Opret gratis konto
                        </Link>
                    </div>

                    <!-- Basis (mest populær) -->
                    <div class="lp-plan lp-plan-popular">
                        <div class="lp-plan-popular-badge">Mest populær</div>
                        <div class="lp-plan-header">
                            <h3 class="lp-plan-name">Basis</h3>
                            <div class="lp-plan-price">
                                <span class="lp-plan-amount">99</span>
                                <span class="lp-plan-period">kr/md</span>
                            </div>
                            <p class="lp-plan-desc">Alt hvad du behøver!</p>
                        </div>
                        <ul class="lp-plan-features">
                            <li>Adgang til Aura-ML-o2</li>
                            <li>Ubegrænset sager</li>
                            <li>Avancerede opgaver</li>
                            <li>Dokumentupload</li>
                            <li>Kalenderintegration</li>
                            <li class="lp-plan-feat-no">E-mail indbakke</li>
                        </ul>
                        <Link :href="route('register')" class="lp-plan-cta lp-plan-cta-primary">
                            Kom i gang
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                            </svg>
                        </Link>
                    </div>

                    <!-- Pro -->
                    <div class="lp-plan">
                        <div class="lp-plan-header">
                            <h3 class="lp-plan-name">Pro</h3>
                            <div class="lp-plan-price">
                                <span class="lp-plan-amount">199</span>
                                <span class="lp-plan-period">kr/md</span>
                            </div>
                            <p class="lp-plan-desc">Alt hvad du behøver, og mere til!</p>
                        </div>
                        <ul class="lp-plan-features">
                            <li>Adgang til Aura-ML-o2</li>
                            <li>Ubegrænset sager</li>
                            <li>Avancerede opgaver</li>
                            <li>Dokumentupload</li>
                            <li>Kalenderintegration</li>
                            <li>E-mail indbakke</li>
                        </ul>
                        <Link :href="route('register')" class="lp-plan-cta lp-plan-cta-ghost">
                            Vælg Pro
                        </Link>
                    </div>

                </div>
            </div>
        </section>

        <!-- ═══ HOW IT WORKS ═══ -->
        <section class="lp-how" id="hvordan">
            <div class="lp-section-inner">
                <div class="lp-section-label">Sådan virker det</div>
                <h2 class="lp-section-title">Tre simple skridt<br><span class="lp-gradient-text">til overblik</span></h2>

                <div class="lp-steps">
                    <div class="lp-step">
                        <div class="lp-step-num">1</div>
                        <h3>Opret din konto</h3>
                        <p>Gratis på under et minut. Ingen kreditkort, ingen binding.</p>
                    </div>
                    <div class="lp-step">
                        <div class="lp-step-num">2</div>
                        <h3>Beskriv din situation</h3>
                        <p>Fortæl Aura om din situation i dit eget tempo. Aura stiller opklarende spørgsmål.</p>
                    </div>
                    <div class="lp-step">
                        <div class="lp-step-num">3</div>
                        <h3>Få din personlige plan</h3>
                        <p>Aura opretter en konkret handlingsplan med opgaver, frister og juridisk vejledning.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ FOOTER ═══ -->
        <footer class="lp-footer">
            <div class="lp-footer-inner">

                <!-- Col 1: Brand -->
                <div class="lp-footer-brand">
                    <img src="/logo.png" alt="Aura" class="lp-footer-logo" />
                    <p class="lp-footer-tagline">
                        AI-rådgivning til dig der<br>skal igennem en skilsmisse.
                    </p>
                    <div class="lp-footer-socials">
                        <a href="https://www.instagram.com/hiaura.dk" target="_blank" rel="noopener" class="lp-social-btn" aria-label="Instagram">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                            </svg>
                        </a>
                        <a href="https://www.linkedin.com" target="_blank" rel="noopener" class="lp-social-btn" aria-label="LinkedIn">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Col 2: Produkt -->
                <div class="lp-footer-col">
                    <h4 class="lp-footer-col-title">Produkt</h4>
                    <ul class="lp-footer-links">
                        <li><Link :href="route('register')" class="lp-footer-link">Kom i gang gratis</Link></li>
                        <li><Link :href="route('login')" class="lp-footer-link">Log ind</Link></li>
                        <li><Link :href="route('subscription.plans')" class="lp-footer-link">Priser</Link></li>
                    </ul>
                </div>

                <!-- Col 3: Funktioner -->
                <div class="lp-footer-col">
                    <h4 class="lp-footer-col-title">Funktioner</h4>
                    <ul class="lp-footer-links">
                        <li><span class="lp-footer-link-plain">AI-rådgivning</span></li>
                        <li><span class="lp-footer-link-plain">Automatiske opgaver</span></li>
                        <li><span class="lp-footer-link-plain">Smart indbakke</span></li>
                        <li><span class="lp-footer-link-plain">Kalender & frister</span></li>
                        <li><span class="lp-footer-link-plain">Dokumenthåndtering</span></li>
                    </ul>
                </div>

                <!-- Col 4: Kontakt -->
                <div class="lp-footer-col">
                    <h4 class="lp-footer-col-title">Kontakt</h4>
                    <ul class="lp-footer-links">
                        <li>
                            <a href="mailto:support@hiaura.dk" class="lp-footer-link">
                                support@hiaura.dk
                            </a>
                        </li>
                        <li><span class="lp-footer-link-plain">Aura ApS</span></li>
                        <li><span class="lp-footer-link-plain">Danmark</span></li>
                    </ul>
                    <div class="lp-footer-hours">
                        <p class="lp-footer-hours-title">Support</p>
                        <p>Man–Fre: 09:00–17:00</p>
                        <p>Weekend: Lukket</p>
                    </div>
                </div>

            </div>

            <div class="lp-footer-bottom">
                <p class="lp-footer-copy">&copy; {{ new Date().getFullYear() }} Aura ApS. Alle rettigheder forbeholdes.</p>
                <div class="lp-footer-legal">
                    <Link :href="route('login')" class="lp-footer-legal-link">Privatlivspolitik</Link>
                    <span class="lp-footer-legal-dot">·</span>
                    <Link :href="route('login')" class="lp-footer-legal-link">Vilkår og betingelser</Link>
                    <span class="lp-footer-legal-dot">·</span>
                    <Link :href="route('login')" class="lp-footer-legal-link">Cookies</Link>
                </div>
            </div>
        </footer>

    </div>
</template>

<style scoped>
/* ─── Reset / Base ─────────────────────────────────────── */
.lp-root {
    font-family: 'Figtree', ui-sans-serif, system-ui, sans-serif;
    color: #fff;
    background: linear-gradient(145deg, #1a3a5c 0%, #2d2060 35%, #4a1a4a 70%, #3d1030 100%);
    background-attachment: fixed;
    overflow-x: hidden;
    min-height: 100vh;
}

/* ─── Shared gradient text ─────────────────────────────── */
.lp-gradient-text {
    background: linear-gradient(135deg, #5BC4E8 0%, #7E75CE 50%, #D9609A 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* ─── NAV ───────────────────────────────────────────────── */
.lp-nav {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 100;
    transition: all 0.3s ease;
}

.lp-nav-scrolled {
    top: 0.75rem;
    padding: 0 1rem;
}

.lp-nav-inner {
    max-width: 88rem;
    margin: 0 auto;
    padding: 1.125rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    transition: all 0.3s ease;
    border-radius: 0;
}

.lp-nav-scrolled .lp-nav-inner {
    background: rgba(20, 10, 40, 0.92);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 1.25rem;
    padding: 0.75rem 1.5rem;
    box-shadow: 0 4px 24px rgba(0,0,0,0.3), 0 1px 2px rgba(0,0,0,0.2);
}

.lp-nav-logo {
    height: 1.875rem;
    width: auto;
    filter: brightness(0) invert(1) opacity(0.9);
    transition: filter 0.3s;
    flex-shrink: 0;
}

.lp-nav-scrolled .lp-nav-logo {
    filter: brightness(0) invert(1) opacity(0.85);
}

/* Anchor menu */
.lp-nav-menu {
    display: none;
    align-items: center;
    gap: 0.25rem;
    opacity: 0;
    transform: translateY(-4px);
    transition: opacity 0.25s ease, transform 0.25s ease;
}

@media (min-width: 768px) {
    .lp-nav-menu { display: flex; }
}

.lp-nav-menu-visible {
    opacity: 1;
    transform: translateY(0);
}

.lp-nav-anchor {
    font-size: 0.875rem;
    font-weight: 500;
    color: rgba(255,255,255,0.7);
    background: none;
    border: none;
    padding: 0.4rem 0.75rem;
    border-radius: 0.625rem;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
}

.lp-nav-anchor:hover { background: rgba(255,255,255,0.1); color: #fff; }

.lp-nav-links {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
}

.lp-nav-link {
    font-size: 0.875rem;
    font-weight: 500;
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    padding: 0.4rem 0.75rem;
    border-radius: 0.625rem;
    transition: all 0.15s;
}

.lp-nav-link:hover { color: #fff; background: rgba(255,255,255,0.1); }

.lp-nav-cta {
    font-size: 0.875rem;
    font-weight: 600;
    color: #fff;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.3);
    padding: 0.5rem 1.125rem;
    border-radius: 9999px;
    text-decoration: none;
    transition: all 0.15s;
    white-space: nowrap;
}

.lp-nav-scrolled .lp-nav-cta {
    background: linear-gradient(135deg, #5BC4E8, #7E75CE, #D9609A);
    border-color: transparent;
    box-shadow: 0 2px 10px rgba(126,117,206,0.35);
}

.lp-nav-cta:hover {
    background: rgba(255,255,255,0.25);
    border-color: rgba(255,255,255,0.45);
}

.lp-nav-scrolled .lp-nav-cta:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(126,117,206,0.5);
}

/* ─── HERO ─────────────────────────────────────────────── */
.lp-hero {
    position: relative;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 7rem 2rem 5rem;
    overflow: hidden;
}

.lp-hero-bg {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.lp-orb {
    position: absolute;
    border-radius: 9999px;
    filter: blur(80px);
}

.lp-orb-1 {
    width: 40rem;
    height: 40rem;
    background: rgba(91,196,232,0.18);
    top: -10rem;
    right: -8rem;
    animation: orb-drift 12s ease-in-out infinite;
}

.lp-orb-2 {
    width: 30rem;
    height: 30rem;
    background: rgba(217,96,154,0.15);
    bottom: -5rem;
    left: -6rem;
    animation: orb-drift 15s ease-in-out infinite reverse;
}

.lp-orb-3 {
    width: 20rem;
    height: 20rem;
    background: rgba(126,117,206,0.2);
    top: 40%;
    left: 30%;
    animation: orb-drift 10s ease-in-out infinite 2s;
}

.lp-grid-overlay {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
    background-size: 4rem 4rem;
}

@keyframes orb-drift {
    0%, 100% { transform: translate(0,0) scale(1); }
    33%       { transform: translate(30px,-20px) scale(1.08); }
    66%       { transform: translate(-15px,15px) scale(0.93); }
}

.lp-hero {
    display: grid;
    grid-template-columns: 1fr;
    gap: 3rem;
    align-items: center;
}

@media (min-width: 1024px) {
    .lp-hero {
        grid-template-columns: 1fr 1fr;
        padding: 7rem 4rem 5rem;
    }
}

.lp-hero-content {
    position: relative;
    z-index: 10;
    opacity: 0;
    transform: translateX(-24px);
    transition: opacity 0.7s ease, transform 0.7s ease;
}

.lp-hero-content.anim-ready {
    opacity: 1;
    transform: translateX(0);
}

.lp-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    color: rgba(255,255,255,0.85);
    font-size: 0.8125rem;
    font-weight: 600;
    padding: 0.35rem 0.875rem;
    border-radius: 9999px;
    margin-bottom: 1.75rem;
    backdrop-filter: blur(8px);
    letter-spacing: 0.02em;
}

.lp-badge-pulse {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 9999px;
    background: #4ade80;
    box-shadow: 0 0 8px rgba(74,222,128,0.7);
    animation: badge-pulse 2s ease-in-out infinite;
}

@keyframes badge-pulse {
    0%, 100% { opacity: 1; transform: scale(1); box-shadow: 0 0 8px rgba(74,222,128,0.7); }
    50%       { opacity: 0.7; transform: scale(0.8); box-shadow: 0 0 4px rgba(74,222,128,0.4); }
}

.lp-hero-title {
    font-size: clamp(2.25rem, 5vw, 3.5rem);
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.03em;
    line-height: 1.12;
    margin: 0 0 1.25rem;
}

.lp-hero-sub {
    font-size: 1.0625rem;
    color: rgba(255,255,255,0.62);
    line-height: 1.75;
    margin: 0 0 2.25rem;
    max-width: 32rem;
}

.lp-br-hide { display: none; }
@media (min-width: 768px) { .lp-br-hide { display: inline; } }

.lp-hero-actions {
    display: flex;
    gap: 0.875rem;
    flex-wrap: wrap;
    margin-bottom: 1.25rem;
}

.lp-btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.9375rem 1.75rem;
    background: linear-gradient(135deg, #5BC4E8 0%, #7E75CE 50%, #D9609A 100%);
    color: #fff;
    font-size: 0.9375rem;
    font-weight: 700;
    border-radius: 0.875rem;
    text-decoration: none;
    transition: all 0.2s ease;
    box-shadow: 0 4px 20px rgba(126,117,206,0.45);
    letter-spacing: 0.01em;
    position: relative;
    overflow: hidden;
}

.lp-btn-primary::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.15), transparent);
    opacity: 0;
    transition: opacity 0.2s;
}

.lp-btn-primary:hover::before { opacity: 1; }
.lp-btn-primary:hover { box-shadow: 0 6px 28px rgba(126,117,206,0.6); transform: translateY(-2px); }
.lp-btn-primary svg { width: 1rem; height: 1rem; transition: transform 0.2s; }
.lp-btn-primary:hover svg { transform: translateX(3px); }

.lp-btn-ghost {
    display: inline-flex;
    align-items: center;
    padding: 0.9375rem 1.5rem;
    background: rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.85);
    font-size: 0.9375rem;
    font-weight: 500;
    border: 1.5px solid rgba(255,255,255,0.2);
    border-radius: 0.875rem;
    text-decoration: none;
    transition: all 0.15s ease;
    backdrop-filter: blur(4px);
}

.lp-btn-ghost:hover {
    background: rgba(255,255,255,0.14);
    border-color: rgba(255,255,255,0.35);
    transform: translateY(-1px);
}

.lp-hero-trust {
    font-size: 0.8125rem;
    color: rgba(255,255,255,0.35);
    margin: 0 0 2.5rem;
}

.lp-stats {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
}

.lp-stat { display: flex; flex-direction: column; gap: 0.2rem; }

.lp-stat-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.02em;
}

.lp-stat-label {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.4);
    letter-spacing: 0.02em;
}

/* ── Floating chat demo ──────────────────────────────────── */
.lp-demo-float {
    position: relative;
    z-index: 10;
    opacity: 0;
    transform: translateX(24px) translateY(8px);
    transition: opacity 0.8s ease 0.2s, transform 0.8s ease 0.2s;
}

.lp-demo-float.anim-ready {
    opacity: 1;
    transform: translateX(0) translateY(0);
    animation: float-bob 6s ease-in-out infinite 1s;
}

@keyframes float-bob {
    0%, 100% { transform: translateY(0); }
    50%       { transform: translateY(-10px); }
}

.lp-demo-glow {
    position: absolute;
    inset: -3rem;
    background: radial-gradient(ellipse at center, rgba(126,117,206,0.25) 0%, transparent 70%);
    pointer-events: none;
    border-radius: 9999px;
}

.chat-demo {
    background: rgba(255,255,255,0.07);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.13);
    border-radius: 1.25rem;
    overflow: hidden;
    box-shadow: 0 24px 64px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.08);
    display: flex;
    flex-direction: column;
    max-height: 32rem;
}

.chat-demo-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 1.125rem;
    background: rgba(255,255,255,0.05);
    border-bottom: 1px solid rgba(255,255,255,0.07);
    flex-shrink: 0;
}

.chat-demo-status-dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 9999px;
    background: #4ade80;
    box-shadow: 0 0 6px rgba(74,222,128,0.6);
    flex-shrink: 0;
    animation: status-pulse 2s ease-in-out infinite;
}

@keyframes status-pulse {
    0%, 100% { box-shadow: 0 0 6px rgba(74,222,128,0.6); }
    50%       { box-shadow: 0 0 12px rgba(74,222,128,0.9); }
}

.chat-demo-header-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: rgba(255,255,255,0.92);
    flex: 1;
}

.chat-demo-online { font-size: 0.6875rem; color: rgba(255,255,255,0.38); }

.chat-demo-body {
    flex: 1;
    padding: 1.125rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    overflow: hidden;
}

.chat-demo-row { display: flex; align-items: flex-end; gap: 0.5rem; }
.chat-demo-row-user { flex-direction: row-reverse; }

.chat-demo-avatar {
    width: 1.875rem;
    height: 1.875rem;
    border-radius: 9999px;
    background: linear-gradient(135deg, #7E75CE, #5BC4E8);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6875rem;
    font-weight: 800;
    color: #fff;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(126,117,206,0.4);
}

.chat-demo-bubble {
    max-width: 78%;
    padding: 0.625rem 0.9375rem;
    border-radius: 1rem;
    font-size: 0.8125rem;
    line-height: 1.55;
}

.chat-demo-bubble-ai {
    background: rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.88);
    border-bottom-left-radius: 0.25rem;
    border: 1px solid rgba(255,255,255,0.07);
}

.chat-demo-bubble-user {
    background: linear-gradient(135deg, #7E75CE, #5BC4E8);
    color: #fff;
    border-bottom-right-radius: 0.25rem;
    box-shadow: 0 2px 12px rgba(126,117,206,0.4);
}

.chat-demo-footer {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: rgba(255,255,255,0.04);
    border-top: 1px solid rgba(255,255,255,0.07);
    flex-shrink: 0;
}

.chat-demo-prompt {
    flex: 1;
    font-size: 0.8125rem;
    color: rgba(255,255,255,0.28);
    font-style: italic;
}

.chat-demo-send-btn {
    width: 2rem;
    height: 2rem;
    border-radius: 0.5rem;
    background: linear-gradient(135deg, #7E75CE, #5BC4E8);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(126,117,206,0.4);
}

.chat-demo-send-btn svg { width: 0.9375rem; height: 0.9375rem; color: #fff; }

.msg-appear-1 { animation: msg-fade-in 0.45s ease both 0.5s; }
.msg-appear-2 { animation: msg-fade-in 0.45s ease both 1.4s; }
.msg-appear-3 { animation: msg-fade-in 0.45s ease both 2.5s; }
.msg-appear-4 { animation: msg-fade-in 0.4s ease both 3.7s; }
.task-appear-1 { animation: task-slide-in 0.4s cubic-bezier(0.34,1.56,0.64,1) both 4.3s; }
.task-appear-2 { animation: task-slide-in 0.4s cubic-bezier(0.34,1.56,0.64,1) both 4.85s; }
.task-appear-3 { animation: task-slide-in 0.4s cubic-bezier(0.34,1.56,0.64,1) both 5.4s; }

@keyframes msg-fade-in {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}

@keyframes task-slide-in {
    from { opacity: 0; transform: translateX(-10px) scale(0.97); }
    to   { opacity: 1; transform: translateX(0) scale(1); }
}

.tasks-created-header {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: rgba(255,255,255,0.5);
    letter-spacing: 0.03em;
}

.tasks-created-header svg { width: 0.875rem; height: 0.875rem; color: #4ade80; flex-shrink: 0; }

.demo-task-card {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.625rem;
    padding: 0.5rem 0.75rem;
    cursor: default;
}

.demo-task-dot { width: 0.5rem; height: 0.5rem; border-radius: 9999px; flex-shrink: 0; }
.demo-task-dot-high { background: #f87171; box-shadow: 0 0 5px rgba(248,113,113,0.5); }
.demo-task-dot-med  { background: #fbbf24; box-shadow: 0 0 5px rgba(251,191,36,0.5); }

.demo-task-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.demo-task-title {
    font-size: 0.75rem;
    font-weight: 600;
    color: rgba(255,255,255,0.88);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.demo-task-due { font-size: 0.625rem; color: rgba(255,255,255,0.38); }

.demo-task-badge {
    font-size: 0.5625rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.1875rem 0.4375rem;
    border-radius: 9999px;
    flex-shrink: 0;
}

.demo-task-badge-high { background: rgba(248,113,113,0.18); color: #fca5a5; border: 1px solid rgba(248,113,113,0.25); }
.demo-task-badge-med  { background: rgba(251,191,36,0.15); color: #fcd34d; border: 1px solid rgba(251,191,36,0.2); }

/* ─── SHARED SECTION STYLES ─────────────────────────────── */
.lp-section-inner {
    max-width: 88rem;
    margin: 0 auto;
}

.lp-section-label {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #a78bfa;
    margin-bottom: 0.75rem;
}

.lp-section-title {
    font-size: clamp(1.75rem, 4vw, 2.5rem);
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.03em;
    line-height: 1.2;
    margin: 0 0 3rem;
}

/* ─── FEATURES SECTION ─────────────────────────────────── */
.lp-features-section {
    padding: 0.5rem 0.5rem 0.25rem;
}

.lp-features-section > .lp-section-inner {
    background:
        linear-gradient(rgba(255,255,255,0.055), rgba(255,255,255,0.055)) padding-box,
        linear-gradient(135deg, #5BC4E8, #7E75CE, #D9609A) border-box;
    border: 1.5px solid transparent;
    border-radius: 2rem;
    padding: 3rem 2.5rem;
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    box-shadow: 0 8px 40px rgba(0,0,0,0.25);
    box-sizing: border-box;
}

/* Features layout */
.lp-features-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
}

@media (min-width: 768px) {
    .lp-features-layout {
        grid-template-columns: 1fr 1fr;
        gap: 3rem;
        align-items: start;
    }
}

.lp-feat-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.lp-feat-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 1.125rem;
    background: transparent;
    border: 1.5px solid rgba(255,255,255,0.07);
    border-radius: 1.125rem;
    cursor: pointer;
    text-align: left;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.lp-feat-item:hover {
    background: rgba(255,255,255,0.07);
    border-color: rgba(255,255,255,0.15);
}

.lp-feat-item-active {
    background: rgba(255,255,255,0.1);
    border-color: rgba(255,255,255,0.18);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.lp-feat-dot {
    width: 0.625rem;
    height: 0.625rem;
    border-radius: 9999px;
    flex-shrink: 0;
    margin-top: 0.25rem;
    transition: box-shadow 0.2s;
}

.lp-feat-item-active .lp-feat-dot {
    box-shadow: 0 0 8px var(--feat-color, #7E75CE);
}

.lp-feat-text { flex: 1; min-width: 0; }

.lp-feat-text strong {
    display: block;
    font-size: 0.9375rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 0.25rem;
}

.lp-feat-text span {
    display: block;
    font-size: 0.875rem;
    color: rgba(255,255,255,0.6);
    line-height: 1.6;
    animation: feat-desc-in 0.25s ease both;
}

@keyframes feat-desc-in {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: translateY(0); }
}

.lp-feat-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: rgba(255,255,255,0.06);
    overflow: hidden;
}

.lp-feat-progress-bar {
    height: 100%;
    animation: feat-progress 3s linear both;
    transform-origin: left;
}

@keyframes feat-progress {
    from { transform: scaleX(0); }
    to   { transform: scaleX(1); }
}

.lp-feat-visual {
    position: relative;
    min-height: 16rem;
}

.lp-feat-panel {
    position: absolute;
    inset: 0;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 1.5rem;
    padding: 2rem;
    box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    opacity: 0;
    transform: scale(0.97) translateY(10px);
    transition: opacity 0.3s ease, transform 0.3s ease;
    pointer-events: none;
    overflow: hidden;
    backdrop-filter: blur(8px);
}

.lp-feat-panel-active {
    opacity: 1;
    transform: scale(1) translateY(0);
    pointer-events: auto;
}

.lp-feat-panel-icon {
    width: 3.5rem;
    height: 3.5rem;
    border-radius: 1rem;
    border: 1.5px solid;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.25rem;
}

.lp-feat-panel-icon svg { width: 1.625rem; height: 1.625rem; }

.lp-feat-panel-title {
    font-size: 1.25rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.02em;
    margin: 0 0 0.625rem;
}

.lp-feat-panel-desc {
    font-size: 0.9375rem;
    color: rgba(255,255,255,0.62);
    line-height: 1.7;
    margin: 0;
}

.lp-feat-lines {
    position: absolute;
    bottom: 1.5rem;
    left: 2rem;
    right: 2rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.lp-feat-line {
    height: 0.375rem;
    background: linear-gradient(90deg, rgba(255,255,255,0.15), transparent);
    border-radius: 9999px;
    animation: shimmer-line 2s ease-in-out infinite alternate;
}

@keyframes shimmer-line {
    from { opacity: 0.05; }
    to   { opacity: 0.18; }
}

/* ─── PRICING SECTION ──────────────────────────────────── */
.lp-pricing {
    padding: 0.25rem 0.5rem;
}

.lp-pricing > .lp-section-inner {
    background:
        linear-gradient(rgba(255,255,255,0.05), rgba(255,255,255,0.05)) padding-box,
        linear-gradient(135deg, #7E75CE, #5BC4E8, #D9609A) border-box;
    border: 1.5px solid transparent;
    border-radius: 2rem;
    padding: 3rem 2.5rem;
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    box-shadow: 0 8px 40px rgba(0,0,0,0.25);
    box-sizing: border-box;
}

.lp-plans {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}

@media (min-width: 768px) {
    .lp-plans {
        grid-template-columns: repeat(3, 1fr);
        align-items: stretch;
    }
}

.lp-plan {
    position: relative;
    background: rgba(255,255,255,0.06);
    border: 1.5px solid rgba(255,255,255,0.1);
    border-radius: 1.75rem;
    padding: 2rem;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.lp-plan:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 48px rgba(0,0,0,0.3);
    border-color: rgba(255,255,255,0.2);
}

.lp-plan-popular {
    background:
        linear-gradient(rgba(126,117,206,0.18), rgba(126,117,206,0.18)) padding-box,
        linear-gradient(135deg, #5BC4E8, #7E75CE, #D9609A) border-box;
    border: 2px solid transparent;
    box-shadow: 0 8px 32px rgba(126,117,206,0.25);
}

.lp-plan-popular-badge {
    position: absolute;
    top: -0.8125rem;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #5BC4E8, #7E75CE, #D9609A);
    color: #fff;
    font-size: 0.6875rem;
    font-weight: 700;
    padding: 0.3rem 1rem;
    border-radius: 9999px;
    white-space: nowrap;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    box-shadow: 0 4px 14px rgba(126,117,206,0.5);
}

.lp-plan-header {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.lp-plan-name {
    font-size: 1.125rem;
    font-weight: 800;
    color: #fff;
    margin: 0;
    letter-spacing: -0.01em;
}

.lp-plan-price {
    display: flex;
    align-items: baseline;
    gap: 0.375rem;
}

.lp-plan-amount {
    font-size: 2.75rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.04em;
    line-height: 1;
}

.lp-plan-period {
    font-size: 0.875rem;
    color: rgba(255,255,255,0.45);
    font-weight: 500;
}

.lp-plan-desc {
    font-size: 0.875rem;
    color: rgba(255,255,255,0.5);
    margin: 0;
    line-height: 1.5;
}

.lp-plan-features {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    flex: 1;
}

.lp-plan-features li {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    font-size: 0.875rem;
    color: rgba(255,255,255,0.8);
}

.lp-plan-features li::before {
    content: '✓';
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.25rem;
    min-width: 1.25rem;
    height: 1.25rem;
    background: rgba(91,196,232,0.15);
    border: 1px solid rgba(91,196,232,0.35);
    border-radius: 9999px;
    font-size: 0.625rem;
    font-weight: 900;
    color: #5BC4E8;
    flex-shrink: 0;
}

.lp-plan-feat-no {
    color: rgba(255,255,255,0.28) !important;
}

.lp-plan-feat-no::before {
    content: '–' !important;
    background: rgba(255,255,255,0.05) !important;
    border-color: rgba(255,255,255,0.08) !important;
    color: rgba(255,255,255,0.22) !important;
    font-size: 0.75rem !important;
}

.lp-plan-cta {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.9375rem 1.5rem;
    border-radius: 0.875rem;
    font-size: 0.9375rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
    text-align: center;
    border: none;
    cursor: pointer;
}

.lp-plan-cta svg { width: 1rem; height: 1rem; transition: transform 0.2s; }
.lp-plan-cta:hover svg { transform: translateX(3px); }

.lp-plan-cta-primary {
    background: linear-gradient(135deg, #5BC4E8 0%, #7E75CE 50%, #D9609A 100%);
    color: #fff;
    box-shadow: 0 4px 20px rgba(126,117,206,0.45);
}

.lp-plan-cta-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 28px rgba(126,117,206,0.65);
}

.lp-plan-cta-ghost {
    background: rgba(255,255,255,0.07);
    color: rgba(255,255,255,0.8);
    border: 1.5px solid rgba(255,255,255,0.15) !important;
}

.lp-plan-cta-ghost:hover {
    background: rgba(255,255,255,0.12);
    border-color: rgba(255,255,255,0.28) !important;
    color: #fff;
}

/* ─── HOW IT WORKS ─────────────────────────────────────── */
.lp-how {
    padding: 0.25rem 0.5rem 0.5rem;
}

.lp-how > .lp-section-inner {
    background:
        linear-gradient(rgba(255,255,255,0.055), rgba(255,255,255,0.055)) padding-box,
        linear-gradient(135deg, #D9609A, #7E75CE, #5BC4E8) border-box;
    border: 1.5px solid transparent;
    border-radius: 2rem;
    padding: 3rem 2.5rem;
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    box-shadow: 0 8px 40px rgba(0,0,0,0.25);
    box-sizing: border-box;
}

.lp-steps {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
}

@media (min-width: 768px) {
    .lp-steps {
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
    }
}

.lp-step {
    position: relative;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 1.5rem;
    padding: 2rem;
    transition: box-shadow 0.2s, transform 0.2s, border-color 0.2s;
}

.lp-step:hover {
    box-shadow: 0 12px 36px rgba(0,0,0,0.25);
    border-color: rgba(255,255,255,0.2);
    transform: translateY(-4px);
}

.lp-step-num {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.75rem;
    background: linear-gradient(135deg, #5BC4E8, #7E75CE, #D9609A);
    color: #fff;
    font-size: 1rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    box-shadow: 0 4px 14px rgba(126,117,206,0.4);
}

.lp-step h3 {
    font-size: 1.0625rem;
    font-weight: 700;
    color: #fff;
    margin: 0 0 0.5rem;
    letter-spacing: -0.01em;
}

.lp-step p {
    font-size: 0.9375rem;
    color: rgba(255,255,255,0.58);
    line-height: 1.65;
    margin: 0;
}

/* ─── FOOTER ───────────────────────────────────────────── */
.lp-footer {
    background: rgba(5, 3, 15, 0.85);
    border-top: 1px solid rgba(255,255,255,0.07);
    margin-top: 0.5rem;
}

.lp-footer-inner {
    max-width: 88rem;
    margin: 0 auto;
    padding: 4rem 2rem 3rem;
    display: grid;
    grid-template-columns: 1fr;
    gap: 2.5rem;
}

@media (min-width: 640px) {
    .lp-footer-inner { grid-template-columns: 1fr 1fr; }
}

@media (min-width: 1024px) {
    .lp-footer-inner { grid-template-columns: 2fr 1fr 1fr 1fr; gap: 3rem; }
}

.lp-footer-brand {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.lp-footer-logo {
    height: 2rem;
    width: auto;
    filter: brightness(0) invert(1) opacity(0.85);
}

.lp-footer-tagline {
    font-size: 0.875rem;
    color: rgba(255,255,255,0.38);
    line-height: 1.7;
    margin: 0;
}

.lp-footer-socials {
    display: flex;
    gap: 0.625rem;
    margin-top: 0.25rem;
}

.lp-social-btn {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 0.625rem;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,0.45);
    text-decoration: none;
    transition: all 0.15s ease;
}

.lp-social-btn:hover {
    background: rgba(126,117,206,0.2);
    border-color: rgba(126,117,206,0.35);
    color: #7E75CE;
    transform: translateY(-2px);
}

.lp-social-btn svg { width: 1rem; height: 1rem; }

.lp-footer-col {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.lp-footer-col-title {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: rgba(255,255,255,0.55);
    margin: 0;
}

.lp-footer-links {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.lp-footer-link {
    font-size: 0.875rem;
    color: rgba(255,255,255,0.45);
    text-decoration: none;
    transition: color 0.15s;
}

.lp-footer-link:hover { color: #fff; }

.lp-footer-link-plain {
    font-size: 0.875rem;
    color: rgba(255,255,255,0.35);
}

.lp-footer-hours {
    margin-top: 0.5rem;
    padding: 0.75rem 1rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 0.625rem;
}

.lp-footer-hours-title {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(255,255,255,0.4);
    margin: 0 0 0.375rem;
}

.lp-footer-hours p {
    font-size: 0.8125rem;
    color: rgba(255,255,255,0.35);
    margin: 0.15rem 0;
}

.lp-footer-bottom {
    max-width: 88rem;
    margin: 0 auto;
    padding: 1.25rem 2rem;
    border-top: 1px solid rgba(255,255,255,0.06);
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    align-items: center;
    text-align: center;
}

@media (min-width: 768px) {
    .lp-footer-bottom {
        flex-direction: row;
        justify-content: space-between;
        text-align: left;
    }
}

.lp-footer-copy {
    font-size: 0.8125rem;
    color: rgba(255,255,255,0.22);
    margin: 0;
}

.lp-footer-legal {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    flex-wrap: wrap;
    justify-content: center;
}

.lp-footer-legal-link {
    font-size: 0.8125rem;
    color: rgba(255,255,255,0.28);
    text-decoration: none;
    transition: color 0.15s;
}

.lp-footer-legal-link:hover { color: rgba(255,255,255,0.65); }

.lp-footer-legal-dot {
    color: rgba(255,255,255,0.15);
    font-size: 0.75rem;
}
</style>
