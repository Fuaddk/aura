<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    activeSection: { type: String, default: 'hero' },
    scrollTo: { type: Function, required: true },
});

const navItems = [
    { id: 'hero',       label: 'Kom i gang' },
    { id: 'funktioner', label: 'Funktioner' },
    { id: 'priser',     label: 'Priser' },
    { id: 'hvordan',    label: 'Sådan virker det' },
];
</script>

<template>
    <aside class="lp-sidebar" aria-label="Sidenavigation">

        <!-- Logo -->
        <div class="lp-sidebar-logo">
            <img src="/logo.png" alt="Aura" />
        </div>

        <!-- Navigation -->
        <nav class="lp-sidebar-nav">
            <button
                v-for="item in navItems"
                :key="item.id"
                class="lp-sidebar-nav-item"
                :class="{ 'lp-sidebar-nav-item-active': activeSection === item.id }"
                @click="scrollTo(item.id)"
            >
                {{ item.label }}
            </button>
        </nav>

        <!-- Divider -->
        <div class="lp-sidebar-divider"></div>

        <!-- Auth buttons -->
        <div class="lp-sidebar-bottom">
            <Link :href="route('login')" class="lp-sidebar-login-btn">Log ind</Link>
            <Link :href="route('register')" class="lp-sidebar-cta-btn">Opret konto</Link>
        </div>

    </aside>
</template>
