<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import '../../css/landing.css';

import LandingBackground from '@/Components/Landing/LandingBackground.vue';
import LandingNav       from '@/Components/Landing/LandingNav.vue';
import LandingSidebar   from '@/Components/Landing/LandingSidebar.vue';
import LandingHero      from '@/Components/Landing/LandingHero.vue';
import LandingFeatures  from '@/Components/Landing/LandingFeatures.vue';
import LandingPricing   from '@/Components/Landing/LandingPricing.vue';
import LandingHow       from '@/Components/Landing/LandingHow.vue';
import LandingFooter    from '@/Components/Landing/LandingFooter.vue';

const scrollY        = ref(0);
const activeSection  = ref('hero');

const scrollTo = (id) => {
    if (id === 'hero') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

const handleScroll = () => {
    scrollY.value = window.scrollY;

    // Detect active section — check from bottom to top, first one whose top
    // is at or above 40% of the viewport height wins.
    const ids = ['hvordan', 'priser', 'funktioner'];
    for (const id of ids) {
        const el = document.getElementById(id);
        if (el && el.getBoundingClientRect().top <= window.innerHeight * 0.4) {
            activeSection.value = id;
            return;
        }
    }
    activeSection.value = 'hero';
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>

<template>
    <Head title="Aura – AI-rådgivning til din skilsmisse" />

    <div class="lp-root">
        <LandingBackground />
        <LandingNav      :scrollY="scrollY" :scrollTo="scrollTo" />
        <LandingSidebar  :activeSection="activeSection" :scrollTo="scrollTo" />
        <LandingHero />
        <LandingFeatures />
        <LandingPricing />
        <LandingHow />
        <LandingFooter />
    </div>
</template>
