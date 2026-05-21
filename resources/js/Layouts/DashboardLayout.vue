<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted, onBeforeUnmount, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import LanguageToggle from '@/Components/LanguageToggle.vue';

defineProps({ title: String });

const { t } = useI18n();
const page = usePage();
const user          = computed(() => page.props.auth?.user);
const currentOrg    = computed(() => page.props.currentOrg);
const role          = computed(() => page.props.auth?.role);
const appDomain     = computed(() => page.props.appDomain || 'auctionball.com');
const appLogo       = computed(() => page.props.appLogo);
const isSuperAdmin  = computed(() => page.props.auth?.user?.is_super_admin);
const impersonating = computed(() => page.props.auth?.impersonating);

const userMenuOpen = ref(false);
const sidebarOpen  = ref(false);

const stopImpersonating = () => router.post(route('admin.stop-impersonating'));

// Sidebar nav — labels are i18n keys; we render `t(item.label)` below.
// team_owner role gets a minimal sidebar (only "Place bids") so the dashboard
// doesn't surface admin tooling they can't use.
const isTeamOwner = computed(() => role.value === 'team_owner');

const navGroups = computed(() => {
    if (isTeamOwner.value) {
        return [
            {
                title: '',
                items: [
                    { label: 'sidebar.place_bids', href: '/bid', icon: 'play', match: /^\/bid/, accent: true },
                ],
            },
        ];
    }

    // On /admin/* paths, hide org-scoped nav so the super-admin sidebar shows
    // ONLY platform tools — no Seasons/Players/Teams leaking into the platform
    // panel. They can still hop back to org work via "Back to dashboard" below.
    if (page.url.startsWith('/admin')) return [];

    // No org attached — hide org-scoped nav (super-admins-only mode).
    // The platform group below still surfaces /admin so they can navigate.
    if (! currentOrg.value) return [];

    return [
        {
            title: '',
            items: [
                { label: 'sidebar.dashboard',     href: '/dashboard',           icon: 'home',     match: /^\/dashboard\/?$/ },
            ],
        },
        {
            title: t('sidebar.auction_group'),
            items: [
                { label: 'sidebar.seasons',       href: '/dashboard/seasons',   icon: 'calendar', match: /^\/dashboard\/seasons/ },
                { label: 'sidebar.players',       href: '/dashboard/players',   icon: 'user',     match: /^\/dashboard\/players/ },
                { label: 'sidebar.teams',         href: '/dashboard/teams',     icon: 'users',    match: /^\/dashboard\/teams/ },
                { label: 'sidebar.live_auction',  href: '/dashboard/auction',   icon: 'play',     match: /^\/dashboard\/auction/, accent: true },
                { label: 'sidebar.big_screen',    href: '/dashboard/bigscreen', icon: 'monitor',  match: /^\/dashboard\/bigscreen/ },
                { label: 'sidebar.rosters',       href: '/dashboard/rosters',   icon: 'team',     match: /^\/dashboard\/rosters/ },
                { label: 'sidebar.analytics',     href: '/dashboard/analytics', icon: 'chart',    match: /^\/dashboard\/analytics/ },
                { label: 'sidebar.audit_log',     href: '/dashboard/audit',     icon: 'audit',    match: /^\/dashboard\/audit/ },
            ],
        },
        {
            title: t('sidebar.org_group'),
            items: [
                { label: 'sidebar.users',    href: '/dashboard/users',    icon: 'team', match: /^\/dashboard\/users/ },
                { label: 'sidebar.settings', href: '/dashboard/settings', icon: 'cog',  match: /^\/dashboard\/settings/ },
                { label: 'sidebar.billing',  href: '/dashboard/billing',  icon: 'card', match: /^\/dashboard\/billing/ },
            ],
        },
    ];
});

// Platform-scoped nav for super admins. Labels are literal here (no i18n key)
// since they target the SuperAdmin/* pages — vue-i18n's t() returns the raw
// string when no matching key exists, so this is safe.
// Pending-payments badge — starts from the Inertia shared prop on page load,
// then re-hydrates from the `super-admin` Echo channel so a new bKash submission
// bumps the badge live without a navigation.
const pendingPaymentsCount = ref(Number(page.props.pendingPaymentsCount || 0));
// Inertia page changes re-share the prop; keep the local ref in sync so a
// regular navigation also corrects any drift if the WebSocket missed an event.
watch(() => page.props.pendingPaymentsCount, (v) => {
    pendingPaymentsCount.value = Number(v || 0);
});

let paymentsChannel = null;
onMounted(() => {
    if (! isSuperAdmin.value || ! window.Echo) return;
    paymentsChannel = window.Echo.private('super-admin')
        .listen('.pending-payments.changed', (payload) => {
            if (payload && typeof payload.count === 'number') {
                pendingPaymentsCount.value = payload.count;
            }
        });
});
onBeforeUnmount(() => {
    if (window.Echo) window.Echo.leave('super-admin');
    paymentsChannel = null;
});

const adminNav = computed(() => {
    const items = [
        { label: 'Overview',      href: '/admin',               icon: 'shield',   match: /^\/admin\/?$/ },
        { label: 'Analytics',     href: '/admin/analytics',     icon: 'chart',    match: /^\/admin\/analytics/ },
        { label: 'Organizations', href: '/admin/orgs',          icon: 'team',     match: /^\/admin\/orgs/ },
        { label: 'Users',         href: '/admin/users',         icon: 'user',     match: /^\/admin\/users/ },
        { label: 'Subscriptions', href: '/admin/subscriptions', icon: 'card',     match: /^\/admin\/subscriptions/ },
        { label: 'Payments',      href: '/admin/payments',      icon: 'card',     match: /^\/admin\/payments/,
          badge: pendingPaymentsCount.value > 0 ? pendingPaymentsCount.value : null },
        { label: 'Integrations',  href: '/admin/integrations',  icon: 'plug',     match: /^\/admin\/integrations/ },
        { label: 'Content',       href: '/admin/content',       icon: 'content',  match: /^\/admin\/content/ },
        { label: 'Advanced',      href: '/admin/advanced',      icon: 'cog',      match: /^\/admin\/advanced/ },
        { label: 'Plans',         href: '/admin/plans',         icon: 'cog',      match: /^\/admin\/plans/ },
        { label: 'Audit log',     href: '/admin/audit',         icon: 'audit',    match: /^\/admin\/audit/ },
    ];
    // When on /admin/* and the super admin also has an org, surface a quick
    // "Back to dashboard" link so they can hop out without typing the URL.
    if (page.url.startsWith('/admin') && currentOrg.value) {
        items.push({ label: '← Back to dashboard', href: '/dashboard', icon: 'home', match: /^\/dashboard\/?$/ });
    }
    return items;
});

const isActive = (item) => item.match.test(page.url);

const logout = () => router.post(route('logout'));
</script>

<template>
    <div class="page-bg min-h-screen flex flex-col">
        <!-- Impersonation banner -->
        <div v-if="impersonating"
             class="bg-amber-100 border-b border-amber-300 px-4 py-2 flex items-center justify-between text-[12.5px] text-amber-900">
            <span class="font-mono">
                ⚠️ Impersonating <strong>{{ user?.name }}</strong> @ <strong>{{ currentOrg?.name }}</strong>
            </span>
            <button @click="stopImpersonating" class="font-medium hover:underline">Stop →</button>
        </div>
        <div class="flex flex-1 min-h-0">

        <!-- ============== SIDEBAR ============== -->
        <aside class="hidden lg:flex w-64 shrink-0 flex-col border-r border-ink-200/60 bg-white/40 backdrop-blur-md">
            <div class="px-5 py-5">
                <Link href="/" class="inline-flex items-center gap-2.5">
                    <img v-if="appLogo" :src="appLogo" alt="AuctionBall" class="h-9 w-9 rounded-lg object-contain bg-white border border-ink-200/40" />
                    <span v-else class="grid place-items-center h-9 w-9 rounded-lg bg-gradient-brand shadow-cta">
                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                            <rect x="4" y="4" width="16" height="16" rx="3"/>
                            <path d="M8 12h8M8 8h5"/>
                        </svg>
                    </span>
                    <span class="font-semibold text-[16px] tracking-tight">AuctionBall</span>
                </Link>
            </div>

            <!-- Org card -->
            <div v-if="currentOrg" class="mx-3 mb-3 px-3 py-2.5 rounded-xl bg-white/70 border border-white/80">
                <div class="flex items-center gap-2.5">
                    <div class="h-8 w-8 rounded-lg bg-gradient-to-br from-cyan-200 to-violet-300 grid place-items-center font-mono text-[11px] font-bold text-indigo-700 shrink-0">
                        {{ currentOrg.name?.[0]?.toUpperCase() }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-[13px] font-semibold truncate leading-tight">{{ currentOrg.name }}</div>
                        <div class="text-[10.5px] font-mono text-ink-500 truncate">{{ currentOrg.slug }}.{{ appDomain }}</div>
                    </div>
                </div>
                <div class="mt-2 flex items-center justify-between">
                    <span class="text-[10.5px] font-mono uppercase tracking-widest text-ink-500">{{ currentOrg.plan }} {{ t('sidebar.plan_suffix') }}</span>
                    <Link href="/dashboard/billing" class="text-[10.5px] font-mono text-brand-indigo hover:underline">{{ t('sidebar.upgrade') }}</Link>
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex-1 px-3 space-y-5 mt-1 overflow-y-auto">
                <div v-for="g in [...navGroups, ...(isSuperAdmin ? [{ title: t('sidebar.platform_group'), items: adminNav }] : [])]" :key="g.title || 'top'">
                    <div v-if="g.title" class="px-3 pb-1.5 font-mono text-[10px] tracking-widest text-ink-400">{{ g.title }}</div>
                    <ul class="space-y-0.5">
                        <li v-for="item in g.items" :key="item.href">
                            <Link :href="item.href"
                                  class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13.5px] transition"
                                  :class="isActive(item)
                                      ? 'bg-white shadow-card text-ink-900 font-semibold'
                                      : 'text-ink-600 hover:bg-white/60 hover:text-ink-900'">
                                <span class="grid place-items-center h-5 w-5"
                                      :class="isActive(item) ? 'text-brand-indigo' : 'text-ink-400'">
                                    <svg v-if="item.icon==='home'"     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4.5 w-4.5"><path d="M3 11l9-8 9 8v10a1 1 0 01-1 1h-5v-7H10v7H4a1 1 0 01-1-1V11z"/></svg>
                                    <svg v-else-if="item.icon==='calendar'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4.5 w-4.5"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                                    <svg v-else-if="item.icon==='user'"     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4.5 w-4.5"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                                    <svg v-else-if="item.icon==='users'"    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4.5 w-4.5"><circle cx="9" cy="8" r="3"/><path d="M3 21c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="8" r="2.5"/><path d="M17 13c2.8 0 5 2.2 5 5"/></svg>
                                    <svg v-else-if="item.icon==='play'"     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4.5 w-4.5"><path d="M6 4l14 8-14 8V4z"/></svg>
                                    <svg v-else-if="item.icon==='monitor'"  viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4.5 w-4.5"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M9 21h6M12 17v4"/></svg>
                                    <svg v-else-if="item.icon==='team'"     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4.5 w-4.5"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="8" r="2.5"/><path d="M3 21c0-3 2.7-6 6-6s6 3 6 6M17 13c2.8 0 5 2.2 5 5"/></svg>
                                    <svg v-else-if="item.icon==='cog'"      viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4.5 w-4.5"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06A2 2 0 114.27 16.96l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09a1.65 1.65 0 001.51-1 1.65 1.65 0 00-.33-1.82l-.06-.06A2 2 0 117.04 4.27l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06A2 2 0 1119.73 7.04l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                                    <svg v-else-if="item.icon==='card'"     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4.5 w-4.5"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/></svg>
                                    <svg v-else-if="item.icon==='chart'"    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4.5 w-4.5"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>
                                    <svg v-else-if="item.icon==='shield'"   viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4.5 w-4.5"><path d="M12 2l8 4v6c0 5-3.5 9-8 10-4.5-1-8-5-8-10V6l8-4z"/></svg>
                                    <svg v-else-if="item.icon==='plug'"     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4.5 w-4.5"><path d="M12 22v-5M9 8V3M15 8V3M6 8h12v4a6 6 0 01-12 0V8z"/></svg>
                                    <svg v-else-if="item.icon==='content'"  viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4.5 w-4.5"><path d="M4 5h16M4 12h10M4 19h16"/><path d="M18 10v4M16 12h4"/></svg>
                                    <svg v-else-if="item.icon==='audit'"    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4.5 w-4.5"><path d="M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                                </span>
                                <span class="flex-1 truncate">{{ t(item.label) }}</span>
                                <span v-if="item.badge"
                                      class="px-1.5 min-w-[18px] h-[18px] rounded-full bg-rose-500 text-white font-mono text-[10.5px] font-bold tracking-tight grid place-items-center leading-none">
                                    {{ item.badge }}
                                </span>
                                <span v-else-if="item.accent" class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="p-3 border-t border-ink-200/60">
                <Link href="/help" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] text-ink-500 hover:bg-white/60">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><circle cx="12" cy="12" r="10"/><path d="M9 9a3 3 0 116 0c0 1.5-3 2-3 4M12 17v.01"/></svg>
                    {{ t('sidebar.help_docs') }}
                </Link>
            </div>
        </aside>

        <!-- ============== MAIN ============== -->
        <div class="flex-1 min-w-0 flex flex-col">

            <!-- Topbar -->
            <header class="sticky top-0 z-20 backdrop-blur-md bg-white/40 border-b border-ink-200/60 px-6 py-3 flex items-center gap-4">
                <button class="lg:hidden p-2 -ml-2 text-ink-700" @click="sidebarOpen = !sidebarOpen">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
                </button>

                <h1 class="text-[18px] font-bold tracking-tight">{{ title }}</h1>

                <div class="ml-auto flex items-center gap-2">
                    <div v-if="$slots.actions" class="flex items-center gap-2">
                        <slot name="actions" />
                    </div>

                    <LanguageToggle />

                    <!-- User menu -->
                    <div class="relative">
                        <button class="flex items-center gap-2.5 px-2 py-1.5 rounded-lg hover:bg-white/60"
                                @click="userMenuOpen = !userMenuOpen">
                            <img v-if="user?.avatar_url" :src="user.avatar_url" :alt="user.name" class="h-8 w-8 rounded-full border border-white/80 object-cover shadow-sm" />
                            <div v-else class="h-8 w-8 rounded-full bg-gradient-to-br from-cyan-200 to-indigo-300 grid place-items-center font-mono text-[11px] font-bold text-indigo-700">
                                {{ user?.name?.[0]?.toUpperCase() }}
                            </div>
                            <div class="hidden md:block text-left leading-tight">
                                <div class="text-[12.5px] font-semibold">{{ user?.name }}</div>
                                <div class="text-[10.5px] font-mono text-ink-500">{{ role }}</div>
                            </div>
                        </button>
                        <div v-if="userMenuOpen"
                             class="absolute right-0 mt-2 w-48 glass-strong rounded-xl py-1.5 shadow-glass-lg z-30">
                            <Link :href="route('profile.edit')" class="block px-4 py-2 text-[13px] hover:bg-white/60">{{ t('sidebar.profile') }}</Link>
                            <Link href="/dashboard/settings" class="block px-4 py-2 text-[13px] hover:bg-white/60">{{ t('sidebar.settings') }}</Link>
                            <button @click="logout" class="block w-full text-left px-4 py-2 text-[13px] text-rose-600 hover:bg-rose-50">{{ t('nav.log_out') }}</button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Mobile drawer -->
            <div v-if="sidebarOpen" class="lg:hidden fixed inset-0 z-40">
                <div class="absolute inset-0 bg-ink-900/30" @click="sidebarOpen = false"></div>
                <aside class="absolute inset-y-0 left-0 w-72 bg-white p-4 shadow-xl overflow-y-auto">
                    <div class="space-y-4">
                        <div v-for="g in navGroups" :key="g.title || 'top'">
                            <div v-if="g.title" class="px-3 pb-1.5 font-mono text-[10px] tracking-widest text-ink-400">{{ g.title }}</div>
                            <ul class="space-y-0.5">
                                <li v-for="item in g.items" :key="item.href">
                                    <Link :href="item.href" @click="sidebarOpen = false"
                                          class="block px-3 py-2 rounded-lg text-[13.5px]"
                                          :class="isActive(item) ? 'bg-ink-100 font-semibold' : 'text-ink-600'">
                                        {{ t(item.label) }}
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </div>
                </aside>
            </div>

            <!-- Flash -->
            <div v-if="$page.props.flash?.success"
                 class="mx-6 mt-4 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-[13px] text-emerald-800">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error"
                 class="mx-6 mt-4 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-[13px] text-rose-800">
                {{ $page.props.flash.error }}
            </div>
            <div v-if="$page.props.flash?.warning"
                 class="mx-6 mt-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-[13px] text-amber-800">
                {{ $page.props.flash.warning }}
            </div>

            <main class="flex-1 px-6 py-6">
                <slot />
            </main>
        </div>
        </div>
    </div>
</template>
