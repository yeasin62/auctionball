<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({ title: { type: String, default: 'Super admin' } });

const page = usePage();
const isActive = (re) => re.test(page.url);

const tabs = [
    { label: 'Overview',      href: '/admin',               match: /^\/admin\/?$/ },
    { label: 'Analytics',     href: '/admin/analytics',     match: /^\/admin\/analytics/ },
    { label: 'Organizations', href: '/admin/orgs',          match: /^\/admin\/orgs/ },
    { label: 'Users',         href: '/admin/users',         match: /^\/admin\/users/ },
    { label: 'Subscriptions', href: '/admin/subscriptions', match: /^\/admin\/subscriptions/ },
    { label: 'Payments',      href: '/admin/payments',      match: /^\/admin\/payments/ },
    { label: 'Plans',         href: '/admin/plans',         match: /^\/admin\/plans/ },
    { label: 'Audit log',     href: '/admin/audit',         match: /^\/admin\/audit/ },
];
</script>

<template>
    <DashboardLayout :title="title">
        <!-- Tab strip — full control surface for the platform -->
        <nav class="mb-5 flex flex-wrap items-center gap-1 p-1 rounded-xl bg-white/60 border border-ink-200/60 w-fit">
            <Link v-for="tab in tabs" :key="tab.href" :href="tab.href"
                  class="px-3.5 py-1.5 rounded-lg font-mono text-[12px] tracking-wide transition"
                  :class="isActive(tab.match)
                      ? 'bg-gradient-brand text-white shadow-cta'
                      : 'text-ink-600 hover:bg-white/80 hover:text-ink-900'">
                {{ tab.label }}
            </Link>
        </nav>

        <slot />
    </DashboardLayout>
</template>
