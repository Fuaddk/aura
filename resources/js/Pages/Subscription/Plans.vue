<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    subscriptionPlans: { type: Array, default: () => [] },
});

const page = usePage();
const user = page.props.auth.user;
const currentPlan = user.subscription_plan || 'free';

const loading = ref(null);

const plans = computed(() => props.subscriptionPlans.map(sp => ({
    id:      sp.slug,
    name:    sp.name,
    price:   String(sp.price),
    period:  sp.price > 0 ? '/md.' : '',
    description: sp.description || '',
    messages: sp.messages_limit === 0 ? 'Ubegrænset' : `${sp.messages_limit} AI-beskeder/md.`,
    features: Array.isArray(sp.features) ? sp.features : [],
    color:   sp.color || '#9ca3af',
    popular: sp.is_popular,
    hasStripe: !!sp.stripe_price_id,
    cta:     sp.slug === currentPlan ? 'Nuværende plan' : `Skift til ${sp.name}`,
})));

const startCheckout = (plan) => {
    if (plan.id === currentPlan || plan.id === 'free' || !plan.hasStripe || loading.value) return;
    loading.value = plan.id;

    // Use native form submit — Inertia's router.post() cannot follow external
    // redirects (Stripe) due to CORS. A real form POST lets the browser handle it.
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = route('subscription.checkout');
    form.style.display = 'none';

    const addField = (name, value) => {
        const el = document.createElement('input');
        el.type = 'hidden'; el.name = name; el.value = value;
        form.appendChild(el);
    };

    addField('_token', document.querySelector('meta[name="csrf-token"]')?.content ?? '');
    addField('plan', plan.id);

    document.body.appendChild(form);
    form.submit();
};

const goBack = () => {
    router.visit(route('dashboard'));
};
</script>

<template>
    <Head title="Abonnementsplaner" />

    <div class="plans-page">
        <!-- Back button -->
        <div class="plans-back">
            <button @click="goBack" class="plans-back-btn">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                Tilbage
            </button>
        </div>

        <!-- Header -->
        <div class="plans-header">
            <div class="plans-badge">ABONNEMENT</div>
            <h1 class="plans-title">Vælg den rette plan for dig</h1>
            <p class="plans-subtitle">
                Kom i gang gratis, og opgrader når du har brug for mere hjælp.
            </p>
        </div>

        <!-- Plan cards -->
        <div class="plans-grid">
            <div
                v-for="plan in plans"
                :key="plan.id"
                :class="['plans-card', { 'plans-card-popular': plan.popular, 'plans-card-current': currentPlan === plan.id }]"
            >
                <!-- Popular badge -->
                <div v-if="plan.popular" class="plans-popular-badge">
                    <span>Mest populær</span>
                </div>

                <!-- Plan header -->
                <div class="plans-card-header">
                    <div class="plans-plan-dot" :style="{ background: plan.color }"></div>
                    <div>
                        <div class="plans-plan-name">{{ plan.name }}</div>
                        <div class="plans-plan-desc">{{ plan.description }}</div>
                    </div>
                </div>

                <!-- Price -->
                <div class="plans-price-row">
                    <span class="plans-price-amount">{{ plan.price }}</span>
                    <span class="plans-price-currency">kr</span>
                    <span class="plans-price-period">{{ plan.period }}</span>
                </div>

                <!-- Messages -->
                <div class="plans-messages-badge" :style="{ color: plan.color, background: plan.color + '18' }">
                    {{ plan.messages }}
                </div>

                <!-- Features -->
                <ul class="plans-features">
                    <li v-for="feature in plan.features" :key="feature" class="plans-feature-item">
                        <svg xmlns="http://www.w3.org/2000/svg" class="plans-check-icon" :style="{ color: plan.color }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        {{ feature }}
                    </li>
                </ul>

                <!-- CTA Button -->
                <button
                    :class="[
                        'plans-cta-btn',
                        currentPlan === plan.id ? 'plans-cta-current' : (plan.popular ? 'plans-cta-primary' : 'plans-cta-secondary')
                    ]"
                    :disabled="currentPlan === plan.id || plan.id === 'free' || !plan.hasStripe || loading === plan.id"
                    @click="startCheckout(plan)"
                >
                    <span v-if="loading === plan.id" class="plans-spinner"></span>
                    <span v-else-if="currentPlan === plan.id">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Nuværende plan
                    </span>
                    <span v-else-if="!plan.hasStripe">Stripe ID mangler</span>
                    <span v-else>{{ plan.cta }}</span>
                </button>
            </div>
        </div>

        <!-- Footer note -->
        <p class="plans-footer-note">
            Du kan annullere eller skifte plan til enhver tid fra din
            <a :href="route('profile.edit')" class="plans-footer-link">profilside</a>.
            Betaling sker via Stripe.
        </p>
    </div>
</template>

<style scoped>
.plans-page {
    min-height: 100vh;
    background: #ffffff;
    padding: 2rem 1.5rem 4rem;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.plans-back {
    max-width: 900px;
    margin: 0 auto 1.5rem;
}

.plans-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.875rem;
    color: #6b7280;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.4rem 0.6rem;
    border-radius: 0.5rem;
    transition: background 0.15s, color 0.15s;
}
.plans-back-btn:hover {
    background: #f3f4f6;
    color: #111827;
}

.plans-header {
    max-width: 600px;
    margin: 0 auto 3rem;
    text-align: center;
}

.plans-badge {
    display: inline-block;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    color: #7E75CE;
    background: #ede9fe;
    padding: 0.3rem 0.9rem;
    border-radius: 9999px;
    margin-bottom: 1rem;
}

.plans-title {
    font-size: clamp(1.6rem, 4vw, 2.2rem);
    font-weight: 700;
    color: #111827;
    margin: 0 0 0.75rem;
    line-height: 1.2;
}

.plans-subtitle {
    font-size: 1rem;
    color: #6b7280;
    margin: 0;
    line-height: 1.6;
}

.plans-grid {
    max-width: 900px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.25rem;
    align-items: stretch;
}

.plans-card {
    position: relative;
    background: #ffffff;
    border: 1.5px solid #e5e7eb;
    border-radius: 1.25rem;
    padding: 1.75rem 1.5rem 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    transition: box-shadow 0.2s, border-color 0.2s;
}
.plans-card:hover {
    box-shadow: 0 4px 24px rgba(0,0,0,0.07);
}

.plans-card-popular {
    border-color: #7E75CE;
    box-shadow: 0 0 0 3px #ede9fe;
}

.plans-card-current {
    border-color: #d1fae5;
    background: #f0fdf4;
}

.plans-popular-badge {
    position: absolute;
    top: -0.75rem;
    left: 50%;
    transform: translateX(-50%);
    background: #7E75CE;
    color: #fff;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    padding: 0.25rem 0.9rem;
    border-radius: 9999px;
    white-space: nowrap;
}

.plans-card-header {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.plans-plan-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-top: 5px;
    flex-shrink: 0;
}

.plans-plan-name {
    font-size: 1.125rem;
    font-weight: 700;
    color: #111827;
    line-height: 1.3;
}

.plans-plan-desc {
    font-size: 0.8rem;
    color: #9ca3af;
    margin-top: 0.15rem;
    line-height: 1.4;
}

.plans-price-row {
    display: flex;
    align-items: baseline;
    gap: 0.2rem;
    margin-top: -0.5rem;
}

.plans-price-amount {
    font-size: 2.5rem;
    font-weight: 800;
    color: #111827;
    line-height: 1;
}

.plans-price-currency {
    font-size: 1rem;
    font-weight: 600;
    color: #6b7280;
}

.plans-price-period {
    font-size: 0.875rem;
    color: #9ca3af;
}

.plans-messages-badge {
    display: inline-block;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.3rem 0.75rem;
    border-radius: 0.5rem;
    width: fit-content;
}

.plans-features {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    flex: 1;
}

.plans-feature-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.875rem;
    color: #374151;
}

.plans-check-icon {
    width: 1rem;
    height: 1rem;
    flex-shrink: 0;
}

.plans-cta-btn {
    width: 100%;
    padding: 0.7rem 1rem;
    border-radius: 0.75rem;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    transition: opacity 0.15s, transform 0.1s;
    margin-top: auto;
}
.plans-cta-btn:active { transform: scale(0.98); }
.plans-cta-btn:disabled { cursor: default; opacity: 0.85; }
.plans-cta-btn:disabled:active { transform: none; }

.plans-cta-primary {
    background: #7E75CE;
    color: #fff;
}
.plans-cta-primary:not(:disabled):hover {
    background: #6d64be;
}

.plans-cta-secondary {
    background: #f3f4f6;
    color: #374151;
    border: 1.5px solid #e5e7eb;
}
.plans-cta-secondary:not(:disabled):hover {
    background: #e5e7eb;
}

.plans-cta-current {
    background: #d1fae5;
    color: #065f46;
    border: 1.5px solid #a7f3d0;
}

.plans-spinner {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border: 2px solid rgba(255,255,255,0.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.plans-footer-note {
    text-align: center;
    font-size: 0.8rem;
    color: #9ca3af;
    margin: 2.5rem auto 0;
    max-width: 500px;
}

.plans-footer-link {
    color: #7E75CE;
    text-decoration: none;
}
.plans-footer-link:hover {
    text-decoration: underline;
}

@media (max-width: 640px) {
    .plans-grid {
        grid-template-columns: 1fr;
        max-width: 400px;
    }
}
</style>
