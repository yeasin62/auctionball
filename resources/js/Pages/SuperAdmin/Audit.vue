<script setup>
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({ logs: Object, filters: Object, event_counts: Object, orgs: Array });

const f = ref({ ...props.filters });
const expanded = ref(null);

const apply = () => router.get(route('admin.audit.index'), f.value, { preserveState: true });
const clear = () => { f.value = { q: '', event: '', org_id: '', date_from: '', date_to: '' }; apply(); };

const eventColor = (key) => ({
    'auction.sold':         'bg-emerald-50 text-emerald-700 border-emerald-100',
    'auction.unsold':       'bg-ink-100 text-ink-600 border-ink-200',
    'auction.reset':        'bg-amber-50 text-amber-700 border-amber-100',
    'plan.changed':         'bg-violet-50 text-violet-700 border-violet-100',
    'user.impersonated':    'bg-rose-50 text-rose-700 border-rose-100',
    'user.promoted':        'bg-violet-50 text-violet-700 border-violet-100',
    'user.demoted':         'bg-ink-100 text-ink-600 border-ink-200',
    'user.password_reset':  'bg-amber-50 text-amber-700 border-amber-100',
    'user.deleted':         'bg-rose-50 text-rose-700 border-rose-100',
    'payment.completed':    'bg-blue-50 text-blue-700 border-blue-100',
    'invitation.accepted':  'bg-cyan-50 text-cyan-700 border-cyan-100',
    'subscription.canceled':'bg-ink-100 text-ink-600 border-ink-200',
    'domain.set':           'bg-blue-50 text-blue-700 border-blue-100',
    'domain.verified':      'bg-emerald-50 text-emerald-700 border-emerald-100',
    'domain.removed':       'bg-ink-100 text-ink-600 border-ink-200',
}[key] || 'bg-ink-100 text-ink-500 border-ink-200');
</script>

<template>
    <SuperAdminLayout :title="t('super_admin.audit_head')">

        <!-- Event chips with counts -->
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <button @click="f.event = ''; apply()"
                    class="px-3 py-1.5 rounded-full text-[12px] font-mono border transition"
                    :class="!f.event ? 'bg-gradient-brand text-white border-transparent shadow-cta' : 'bg-white/70 text-ink-600 border-ink-200/60'">
                {{ t('super_admin.audit_all') }} <span class="ml-1 opacity-70">{{ Object.values(event_counts).reduce((a, b) => a + b, 0) }}</span>
            </button>
            <button v-for="(count, ev) in event_counts" :key="ev"
                    @click="f.event = (f.event === ev ? '' : ev); apply()"
                    class="px-3 py-1.5 rounded-full text-[11.5px] tracking-wide border transition"
                    :class="f.event === ev ? eventColor(ev) : 'bg-white/70 text-ink-600 border-ink-200/60'">
                {{ ev }} <span class="ml-1 font-mono opacity-70">{{ count }}</span>
            </button>
        </div>

        <div class="glass rounded-2xl p-4 mb-4 flex flex-wrap items-center gap-2">
            <input v-model="f.q" @keyup.enter="apply" :placeholder="t('super_admin.audit_search_summary')"
                   class="flex-1 min-w-[220px] rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
            <select v-model="f.org_id" @change="apply" class="rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 text-[13px]">
                <option value="">{{ t('super_admin.audit_all_orgs') }}</option>
                <option v-for="o in orgs" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
            <input v-model="f.date_from" @change="apply" type="date" class="rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 text-[13px]" />
            <input v-model="f.date_to"   @change="apply" type="date" class="rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 text-[13px]" />
            <button @click="clear" class="btn-ghost py-2 px-3 text-[12px]">{{ t('super_admin.reset') }}</button>
            <span class="text-[12px] font-mono text-ink-500 ml-auto">{{ t('super_admin.audit_n_entries', { n: logs.total }) }}</span>
        </div>

        <!-- Timeline -->
        <div v-if="logs.data.length" class="glass rounded-2xl overflow-hidden">
            <ul class="divide-y divide-ink-100">
                <li v-for="row in logs.data" :key="row.id" class="px-5 py-3 hover:bg-white/40">
                    <button class="w-full text-left flex items-start gap-3" @click="expanded = expanded === row.id ? null : row.id">
                        <div class="font-mono text-[10.5px] text-ink-500 w-36 shrink-0 pt-0.5">{{ row.created_at }}</div>
                        <span class="px-2 py-0.5 rounded-full font-mono text-[10.5px] uppercase border tracking-wider shrink-0" :class="eventColor(row.event)">{{ row.event }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="text-[13px] leading-snug">{{ row.summary }}</div>
                            <div class="mt-0.5 text-[11px] font-mono text-ink-500">
                                <span v-if="row.org">{{ row.org }} · </span>{{ row.actor_name }}
                                <span v-if="row.ip_address" class="text-ink-400"> · {{ row.ip_address }}</span>
                            </div>
                        </div>
                        <span v-if="row.payload" class="text-ink-400 text-[15px] shrink-0 transition" :class="{ 'rotate-90': expanded === row.id }">›</span>
                    </button>
                    <pre v-if="expanded === row.id && row.payload"
                         class="mt-2 ml-40 p-3 rounded-lg bg-white/60 border border-ink-200/60 text-[11.5px] font-mono text-ink-700 overflow-x-auto whitespace-pre-wrap">{{ JSON.stringify(row.payload, null, 2) }}</pre>
                </li>
            </ul>
        </div>
        <div v-else class="glass rounded-2xl p-10 text-center text-[14px] text-ink-500">
            {{ t('super_admin.audit_no_match') }}
        </div>

        <div v-if="logs.last_page > 1" class="mt-4 flex justify-center gap-1">
            <Link v-for="link in logs.links" :key="link.label" :href="link.url || '#'" v-html="link.label"
                  class="px-3 py-1.5 rounded-lg text-[13px] font-mono"
                  :class="link.active ? 'bg-gradient-brand text-white' : link.url ? 'text-ink-700 hover:bg-white/60' : 'text-ink-300'"
                  :preserve-scroll="true" />
        </div>
    </SuperAdminLayout>
</template>
