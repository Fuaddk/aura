<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

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

onMounted(() => {
    featureTimer = setInterval(() => {
        activeFeature.value = (activeFeature.value + 1) % features.length;
    }, 3000);
});

onUnmounted(() => {
    clearInterval(featureTimer);
});
</script>

<template>
    <section id="funktioner">
        <div class="lp-section-wrap">

            <!-- Aside: label + title -->
            <aside class="lp-section-aside">
                <div class="lp-section-label">Hvad Aura kan</div>
                <h2 class="lp-section-title">
                    Alt hvad du har brug for<br>
                    <span class="lp-gradient-text">samlet ét sted</span>
                </h2>
                <p class="lp-section-sub">
                    Aura samler alle de værktøjer du har brug for gennem en skilsmisse i én samlet platform.
                </p>
            </aside>

            <!-- Card: feature selector -->
            <div class="lp-section-card">
                <div class="lp-features-layout">

                    <!-- Feature list (left) -->
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

        </div>
    </section>
</template>
