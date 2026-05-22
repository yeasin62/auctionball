<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicFooter from '@/Components/PublicFooter.vue';
import PublicHeader from '@/Components/PublicHeader.vue';

const { t, locale } = useI18n();
const appDomain = computed(() => usePage().props.appDomain || 'auctionball.com');

const props = defineProps({
    plans:     { type: Array,  default: () => [] },
    unlimited: { type: Number, default: 999_999_999 },
});

// Localized number formatter — Bengali numerals when locale=bn, Western otherwise.
const numFmt = (n) => new Intl.NumberFormat(locale.value === 'bn' ? 'bn-IN' : 'en-IN').format(n);

const planPrice = (slug) => {
    const p = props.plans.find(x => x.slug === slug);
    return p ? numFmt(p.price_bdt) : '';
};
const planTeams = (slug) => {
    const p = props.plans.find(x => x.slug === slug);
    if (! p) return '';
    return p.teams_limit >= props.unlimited ? '∞' : numFmt(p.teams_limit);
};

/* ----------------------- Data ----------------------- */
// Decorative mockup data — names + roles render as labels in the hero/feature
// glass cards and stay literal across locales (proper nouns, sample numbers).
const heroQueue = [
    { initials: 'SR', name: 'Shakib Rahman', role: 'All-rounder · A+', live: true },
    { initials: 'TI', name: 'Tanveer Islam', role: 'Batsman · A',     bid: '৳45k' },
    { initials: 'MK', name: 'Mehedi Khan',   role: 'Bowler · B+',     bid: '৳30k' },
];
const heroBudgets = [
    { name: 'Dhaka Dynamites',  value: '৳1,25,000' },
    { name: 'Chittagong Kings', value: '৳1,20,000' },
    { name: 'Sylhet Strikers',  value: '৳1,15,000' },
];

const steps = computed(() => [
    { n: '01', tag: t('landing.steps.s1_tag'), title: t('landing.steps.s1_title'), icon: 'rocket' },
    { n: '02', tag: t('landing.steps.s2_tag'), title: t('landing.steps.s2_title'), icon: 'users'  },
    { n: '03', tag: t('landing.steps.s3_tag'), title: t('landing.steps.s3_title'), icon: 'play'   },
]);

const wsEvents = computed(() => [
    { label: t('landing.features.event_update'), meta: t('landing.features.event_update_meta'), highlight: false },
    { label: t('landing.features.event_bid'),    meta: '+ ৳5,000',                              highlight: false },
    { label: t('landing.features.event_sold'),   meta: '৳1,25,000',                             highlight: true  },
]);

const seasonBars = [
    { team: 'Dhaka Dynamites',  spent: '৳3.75L', cap: '৳5L', pct: 75 },
    { team: 'Chittagong Kings', spent: '৳3.10L', cap: '৳5L', pct: 62 },
];

const bigBidIncrements = ['+ ৳5,000', '+ ৳10,000', '+ ৳25,000'];
const bigBidHistory = [
    { time: '14:32:14', team: 'DHK', amount: '৳1,25,000', current: true },
    { time: '14:32:11', team: 'CTG', amount: '৳1,20,000' },
    { time: '14:32:08', team: 'DHK', amount: '৳1,15,000' },
    { time: '14:32:04', team: 'SYL', amount: '৳1,10,000' },
    { time: '14:32:01', team: 'CTG', amount: '৳1,05,000' },
];
const bigBudgets = [
    { team: 'Dhaka',      pct: 75, color: 'from-cyan-400 to-blue-500' },
    { team: 'Chittagong', pct: 62, color: 'from-indigo-400 to-violet-500' },
    { team: 'Sylhet',     pct: 88, color: 'from-emerald-400 to-emerald-500' },
    { team: 'Khulna',     pct: 41, color: 'from-amber-400 to-orange-500' },
];

const plans = computed(() => [
    {
        name: t('landing.pricing.free_name'),
        tagline: t('landing.pricing.free_tagline'),
        price: planPrice('free') || '0', unit: t('landing.pricing.free_unit'),
        meta: t('landing.pricing.free_meta', { teams: planTeams('free') }),
        cta: t('landing.pricing.free_cta'),
        bullets: [
            t('landing.pricing.free_b1'),
            t('landing.pricing.free_b2', { teams: planTeams('free') }),
            t('landing.pricing.free_b3'),
            t('landing.pricing.free_b4'),
            t('landing.pricing.free_b5'),
        ],
        popular: false, free: true, slug: 'free',
    },
    {
        name: t('landing.pricing.starter_name'),
        tagline: t('landing.pricing.starter_tagline'),
        price: planPrice('starter') || '1,999', unit: t('landing.pricing.monthly_unit'),
        meta: t('landing.pricing.starter_meta', { teams: planTeams('starter') }),
        cta: t('landing.pricing.starter_cta'),
        bullets: [
            t('landing.pricing.starter_b1'),
            t('landing.pricing.starter_b2'),
            t('landing.pricing.starter_b3', { teams: planTeams('starter') }),
            t('landing.pricing.starter_b4'),
            t('landing.pricing.starter_b5'),
            t('landing.pricing.starter_b6'),
        ],
        popular: true, free: false, slug: 'starter',
        bullets_lead_idx: 0,
    },
    {
        name: t('landing.pricing.pro_name'),
        tagline: t('landing.pricing.pro_tagline'),
        price: planPrice('pro') || '4,999', unit: t('landing.pricing.monthly_unit'),
        meta: t('landing.pricing.pro_meta', { teams: planTeams('pro') }),
        cta: t('landing.pricing.pro_cta'),
        bullets: [
            t('landing.pricing.pro_b1'),
            t('landing.pricing.pro_b2', { teams: planTeams('pro') }),
            t('landing.pricing.pro_b3'),
            t('landing.pricing.pro_b4'),
            t('landing.pricing.pro_b5'),
            t('landing.pricing.pro_b6'),
        ],
        popular: false, free: false, slug: 'pro',
        bullets_lead_idx: 0,
    },
    {
        name: t('landing.pricing.enterprise_name'),
        tagline: t('landing.pricing.enterprise_tagline'),
        price: planPrice('enterprise') || '5,999', unit: t('landing.pricing.monthly_unit'),
        meta: t('landing.pricing.enterprise_meta'),
        cta: t('landing.pricing.enterprise_cta'),
        bullets: [
            t('landing.pricing.enterprise_b1'),
            t('landing.pricing.enterprise_b2'),
            t('landing.pricing.enterprise_b3'),
            t('landing.pricing.enterprise_b4'),
            t('landing.pricing.enterprise_b5'),
        ],
        popular: false, free: false, slug: 'enterprise',
        bullets_lead_idx: 0,
    },
]);

const whyItems = computed(() => [
    { n: '— 01', title: t('landing.why.i1_title'), body: t('landing.why.i1_body') },
    { n: '— 02', title: t('landing.why.i2_title'), body: t('landing.why.i2_body') },
    { n: '— 03', title: t('landing.why.i3_title'), body: t('landing.why.i3_body') },
    { n: '— 04', title: t('landing.why.i4_title'), body: t('landing.why.i4_body') },
]);

const testimonials = computed(() => [
    { initials: 'RH', name: t('landing.testimonials.t1_name'), role: t('landing.testimonials.t1_role'), quote: t('landing.testimonials.t1_quote') },
    { initials: 'FA', name: t('landing.testimonials.t2_name'), role: t('landing.testimonials.t2_role'), quote: t('landing.testimonials.t2_quote') },
    { initials: 'IS', name: t('landing.testimonials.t3_name'), role: t('landing.testimonials.t3_role'), quote: t('landing.testimonials.t3_quote') },
]);

const faqs = computed(() => [
    { q: t('landing.faq.q1'), a: t('landing.faq.a1', { domain: appDomain.value }) },
    { q: t('landing.faq.q2'), a: t('landing.faq.a2') },
    { q: t('landing.faq.q3'), a: t('landing.faq.a3') },
    { q: t('landing.faq.q4'), a: t('landing.faq.a4') },
    { q: t('landing.faq.q5'), a: t('landing.faq.a5') },
    { q: t('landing.faq.q6'), a: t('landing.faq.a6') },
]);
const openFaq = ref(0);
const toggleFaq = (i) => (openFaq.value = openFaq.value === i ? -1 : i);

// ============== SEO + AEO ==============
// Meta + JSON-LD structured data so Google, Bing, AND answer engines (ChatGPT,
// Perplexity, Claude, Google AI Overviews) can correctly identify the product,
// answer questions about it, and surface the FAQ as direct answers.
const siteUrl       = computed(() => `https://${appDomain.value}`);
const seoTitle      = computed(() => locale.value === 'bn'
    ? 'ক্রিকেট ও ফুটবল প্লেয়ার অকশন সফটওয়্যার (লাইভ বিডিং)'
    : 'Real-time cricket & football player auction software');
const seoDescription = computed(() => locale.value === 'bn'
    ? 'বাংলাদেশের সেরা টুর্নামেন্ট সংগঠকদের জন্য তৈরি — লাইভ প্লেয়ার অকশন, রিয়েল-টাইম বিডিং, বিগ-স্ক্রিন ব্রডকাস্ট, মাল্টি-টিম বাজেট, bKash/PayPal বিলিং। ৩০ সেকেন্ডে সাইনআপ।'
    : 'Run live player auctions for cricket and football tournaments. Real-time bidding, big-screen broadcast, multi-team budgets, custom registration forms, bKash/PayPal billing. Built for Bangladesh organizers. Free to start.');
const ogImage       = computed(() => `${siteUrl.value}/og-image.png`);

// FAQ JSON-LD — the single biggest AEO win. Answer engines extract Q/A pairs
// directly from this and surface them in AI overviews / answer panels.
const faqJsonLd = computed(() => JSON.stringify({
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: faqs.value.map(f => ({
        '@type': 'Question',
        name: f.q,
        acceptedAnswer: { '@type': 'Answer', text: f.a },
    })),
}));

// SoftwareApplication schema — tells crawlers WHAT the product is, with
// pricing, ratings, screenshots, supported platforms.
const softwareJsonLd = computed(() => JSON.stringify({
    '@context': 'https://schema.org',
    '@type': 'SoftwareApplication',
    name: 'AuctionBall',
    applicationCategory: 'BusinessApplication',
    operatingSystem: 'Web (any modern browser)',
    description: seoDescription.value,
    url: siteUrl.value,
    image: ogImage.value,
    featureList: [
        'Real-time WebSocket bidding',
        'Big-screen broadcast view',
        'Multi-team budget tracking',
        'Custom player registration forms',
        'bKash and PayPal payment integration',
        'Cricket and football tournament support',
        'Multi-tenant organization isolation',
        'Bengali and English UI',
    ],
    offers: (props.plans?.length ? props.plans : []).map(p => ({
        '@type': 'Offer',
        name: p.slug,
        price: String(p.price_bdt ?? 0),
        priceCurrency: 'BDT',
        availability: 'https://schema.org/InStock',
        url: `${siteUrl.value}/register?plan=${p.slug}`,
    })),
    publisher: {
        '@type': 'Organization',
        name: 'AuctionBall',
        url: siteUrl.value,
    },
}));

// Organization schema — supports Google Knowledge Panel, AI "what is X" answers
const organizationJsonLd = computed(() => JSON.stringify({
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: 'AuctionBall',
    url: siteUrl.value,
    logo: ogImage.value,
    description: seoDescription.value,
    sameAs: [
        // Add real social profile URLs once they exist:
        // 'https://twitter.com/auctionball',
        // 'https://facebook.com/auctionball',
    ],
    contactPoint: [{
        '@type': 'ContactPoint',
        contactType: 'customer support',
        email: `support@${appDomain.value}`,
        availableLanguage: ['English', 'Bengali'],
    }],
    address: {
        '@type': 'PostalAddress',
        addressCountry: 'BD',
    },
}));

// WebSite schema with SearchAction enables sitelinks search box on Google
const websiteJsonLd = computed(() => JSON.stringify({
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: 'AuctionBall',
    url: siteUrl.value,
    inLanguage: ['en', 'bn'],
}));

// BreadcrumbList — anchored at site root so AI engines understand site structure
const breadcrumbJsonLd = computed(() => JSON.stringify({
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: [
        { '@type': 'ListItem', position: 1, name: 'Home', item: siteUrl.value },
    ],
}));

// Inject JSON-LD scripts directly into document.head — Inertia's <Head>
// component doesn't reliably support <script> children (Vue's template
// compiler strips them). Modern crawlers (Googlebot, Bingbot, OpenAI's
// browsing tool, Perplexity, Claude's web crawler) all execute JS, so an
// onMounted injection is fully indexable.
const installJsonLd = () => {
    const ldScripts = {
        'jsonld-organization': organizationJsonLd.value,
        'jsonld-website':      websiteJsonLd.value,
        'jsonld-software':     softwareJsonLd.value,
        'jsonld-faq':          faqJsonLd.value,
        'jsonld-breadcrumb':   breadcrumbJsonLd.value,
    };
    Object.entries(ldScripts).forEach(([id, content]) => {
        let el = document.getElementById(id);
        if (! el) {
            el = document.createElement('script');
            el.id = id;
            el.type = 'application/ld+json';
            document.head.appendChild(el);
        }
        el.textContent = content;
    });
};

onMounted(installJsonLd);
// Re-render schemas when locale switches so AI engines pick up the right
// language version of the FAQ answers etc.
watch(locale, installJsonLd);
onBeforeUnmount(() => {
    ['jsonld-organization','jsonld-website','jsonld-software','jsonld-faq','jsonld-breadcrumb']
        .forEach(id => document.getElementById(id)?.remove());
});

// Payment method catalog — labels stay as literals (brand names); only
// "Bank transfer" gets translated. The dot color is keyed to the method so
// each tile keeps its identity regardless of which others are enabled. The
// admin picks which keys appear via Super admin → Payments.
const paymentCatalog = computed(() => ({
    bkash:           { label: 'bKash',             dot: 'bg-pink-500'    },
    nagad:           { label: 'Nagad',             dot: 'bg-amber-500'   },
    rocket:          { label: 'Rocket',            dot: 'bg-emerald-500' },
    sslcommerz:      { label: 'SSLCommerz',        dot: 'bg-blue-500'    },
    paypal:          { label: 'PayPal',            dot: 'bg-indigo-500'  },
    visa_mastercard: { label: 'Visa / Mastercard', dot: 'bg-violet-500'  },
    bank_transfer:   { label: t('landing.payments.bank_transfer'), dot: 'bg-ink-400' },
}));

const landingPaymentMethods = computed(() => usePage().props.landingPaymentMethods || []);
const payments = computed(() =>
    landingPaymentMethods.value
        .map(key => paymentCatalog.value[key])
        .filter(Boolean),
);

</script>

<template>
    <!-- ============== SEO + AEO META ============== -->
    <Head :title="seoTitle">
        <meta name="description" :content="seoDescription" head-key="description" />
        <meta name="keywords" content="player auction software, cricket auction platform, tournament auction system Bangladesh, live player auction app, cricket auction software, football auction software, player auction Bangladesh, BPL auction, IPL-style auction, tournament bidding system, bKash auction software, real-time auction, big screen auction, AuctionBall" />
        <meta name="author" content="AuctionBall" />

        <!-- Canonical + alternates -->
        <link rel="canonical" :href="siteUrl + '/'" head-key="canonical" />
        <link rel="alternate" hreflang="en" :href="siteUrl + '/?lang=en'" />
        <link rel="alternate" hreflang="bn" :href="siteUrl + '/?lang=bn'" />
        <link rel="alternate" hreflang="x-default" :href="siteUrl + '/'" />

        <!-- Open Graph (Facebook, LinkedIn, WhatsApp link previews) -->
        <meta property="og:type" content="website" head-key="og:type" />
        <meta property="og:title" :content="seoTitle" head-key="og:title" />
        <meta property="og:description" :content="seoDescription" head-key="og:description" />
        <meta property="og:url" :content="siteUrl + '/'" head-key="og:url" />
        <meta property="og:image" :content="ogImage" head-key="og:image" />
        <meta property="og:image:width" content="1200" />
        <meta property="og:image:height" content="630" />
        <meta property="og:image:alt" content="AuctionBall live auction control panel and big-screen view" />
        <meta property="og:site_name" content="AuctionBall" head-key="og:site_name" />
        <meta property="og:locale" :content="locale === 'bn' ? 'bn_BD' : 'en_US'" head-key="og:locale" />
        <meta property="og:locale:alternate" :content="locale === 'bn' ? 'en_US' : 'bn_BD'" />

        <!-- Twitter / X card -->
        <meta name="twitter:card" content="summary_large_image" head-key="twitter:card" />
        <meta name="twitter:title" :content="seoTitle" head-key="twitter:title" />
        <meta name="twitter:description" :content="seoDescription" head-key="twitter:description" />
        <meta name="twitter:image" :content="ogImage" head-key="twitter:image" />
        <meta name="twitter:image:alt" content="AuctionBall live auction interface" />

        <!-- AI / answer engines hint they should index, follow, and use full snippets -->
        <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1" head-key="robots" />
        <meta name="googlebot" content="index,follow,max-image-preview:large,max-snippet:-1" />
        <meta name="bingbot" content="index,follow" />

    </Head>

    <div class="page-bg min-h-screen">

        <!-- ============== NAV ============== -->
        <PublicHeader home />

        <!-- ============== HERO ============== -->
        <section class="relative">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 pt-8 sm:pt-10 pb-16 sm:pb-24 grid lg:grid-cols-12 gap-8 sm:gap-10 items-start">
                <div class="lg:col-span-7">
                    <div class="mono-pill mb-6 sm:mb-7"><span class="dot"></span>{{ t('landing.hero.pill') }}</div>

                    <h1 class="text-[34px] sm:text-[44px] lg:text-[58px] leading-[1.08] sm:leading-[1.05] lg:leading-[1.04] font-extrabold tracking-tight text-ink-900">
                        {{ t('landing.hero.h1_top') }}
                        <span class="text-grad">{{ t('landing.hero.h1_grad_a') }}<br/>{{ t('landing.hero.h1_grad_b') }}</span>
                    </h1>

                    <p class="mt-5 sm:mt-6 max-w-xl text-[15px] sm:text-[16px] leading-relaxed text-ink-500">
                        {{ t('landing.hero.body') }}
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <Link href="/register" class="btn-primary px-6">{{ t('nav.start_free') }} <span aria-hidden>→</span></Link>
                        <a href="#big-screen" class="btn-ghost px-6">{{ t('nav.see_live_demo') }}</a>
                    </div>

                    <p class="mt-9 text-[13px] text-ink-500 font-mono">
                        <span class="text-ink-900 font-semibold">{{ t('landing.hero.stat_realtime') }}</span><span v-if="t('landing.hero.stat_realtime_after')"> {{ t('landing.hero.stat_realtime_after') }}</span>
                        <span class="px-2">·</span>
                        <span class="text-ink-900 font-semibold">∞</span> {{ t('landing.hero.stat_seasons_after') }}
                        <span class="px-2">·</span>
                        <span class="text-ink-900 font-semibold">{{ t('landing.hero.stat_bigscreen') }}</span> {{ t('landing.hero.stat_bigscreen_after') }}
                    </p>
                </div>

                <!-- Right: glass mockup -->
                <div class="lg:col-span-5">
                    <div class="glass-strong rounded-3xl p-5 sm:p-6 shadow-glass-lg">
                        <div class="flex items-center gap-1.5 mb-4">
                            <span class="h-2.5 w-2.5 rounded-full bg-ink-200"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-ink-200"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-ink-200"></span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Player queue -->
                            <div class="rounded-2xl bg-white/70 border border-white/80 p-4">
                                <div class="font-mono text-[10.5px] tracking-widest text-ink-400 mb-3">{{ t('landing.mockup.player_queue') }}</div>
                                <div class="space-y-3">
                                    <div v-for="p in heroQueue" :key="p.initials" class="flex items-start gap-3">
                                        <img v-if="p.photo" :src="p.photo" :alt="p.name" width="36" height="36" class="h-9 w-9 rounded-full object-cover border border-blue-300/30 shrink-0" decoding="async" />
                                        <div v-else class="avatar shrink-0">{{ p.initials }}</div>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-[13px] font-semibold leading-tight">{{ p.name }}</div>
                                            <div class="text-[11.5px] text-ink-500 leading-tight">{{ p.role }}</div>
                                        </div>
                                        <div v-if="p.live" class="flex items-center gap-1 text-[10.5px] font-mono text-rose-500 px-2 py-0.5 rounded-full bg-rose-50 border border-rose-100">
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>LIVE
                                        </div>
                                        <div v-else class="text-[12px] font-mono text-ink-700 font-semibold">{{ p.bid }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Current bid -->
                            <div class="rounded-2xl bg-white/70 border border-white/80 p-4 flex flex-col">
                                <div class="font-mono text-[10.5px] tracking-widest text-ink-400 mb-3">{{ t('landing.mockup.current_bid_lot') }}</div>
                                <div class="text-[12px] font-mono text-ink-500 mb-1">{{ t('landing.mockup.player_name') }}</div>
                                <div class="text-[26px] font-bold tracking-tight text-grad mb-3">৳1,25,000</div>

                                <div class="rounded-xl bg-white border border-ink-100 px-3 py-2 mb-3 text-center">
                                    <div class="font-mono text-[20px] tracking-widest text-ink-900">00 : 01</div>
                                </div>

                                <div class="space-y-1.5 text-[11.5px]">
                                    <div v-for="b in heroBudgets" :key="b.name" class="flex justify-between">
                                        <span class="text-ink-600">{{ b.name }}</span>
                                        <span class="font-mono text-ink-900 font-semibold">{{ b.value }}</span>
                                    </div>
                                </div>

                                <div class="mt-auto pt-3">
                                    <div class="flex justify-between text-[10.5px] font-mono text-ink-500 mb-1">
                                        <span>{{ t('landing.mockup.budget') }}</span>
                                        <span>৳3,75,000 / ৳5,00,000</span>
                                    </div>
                                    <div class="bar-track"><div class="bar-fill" style="width:75%"></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============== HOW IT WORKS ============== -->
        <section id="how-it-works" class="relative">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 pb-16 sm:pb-24 text-center">
                <div class="mono-pill mx-auto mb-5">{{ t('landing.steps.pill') }}</div>
                <h2 class="text-[30px] sm:text-[36px] lg:text-[44px] leading-[1.1] sm:leading-[1.08] font-extrabold tracking-tight">{{ t('landing.steps.heading') }}</h2>
                <p class="mt-5 max-w-2xl mx-auto text-ink-500">
                    {{ t('landing.steps.body') }}
                </p>

                <div class="mt-12 grid md:grid-cols-3 gap-5 text-left">
                    <div v-for="s in steps" :key="s.n" class="glass rounded-2xl p-6">
                        <div class="icon-tile mb-5">
                            <svg v-if="s.icon==='rocket'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path d="M5 13l3-7 7-3 4 4-3 7-7 3-4-4z"/><circle cx="12" cy="10" r="1.5"/>
                            </svg>
                            <svg v-else-if="s.icon==='users'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <circle cx="9" cy="8" r="3"/><path d="M3 21c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="8" r="2.5"/><path d="M17 13c2.8 0 5 2.2 5 5"/>
                            </svg>
                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path d="M6 4l14 8-14 8V4z"/>
                            </svg>
                        </div>
                        <h3 class="text-[20px] font-bold tracking-tight mb-3">{{ s.title }}</h3>
                        <p class="text-[14.5px] leading-relaxed text-ink-500">
                            <template v-if="s.n==='01'">
                                {{ t('landing.steps.s1_body_a') }}
                                <code class="font-mono text-[12.5px] bg-ink-50 px-1.5 py-0.5 rounded">yours.{{ appDomain }}</code>
                                {{ t('landing.steps.s1_body_b') }}
                            </template>
                            <template v-else-if="s.n==='02'">
                                {{ t('landing.steps.s2_body') }}
                            </template>
                            <template v-else>
                                {{ t('landing.steps.s3_body_a') }}
                                <code class="font-mono text-[12.5px] bg-ink-50 px-1.5 py-0.5 rounded">/live</code>
                                {{ t('landing.steps.s3_body_b') }}
                            </template>
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============== FEATURES ============== -->
        <section id="features" class="relative">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 pb-16 sm:pb-24 text-center">
                <div class="mono-pill mx-auto mb-5">{{ t('landing.features.pill') }}</div>
                <h2 class="text-[30px] sm:text-[36px] lg:text-[44px] leading-[1.1] sm:leading-[1.08] font-extrabold tracking-tight">
                    {{ t('landing.features.heading_a') }}<br/>{{ t('landing.features.heading_b') }}
                </h2>
                <p class="mt-5 max-w-2xl mx-auto text-ink-500">
                    {{ t('landing.features.body') }}
                </p>

                <div class="mt-12 grid md:grid-cols-2 lg:grid-cols-3 gap-5 text-left">
                    <!-- 1. Real-time engine -->
                    <div class="glass rounded-2xl p-6 lg:col-span-2">
                        <div class="icon-tile mb-5">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path d="M3 12h3l3-7 4 14 3-7h5"/>
                            </svg>
                        </div>
                        <h3 class="text-[20px] font-bold mb-3 tracking-tight">{{ t('landing.features.engine_title') }}</h3>
                        <p class="text-[14.5px] text-ink-500 leading-relaxed mb-5">
                            {{ t('landing.features.engine_body') }}
                        </p>
                        <div class="space-y-2">
                            <div v-for="(r, i) in wsEvents" :key="i" class="code-line" :class="{ highlight: r.highlight }">
                                <div class="flex items-center gap-2">
                                    <span class="h-1.5 w-1.5 rounded-full"
                                          :class="r.highlight ? 'bg-brand-indigo' : 'bg-emerald-500'"></span>
                                    <span class="text-ink-900 font-medium">{{ r.label }}</span>
                                </div>
                                <div :class="r.highlight ? 'text-brand-indigo font-semibold' : 'text-ink-500'">{{ r.meta }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Big-screen broadcast -->
                    <div class="glass rounded-2xl p-6">
                        <div class="icon-tile mb-5">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <rect x="3" y="4" width="18" height="13" rx="2"/><path d="M9 21h6M12 17v4"/>
                            </svg>
                        </div>
                        <h3 class="text-[20px] font-bold mb-3 tracking-tight">{{ t('landing.features.bigscreen_title') }}</h3>
                        <p class="text-[14.5px] text-ink-500 leading-relaxed mb-5">
                            {{ t('landing.features.bigscreen_body_a') }}
                            <code class="font-mono text-[12.5px] bg-ink-50 px-1.5 py-0.5 rounded">yours.{{ appDomain }}/live</code>{{ t('landing.features.bigscreen_body_b') }}
                        </p>
                        <div class="code-line">
                            <div class="flex items-center gap-2 font-mono text-[12.5px]">
                                <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                                <span>yours.{{ appDomain }}/live</span>
                            </div>
                            <span class="text-blue-500 font-mono font-semibold">LIVE</span>
                        </div>
                    </div>

                    <!-- 3. Multi-season -->
                    <div class="glass rounded-2xl p-6">
                        <div class="icon-tile mb-5">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <rect x="3" y="5" width="18" height="14" rx="2"/>
                                <path d="M3 9h18"/>
                            </svg>
                        </div>
                        <h3 class="text-[20px] font-bold mb-3 tracking-tight">{{ t('landing.features.multi_title') }}</h3>
                        <p class="text-[14.5px] text-ink-500 leading-relaxed mb-5">
                            {{ t('landing.features.multi_body') }}
                        </p>
                        <div class="space-y-3">
                            <div v-for="b in seasonBars" :key="b.team">
                                <div class="flex justify-between text-[12px] font-mono mb-1">
                                    <span class="text-ink-600">{{ b.team }}</span>
                                    <span class="text-ink-900 font-semibold">{{ b.spent }} / {{ b.cap }}</span>
                                </div>
                                <div class="bar-track"><div class="bar-fill" :style="{width: b.pct + '%'}"></div></div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Player profiles -->
                    <div class="glass rounded-2xl p-6 lg:col-span-2">
                        <div class="icon-tile mb-5">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <circle cx="12" cy="8" r="4"/>
                                <path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/>
                            </svg>
                        </div>
                        <h3 class="text-[20px] font-bold mb-3 tracking-tight">{{ t('landing.features.profiles_title') }}</h3>
                        <p class="text-[14.5px] text-ink-500 leading-relaxed mb-5">
                            {{ t('landing.features.profiles_body') }}
                        </p>
                        <div class="rounded-xl bg-white/70 border border-white/80 px-4 py-3 flex items-center gap-4">
                            <div class="avatar h-12 w-12 shrink-0 text-[13px]">SR</div>
                            <div class="flex-1 min-w-0">
                                <div class="text-[14px] font-semibold leading-tight">Shakib Rahman</div>
                                <div class="text-[12px] text-ink-500 leading-tight">All-rounder · Mirpur · Age 26</div>
                            </div>
                            <div class="flex gap-6 text-center">
                                <div>
                                    <div class="text-[16px] font-bold tracking-tight">42.8</div>
                                    <div class="text-[10px] font-mono tracking-widest text-ink-400 mt-0.5">AVG</div>
                                </div>
                                <div>
                                    <div class="text-[16px] font-bold tracking-tight">28</div>
                                    <div class="text-[10px] font-mono tracking-widest text-ink-400 mt-0.5">WKTS</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Auction control center -->
                    <div class="glass rounded-2xl p-6">
                        <div class="icon-tile mb-5">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <rect x="3" y="4" width="18" height="14" rx="2"/>
                                <path d="M9 9h2v6H9zM13 9h2v6h-2z"/>
                            </svg>
                        </div>
                        <h3 class="text-[20px] font-bold mb-3 tracking-tight">{{ t('landing.features.control_title') }}</h3>
                        <p class="text-[14.5px] text-ink-500 leading-relaxed">
                            {{ t('landing.features.control_body') }}
                        </p>
                    </div>

                    <!-- 6. Mobile bidding -->
                    <div class="glass rounded-2xl p-6">
                        <div class="icon-tile mb-5">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <rect x="7" y="3" width="10" height="18" rx="2"/>
                                <path d="M11 18h2"/>
                            </svg>
                        </div>
                        <h3 class="text-[20px] font-bold mb-3 tracking-tight">{{ t('landing.features.mobile_title') }}</h3>
                        <p class="text-[14.5px] text-ink-500 leading-relaxed">
                            {{ t('landing.features.mobile_body') }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============== BIG SCREEN PREVIEW ============== -->
        <section id="big-screen" class="relative">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 pb-16 sm:pb-24 text-center">
                <div class="mono-pill mx-auto mb-5">{{ t('landing.bigscreen_section.pill') }}</div>
                <h2 class="text-[30px] sm:text-[36px] lg:text-[44px] leading-[1.1] sm:leading-[1.08] font-extrabold tracking-tight">{{ t('landing.bigscreen_section.heading') }}</h2>
                <p class="mt-5 max-w-2xl mx-auto text-ink-500">
                    {{ t('landing.bigscreen_section.body_a') }}
                    <code class="font-mono text-[13px] bg-ink-50 px-1.5 py-0.5 rounded">yours.{{ appDomain }}/live</code>
                    {{ t('landing.bigscreen_section.body_b') }}
                </p>

                <div class="mt-12 glass-strong rounded-3xl p-6 sm:p-8 shadow-glass-lg text-left">
                    <div class="grid lg:grid-cols-12 gap-6">
                        <!-- Player -->
                        <div class="lg:col-span-4 rounded-2xl bg-white/70 border border-white/80 p-5">
                            <div class="flex items-center justify-between mb-4">
                                <div class="font-mono text-[11px] text-ink-500">{{ t('landing.bigscreen_section.lot') }}</div>
                                <div class="flex items-center gap-1.5 text-[10.5px] font-mono text-rose-500 px-2 py-0.5 rounded-full bg-rose-50 border border-rose-100">
                                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>{{ t('landing.bigscreen_section.live') }}
                                </div>
                            </div>
                            <div class="flex justify-center my-4">
                                <div class="avatar h-24 w-24 rounded-2xl border-2 border-white/60 text-[24px] shadow-md">SR</div>
                            </div>
                            <div class="text-center">
                                <div class="text-[22px] font-bold tracking-tight">{{ t('landing.bigscreen_section.player_name') }}</div>
                                <div class="font-mono text-[11px] tracking-widest text-ink-500 mt-0.5">{{ t('landing.bigscreen_section.player_meta') }}</div>
                            </div>
                            <div class="grid grid-cols-3 gap-3 mt-5 text-center">
                                <div>
                                    <div class="text-[20px] font-bold tracking-tight">42.8</div>
                                    <div class="text-[10px] font-mono tracking-widest text-ink-400">{{ t('landing.bigscreen_section.bat_avg') }}</div>
                                </div>
                                <div>
                                    <div class="text-[20px] font-bold tracking-tight">28</div>
                                    <div class="text-[10px] font-mono tracking-widest text-ink-400">{{ t('landing.bigscreen_section.wkts') }}</div>
                                </div>
                                <div>
                                    <div class="text-[20px] font-bold tracking-tight">147</div>
                                    <div class="text-[10px] font-mono tracking-widest text-ink-400">{{ t('landing.bigscreen_section.strike') }}</div>
                                </div>
                            </div>
                            <div class="mt-5 pt-4 border-t border-ink-100 space-y-1.5 text-[12.5px]">
                                <div class="flex justify-between"><span class="text-ink-500">{{ t('landing.bigscreen_section.base_price') }}</span><span class="font-mono font-semibold">৳50,000</span></div>
                                <div class="flex justify-between"><span class="text-ink-500">{{ t('landing.bigscreen_section.from') }}</span><span>{{ t('landing.bigscreen_section.from_value') }}</span></div>
                                <div class="flex justify-between"><span class="text-ink-500">{{ t('landing.bigscreen_section.age') }}</span><span>26</span></div>
                            </div>
                        </div>

                        <!-- Center: bid + timer + actions -->
                        <div class="lg:col-span-5 space-y-4">
                            <div class="rounded-2xl p-6 text-center"
                                 style="background:linear-gradient(135deg,rgba(186,219,255,.45),rgba(232,213,255,.55));border:1px solid rgba(255,255,255,.7);">
                                <div class="font-mono text-[11px] tracking-widest text-ink-500 mb-2">{{ t('landing.bigscreen_section.current_bid') }}</div>
                                <div class="text-[32px] sm:text-[40px] lg:text-[44px] font-extrabold tracking-tight text-grad leading-none">৳1,25,000</div>
                                <div class="mt-3 text-[13px] text-ink-600">{{ t('landing.bigscreen_section.leading') }} <span class="font-semibold text-ink-900">{{ t('landing.bigscreen_section.leading_team') }}</span></div>
                            </div>
                            <div class="rounded-2xl bg-white/80 border border-white/80 p-6">
                                <div class="text-center font-mono text-[32px] sm:text-[40px] lg:text-[44px] tracking-widest text-ink-900">
                                    00 <span class="text-ink-300">:</span> 04
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <button v-for="inc in bigBidIncrements" :key="inc" class="btn-ghost py-2.5 text-[13px] font-mono">{{ inc }}</button>
                            </div>
                            <button class="btn-primary w-full py-4 text-[15px]">{{ t('landing.bigscreen_section.place_bid') }}</button>
                        </div>

                        <!-- Right: bid history + budgets -->
                        <div class="lg:col-span-3 space-y-4">
                            <div class="rounded-2xl bg-white/70 border border-white/80 p-5">
                                <div class="font-mono text-[11px] tracking-widest text-ink-500 mb-3">{{ t('landing.bigscreen_section.bid_history') }}</div>
                                <ul class="space-y-2 text-[12.5px] font-mono">
                                    <li v-for="(b, i) in bigBidHistory" :key="i"
                                        class="flex justify-between items-center px-2 py-1 rounded"
                                        :class="b.current ? 'bg-blue-50 border border-blue-100 text-ink-900' : 'text-ink-600'">
                                        <span>{{ b.time }} {{ b.team }}</span>
                                        <span class="font-semibold">{{ b.amount }}</span>
                                    </li>
                                </ul>
                            </div>
                            <div class="rounded-2xl bg-white/70 border border-white/80 p-5">
                                <div class="font-mono text-[11px] tracking-widest text-ink-500 mb-3">{{ t('landing.bigscreen_section.team_budgets') }}</div>
                                <div class="space-y-2.5">
                                    <div v-for="b in bigBudgets" :key="b.team">
                                        <div class="flex justify-between text-[11.5px] mb-1">
                                            <span>{{ b.team }}</span>
                                            <span class="font-mono font-semibold">{{ b.pct }}%</span>
                                        </div>
                                        <div class="bar-track">
                                            <div class="h-full rounded-full bg-gradient-to-r" :class="b.color" :style="{ width: b.pct + '%' }"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============== PRICING ============== -->
        <section id="pricing" class="relative">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 pb-16 sm:pb-24 text-center">
                <div class="mono-pill mx-auto mb-5">{{ t('landing.pricing.pill') }}</div>
                <h2 class="text-[30px] sm:text-[36px] lg:text-[44px] leading-[1.1] sm:leading-[1.08] font-extrabold tracking-tight">{{ t('landing.pricing.heading') }}</h2>
                <p class="mt-5 max-w-2xl mx-auto text-ink-500">
                    {{ t('landing.pricing.body_a') }} <a href="#contact" class="text-brand-indigo font-medium hover:underline">{{ t('landing.pricing.body_link') }}</a>
                </p>

                <div class="mt-12 grid md:grid-cols-2 lg:grid-cols-4 gap-6 text-left">
                    <div v-for="p in plans" :key="p.slug"
                         class="relative rounded-2xl p-7 flex flex-col"
                         :class="p.popular ? 'glass-strong shadow-pricing-pop' : 'glass'">

                        <div v-if="p.popular"
                             class="absolute -top-3 right-7 px-3 py-1 rounded-full font-mono text-[10.5px] tracking-widest text-white shadow-cta"
                             style="background:linear-gradient(135deg,#3b82f6,#6366f1,#8b5cf6);">
                            {{ t('landing.pricing.most_popular') }}
                        </div>

                        <div class="mb-1 text-[20px] font-bold tracking-tight">{{ p.name }}</div>
                        <p class="text-[13.5px] text-ink-500 leading-relaxed mb-6 min-h-[42px]">{{ p.tagline }}</p>

                        <div v-if="p.free" class="flex items-baseline gap-1 mb-1">
                            <span class="text-[36px] sm:text-[44px] font-extrabold tracking-tight leading-none">৳0</span>
                            <span class="text-[14px] text-ink-500 ml-1">/ {{ p.unit }}</span>
                        </div>
                        <div v-else class="flex items-baseline gap-1 mb-1">
                            <span class="text-[20px] sm:text-[22px] text-ink-700 font-semibold">৳</span>
                            <span class="text-[36px] sm:text-[44px] font-extrabold tracking-tight leading-none">{{ p.price }}</span>
                            <span class="text-[14px] text-ink-500 ml-1">{{ p.unit }}</span>
                        </div>
                        <div class="font-mono text-[11.5px] text-ink-500 mb-6">{{ p.meta }}</div>

                        <ul class="space-y-3 mb-7 text-[14px]">
                            <li v-for="(b,i) in p.bullets" :key="i" class="flex gap-2.5"
                                :class="i === 0 && p.bullets_lead_idx === 0 ? 'font-semibold text-ink-900' : ''">
                                <svg class="h-4 w-4 mt-0.5 shrink-0 text-brand-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                                    <path d="M5 12l5 5L20 7"/>
                                </svg>
                                <span class="text-ink-700">{{ b }}</span>
                            </li>
                        </ul>

                        <Link :href="`/register?plan=${p.slug}`"
                              class="mt-auto block w-full py-3 rounded-xl text-[14px] font-medium text-center"
                              :class="p.popular ? 'btn-primary' : 'btn-ghost'">
                            {{ p.cta }}
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============== WHY ============== -->
        <section class="relative">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 pb-16 sm:pb-24 text-center">
                <div class="mono-pill mx-auto mb-5">{{ t('landing.why.pill') }}</div>
                <h2 class="text-[30px] sm:text-[36px] lg:text-[44px] leading-[1.1] sm:leading-[1.08] font-extrabold tracking-tight">
                    {{ t('landing.why.heading_a') }}<br/>{{ t('landing.why.heading_b') }}
                </h2>
                <p class="mt-5 max-w-2xl mx-auto text-ink-500">
                    {{ t('landing.why.body') }}
                </p>

                <div class="mt-12 grid md:grid-cols-2 lg:grid-cols-4 gap-5 text-left">
                    <div v-for="w in whyItems" :key="w.n" class="glass rounded-2xl p-6">
                        <div class="font-mono text-[12px] text-ink-500 mb-5">{{ w.n }}</div>
                        <h3 class="text-[16px] font-bold tracking-tight mb-3 leading-snug">{{ w.title }}</h3>
                        <p class="text-[13.5px] text-ink-500 leading-relaxed">{{ w.body }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============== TESTIMONIALS ============== -->
        <section class="relative">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 pb-16 sm:pb-24 text-center">
                <div class="mono-pill mx-auto mb-5">{{ t('landing.testimonials.pill') }}</div>
                <h2 class="text-[30px] sm:text-[36px] lg:text-[44px] leading-[1.1] sm:leading-[1.08] font-extrabold tracking-tight">
                    {{ t('landing.testimonials.heading_a') }}<br/>{{ t('landing.testimonials.heading_b') }}
                </h2>

                <div class="mt-12 grid md:grid-cols-3 gap-5 text-left">
                    <figure v-for="t in testimonials" :key="t.initials" class="glass rounded-2xl p-6 flex flex-col">
                        <blockquote class="text-[14.5px] text-ink-700 leading-relaxed flex-1">&ldquo;{{ t.quote }}&rdquo;</blockquote>
                        <figcaption class="mt-6 flex items-center gap-3">
                            <img v-if="t.photo" :src="t.photo" :alt="t.name" width="40" height="40" class="h-10 w-10 rounded-full object-cover border border-blue-300/30 shrink-0" loading="lazy" decoding="async" />
                            <div v-else class="avatar shrink-0">{{ t.initials }}</div>
                            <div>
                                <div class="text-[13.5px] font-semibold leading-tight">{{ t.name }}</div>
                                <div class="text-[12px] text-ink-500 leading-tight">{{ t.role }}</div>
                            </div>
                        </figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <!-- ============== FAQ ============== -->
        <section id="faq" class="relative">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 pb-16 sm:pb-24 text-center">
                <div class="mono-pill mx-auto mb-5">{{ t('landing.faq.pill') }}</div>
                <h2 class="text-[30px] sm:text-[36px] lg:text-[44px] leading-[1.1] sm:leading-[1.08] font-extrabold tracking-tight">{{ t('landing.faq.heading') }}</h2>

                <div class="mt-12 text-left">
                    <div v-for="(f,i) in faqs" :key="i" class="border-b border-ink-200/60">
                        <button class="w-full flex items-center justify-between py-5 text-left"
                                @click="toggleFaq(i)">
                            <span class="text-[16px] font-semibold tracking-tight">{{ f.q }}</span>
                            <span class="ml-4 grid place-items-center h-7 w-7 rounded-full transition-colors"
                                  :class="openFaq===i ? 'bg-brand-blue/10 text-brand-blue' : 'text-ink-400'">
                                <svg v-if="openFaq===i" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                                    <path d="M6 6l12 12M18 6L6 18"/>
                                </svg>
                                <span v-else class="text-[18px] leading-none">+</span>
                            </span>
                        </button>
                        <div v-show="openFaq===i" class="pb-5 text-[14px] leading-relaxed text-ink-500">
                            {{ f.a }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============== PAYMENTS ============== -->
        <section v-if="payments.length" class="relative">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 pb-16 sm:pb-24 text-center">
                <div class="mono-pill mx-auto mb-5">{{ t('landing.payments.pill') }}</div>
                <h2 class="text-[24px] sm:text-[32px] leading-[1.1] font-extrabold tracking-tight">{{ t('landing.payments.heading') }}</h2>

                <div class="mt-10 flex flex-wrap justify-center gap-3">
                    <span v-for="p in payments" :key="p.label" class="glass rounded-full px-5 py-2.5 text-[13.5px] flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full" :class="p.dot"></span>
                        {{ p.label }}
                    </span>
                </div>

                <p class="mt-6 font-mono text-[12.5px] text-ink-500">
                    {{ t('landing.payments.footer') }}
                </p>
            </div>
        </section>

        <!-- ============== FINAL CTA (DARK) ============== -->
        <section class="relative">
            <div class="mx-auto max-w-7xl px-6 pb-20">
                <div class="relative rounded-3xl overflow-hidden p-8 sm:p-12 md:p-16 text-center text-white"
                     style="background:linear-gradient(135deg,#0a0e27 0%,#1a1f3a 50%,#1a0f3a 100%);">
                    <div class="absolute inset-0 grid-dark-bg opacity-40 pointer-events-none"></div>
                    <div class="absolute -top-32 -right-32 w-96 h-96 rounded-full"
                         style="background:radial-gradient(circle,rgba(99,102,241,.3),transparent 70%);"></div>
                    <div class="absolute -bottom-32 -left-32 w-96 h-96 rounded-full"
                         style="background:radial-gradient(circle,rgba(139,92,246,.25),transparent 70%);"></div>

                    <div class="relative">
                        <div class="inline-flex items-center gap-2 rounded-full px-4 py-1.5 font-mono text-[11.5px] tracking-wide bg-white/5 border border-white/10 text-ink-200">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                            {{ t('landing.cta.free_pill') }}
                        </div>

                        <h2 class="mt-6 text-[30px] sm:text-[40px] md:text-[52px] leading-[1.1] sm:leading-[1.05] font-extrabold tracking-tight">
                            {{ t('landing.cta.heading_a') }}
                            <span class="bg-clip-text text-transparent" style="background-image:linear-gradient(90deg,#3b82f6,#8b5cf6);">{{ t('landing.cta.heading_grad') }}</span>
                        </h2>
                        <p class="mt-5 max-w-2xl mx-auto text-[15px] text-ink-300 leading-relaxed">
                            {{ t('landing.cta.body') }}
                        </p>

                        <div class="mt-8 flex flex-wrap justify-center gap-3">
                            <Link href="/register" class="btn-primary px-6">{{ t('nav.start_free') }} <span aria-hidden>→</span></Link>
                            <a href="#big-screen" class="btn-dark-ghost px-6">{{ t('nav.see_live_demo') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============== FOOTER ============== -->
        <PublicFooter />
    </div>
</template>
