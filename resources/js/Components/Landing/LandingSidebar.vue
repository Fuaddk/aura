<script setup>
defineProps({
    activeSection: { type: String, default: 'hero' },
    scrollTo: { type: Function, required: true },
});

const sections = [
    { id: 'hero',      label: 'Top' },
    { id: 'funktioner', label: 'Funktioner' },
    { id: 'priser',    label: 'Priser' },
    { id: 'hvordan',   label: 'Sådan' },
];
</script>

<template>
    <nav class="lp-sidebar" aria-label="Sektionsnavigation">
        <div class="lp-sidebar-inner">
            <button
                v-for="s in sections"
                :key="s.id"
                class="lp-sidebar-item"
                :class="{ 'lp-sidebar-item-active': activeSection === s.id }"
                @click="scrollTo(s.id)"
                :title="s.label"
            >
                <span class="lp-sidebar-dot"></span>
                <span class="lp-sidebar-label">{{ s.label }}</span>
            </button>
        </div>
    </nav>
</template>
