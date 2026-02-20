<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    scrollY: { type: Number, default: 0 },
    scrollTo: { type: Function, required: true },
});
</script>

<template>
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
</template>
