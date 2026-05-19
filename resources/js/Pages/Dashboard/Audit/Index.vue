<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    logs:       Object,
    filters:    Object,
    eventTypes: Object,
    counts:     Object,
});

const { locale } = useI18n();

const f = ref({ ...props.filters });
const expanded = ref(null);

const apply = () => router.get(route('dashboard.audit.index'), f.value, { preserveState: true });
const clear = () => { f.value = { q: '', event: '', date_from: '', date_to: '' }; apply(); };

const labelFor = (key) => {
    const t = props.eventTypes[key];
    if (! t) return key;
    return locale.value === 'bn' ? t.label_bn : t.label_en;
};

const eventColor = (key) => ({
    'auction.sold':        'bg-emerald-50 text-emerald-700 border-emerald-100',
    'auction.unsold':      'bg-ink-100 text-ink-600 border-ink-200',
    'auction.reset':       'bg-amber-50 text-amber-700 border-amber-100',
    'plan.changed':        'bg-violet-50 text-violet-700 border-violet-100',
    'user.impersonated':   'bg-rose-50 text-rose-700 border-rose-100',
    'payment.completed':   'bg-blue-50 text-blue-700 border-blue-100',
    'invitation.accepted': 'bg-cyan-50 text-cyan-700 border-cyan-100',
}[key] || 'bg-ink-100 text-ink-500 border-ink-200');

const eventTypesList = computed(() => Object.keys(props.eventTypes));
const total = computed(() => Object.values(props.counts).reduce((a, b) => a + b, 0));
</script>

<template>
    <DashboardLayout :title="t('sidebar.audit_log')">

        <!-- Filter chips with counts -->
        <div class="glass rounded-2xl p-4 mb-4">
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <button @click="f.event = ''; apply()"
                        class="px-3 py-1.5 rounded-full text-[12px] font-mono tracking-wide border transition"
                        :class="!f.event ? 'bg-gradient-brand text-white border-transparent shadow-cta' : 'bg-white/70 text-ink-600 border-ink-200/60'">
                    {{ locale === 'bn' ? 'সব' : 'All' }}
                    <span class="ml-1 opacity-70">{{ total }}</span>
                </button>
                <button v-for="e in eventTypesList" :key="e"
                        @click="f.event = (f.event === e ? '' : e); apply()"
                        class="px-3 py-1.5 rounded-full text-[12px] tracking-wide border transition"
                        :class="f.event === e ? eventColor(e) : 'bg-white/70 text-ink-600 border-ink-200/60'">
                    {{ labelFor(e) }}
                    <span class="ml-1 font-mono opacity-70">{{ counts[e] || 0 }}</span>
                </button>
            </div>

            <div class="flex flex-wrap gap-2">
                <input v-model="f.q" @keyup.enter="apply"
                       :placeholder="locale === 'bn' ? 'অনুসন্ধান (সারাংশ বা actor)…' : 'Search summary or actor…'"
                       class="flex-1 min-w-[200px] rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                <input v-model="f.date_from" @change="apply" type="date" class="rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 text-[13px]" />
                <input v-model="f.date_to"   @change="apply" type="date" class="rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 text-[13px]" />
                <button @click="clear" class="btn-ghost py-2 px-3 text-[12px]">{{ locale === 'bn' ? 'রিসেট' : 'Reset' }}</button>
            </div>
        </div>

        <!-- Timeline -->
        <div v-if="logs.data.length" class="glass rounded-2xl overflow-hidden">
            <ul class="divide-y divide-ink-100">
                <li v-for="row in logs.data" :key="row.id" class="px-5 py-3.5 hover:bg-white/40 transition">
                    <button class="w-full text-left flex items-start gap-4" @click="expanded = expanded === row.id ? null : row.id">
                        <div class="font-mono text-[11px] text-ink-500 w-32 shrink-0 leading-tight pt-0.5">
                            {{ row.created_at?.replace('T', ' ').slice(0, 19) }}
                        </div>
                        <span class="px-2 py-0.5 rounded-full font-mono text-[10.5px] uppercase border tracking-wider shrink-0" :class="eventColor(row.event)">
                            {{ row.event }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="text-[13.5px] leading-snug">{{ row.summary }}</div>
                            <div class="mt-1 text-[11.5px] font-mono text-ink-500">
                                {{ row.actor_name }}
                                <span v-if="row.ip_address" class="text-ink-400">· {{ row.ip_address }}</span>
                            </div>
                        </div>
                        <span v-if="row.payload" class="text-ink-400 text-[16px] shrink-0 transition" :class="{ 'rotate-90': expanded === row.id }">›</span>
                    </button>

                    <pre v-if="expanded === row.id && row.payload"
                         class="mt-3 ml-36 p-3 rounded-lg bg-white/60 border border-ink-200/60 text-[11.5px] font-mono text-ink-700 overflow-x-auto whitespace-pre-wrap">{{ JSON.stringify(row.payload, null, 2) }}</pre>
                </li>
            </ul>
        </div>
        <div v-else class="glass rounded-2xl p-10 text-center">
            <div class="font-mono text-[11px] tracking-widest text-ink-500 mb-3">
                {{ locale === 'bn' ? '/ কোনো অডিট ইভেন্ট নেই' : '/ no audit events' }}
            </div>
            <p class="text-ink-500 text-[14px]">
                {{ locale === 'bn'
                    ? 'এই অর্গানাইজেশনে কোনো ট্র্যাক করা ইভেন্ট ঘটেনি যা ফিল্টারের সাথে মেলে। নিলাম শুরু করলে এখানে log হবে।'
                    : 'No tracked events match the current filters. Run an auction or perform an admin action — entries land here as they happen.' }}
            </p>
        </div>

        <!-- Pagination -->
        <div v-if="logs.last_page > 1" class="mt-4 flex justify-center gap-1">
            <Link v-for="link in logs.links" :key="link.label" :href="link.url || '#'" v-html="link.label"
                  class="px-3 py-1.5 rounded-lg text-[13px] font-mono"
                  :class="link.active ? 'bg-gradient-brand text-white' : link.url ? 'text-ink-700 hover:bg-white/60' : 'text-ink-300'"
                  :preserve-scroll="true" />
        </div>
    </DashboardLayout>
</template>
