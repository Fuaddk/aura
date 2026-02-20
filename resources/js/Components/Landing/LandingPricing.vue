<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <section id="priser">
        <div class="lp-section-wrap">

            <!-- Aside: label + title -->
            <aside class="lp-section-aside">
                <div class="lp-section-label">Prisplaner</div>
                <h2 class="lp-section-title">
                    Vælg den plan<br>
                    <span class="lp-gradient-text">der passer til dig</span>
                </h2>
                <p class="lp-section-sub">
                    Start gratis og opgradér når du har brug for mere. Ingen binding.
                </p>
            </aside>

            <!-- Card: plans -->
            <div class="lp-section-card lp-section-card--pricing">
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

        </div>
    </section>
</template>
