<script setup>
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useConfirm } from '@/composables/useConfirm';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const confirm = useConfirm();

const props = defineProps({
    orgs:    Object,                 // paginator
    filters: Object,
    plans:   { type: Array, default: () => [] },
});

const f = ref({ ...props.filters });
const apply = () => router.get(route('admin.orgs.index'), f.value, { preserveState: true });
const clear = () => { f.value = { q: '', plan: '' }; apply(); };

const setPlan = async (org, plan) => {
    if (plan === org.plan) return;
    if (! await confirm({
        title: t('super_admin.change_plan_title', { org: org.name }),
        description: t('super_admin.change_plan_body', { current: org.plan, next: plan }),
        variant: 'warning',
        confirmText: t('super_admin.switch_to_plan', { plan }),
    })) return;
    router.post(route('admin.orgs.set-plan', org.id), { plan }, { preserveScroll: true });
};

const impersonate = async (org) => {
    if (! await confirm({
        title: t('super_admin.impersonate_title', { org: org.name }),
        description: t('super_admin.impersonate_body'),
        variant: 'info',
        confirmText: t('super_admin.impersonate'),
    })) return;
    router.post(route('admin.orgs.impersonate', org.id), {}, { preserveScroll: false });
};

// ============== Manual subscription extender ==============
// Lets a super admin grant test / comp / trial access by setting an end date
// directly. Uses a dedicated inline modal because we need plan + date + note
// in one shot — not enough for the generic usePrompt() composable.
const extendOrg     = ref(null);             // holds the org while modal is open
const extendForm    = useForm({ plan: '', until: '', note: '' });

const todayPlus = (days) => {
    const d = new Date();
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
};

const openExtend = (org) => {
    extendOrg.value  = org;
    extendForm.plan  = org.plan;
    extendForm.until = org.sub_until || todayPlus(30);
    extendForm.note  = '';
    extendForm.clearErrors();
};

const closeExtend = () => { extendOrg.value = null; extendForm.reset(); };

const submitExtend = () => {
    extendForm.post(route('admin.orgs.extend-sub', extendOrg.value.id), {
        preserveScroll: true,
        onSuccess: () => closeExtend(),
    });
};

const todayIso = computed(() => new Date().toISOString().slice(0, 10));

const removeOrg = async (org) => {
    if (! await confirm({
        title: t('super_admin.permanently_delete_title', { name: org.name }),
        description: t('super_admin.permanently_delete_body'),
        variant: 'danger',
        confirmText: t('super_admin.orgs_delete_button'),
        typeToConfirm: org.slug,
    })) return;
    router.delete(route('admin.orgs.delete', org.id), { preserveScroll: true });
};

const planColor = (p) => ({
    free:       'bg-ink-100 text-ink-700 border-ink-200',
    starter:    'bg-blue-50 text-blue-700 border-blue-100',
    pro:        'bg-violet-50 text-violet-700 border-violet-100',
    enterprise: 'bg-amber-50 text-amber-800 border-amber-100',
}[p] || 'bg-ink-100 text-ink-500');

const subColor = (s) => ({
    active:    'text-emerald-700',
    past_due:  'text-amber-700',
    expired:   'text-rose-700',
    canceled:  'text-ink-500',
}[s] || 'text-ink-400');
</script>

<template>
    <Head :title="t('super_admin.head_orgs')" />
    <SuperAdminLayout :title="t('super_admin.orgs_title')">

        <!-- Filters -->
        <div class="glass rounded-2xl p-4 mb-4 flex flex-wrap items-center gap-2">
            <input v-model="f.q" @keyup.enter="apply"
                   :placeholder="t('super_admin.orgs_search_placeholder')"
                   class="flex-1 min-w-[220px] rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
            <select v-model="f.plan" @change="apply" class="rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 text-[13px] capitalize">
                <option value="">{{ t('super_admin.all_plans') }}</option>
                <option v-for="p in plans" :key="p" :value="p">{{ p }}</option>
            </select>
            <button @click="apply" class="btn-primary py-2 px-4 text-[13px]">{{ t('super_admin.users_apply') }}</button>
            <button @click="clear" class="btn-ghost py-2 px-3 text-[12px]">{{ t('super_admin.reset') }}</button>
            <span class="text-[12px] font-mono text-ink-500 ml-auto">{{ t('super_admin.n_total', { n: orgs.total }) }}</span>
        </div>

        <!-- Org table -->
        <div class="glass rounded-2xl overflow-hidden">
            <table class="w-full text-[13.5px]">
                <thead class="bg-white/40">
                    <tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                        <th class="px-4 py-2.5">{{ t('super_admin.th_org') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_owner_admin') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_plan') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.orgs_th_subscription') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.orgs_th_usage') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.orgs_th_domain') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_joined') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('super_admin.orgs_th_actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="o in orgs.data" :key="o.id" class="hover:bg-white/40">
                        <td class="px-4 py-2.5">
                            <div class="font-medium leading-tight">{{ o.name }}</div>
                            <div class="font-mono text-[10.5px] text-ink-500">{{ o.slug }}</div>
                        </td>
                        <td class="px-4 py-2.5">
                            <div v-if="o.owner_admin" class="leading-tight">
                                <div class="font-medium">{{ o.owner_admin.name }}</div>
                                <div class="font-mono text-[10.5px] text-ink-500">{{ o.owner_admin.email }}</div>
                            </div>
                            <div v-else class="text-ink-400 text-[12px]">—</div>
                        </td>
                        <td class="px-4 py-2.5">
                            <select :value="o.plan"
                                    @change="setPlan(o, $event.target.value)"
                                    class="rounded-lg border bg-white/80 px-2 py-1 text-[11.5px] font-mono uppercase capitalize tracking-wide"
                                    :class="planColor(o.plan)">
                                <option v-for="p in plans" :key="p" :value="p">{{ p }}</option>
                            </select>
                        </td>
                        <td class="px-4 py-2.5">
                            <div v-if="o.sub_status" class="leading-tight">
                                <div class="font-mono text-[11px] uppercase" :class="subColor(o.sub_status)">{{ o.sub_status }}</div>
                                <div class="font-mono text-[10.5px] text-ink-500">{{ t('super_admin.orgs_until', { provider: o.sub_provider, date: o.sub_until }) }}</div>
                            </div>
                            <div v-else class="text-ink-400 text-[12px]">{{ t('super_admin.no_active_sub') }}</div>
                            <button @click="openExtend(o)"
                                    class="mt-1 text-[11px] text-brand-indigo hover:underline">
                                {{ o.sub_status ? t('super_admin.change_end_date') : t('super_admin.grant_access') }}
                            </button>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-[11.5px] text-ink-700">
                            {{ t('super_admin.orgs_usage_short', { u: o.users_count, s: o.seasons_count, p: o.players_count }) }}
                        </td>
                        <td class="px-4 py-2.5">
                            <code v-if="o.custom_domain" class="font-mono text-[11px] text-ink-700">{{ o.custom_domain }}</code>
                            <span v-else class="text-ink-400 text-[12px]">—</span>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-[11.5px] text-ink-500">{{ o.created_at }}</td>
                        <td class="px-4 py-2.5 text-right whitespace-nowrap">
                            <button @click="impersonate(o)" class="text-[11.5px] text-brand-indigo hover:underline mr-3">{{ t('super_admin.impersonate') }}</button>
                            <button @click="removeOrg(o)" class="text-[11.5px] text-rose-500 hover:text-rose-700">{{ t('super_admin.delete') }}</button>
                        </td>
                    </tr>
                    <tr v-if="orgs.data.length === 0">
                        <td colspan="8" class="px-4 py-12 text-center text-[13.5px] text-ink-500">{{ t('super_admin.no_orgs_match') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="orgs.last_page > 1" class="mt-4 flex justify-center gap-1">
            <Link v-for="link in orgs.links" :key="link.label" :href="link.url || '#'" v-html="link.label"
                  class="px-3 py-1.5 rounded-lg text-[13px] font-mono"
                  :class="link.active ? 'bg-gradient-brand text-white' : link.url ? 'text-ink-700 hover:bg-white/60' : 'text-ink-300'"
                  :preserve-scroll="true" />
        </div>

        <!-- ============== Manual subscription extender modal ============== -->
        <Teleport to="body">
            <Transition name="extend-fade">
                <div v-if="extendOrg" class="fixed inset-0 z-[60] grid place-items-center bg-ink-900/55 backdrop-blur-sm p-4"
                     @click.self="closeExtend">
                    <div class="glass-strong rounded-2xl max-w-md w-full p-6 shadow-glass-lg">
                        <div class="flex items-start gap-4 mb-5">
                            <span class="grid place-items-center h-11 w-11 rounded-xl shrink-0 bg-blue-100">
                                <svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M8 7V3m8 4V3M3 11h18M5 6h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/></svg>
                            </span>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-[16px] font-bold tracking-tight">{{ t('super_admin.extend_heading') }}</h3>
                                <p class="mt-1 text-[13px] text-ink-600 leading-relaxed">
                                    {{ t('super_admin.extend_body', { name: extendOrg.name }) }}
                                </p>
                            </div>
                        </div>

                        <form @submit.prevent="submitExtend" class="space-y-3.5">
                            <div>
                                <label class="font-mono text-[10.5px] tracking-widest text-ink-500 mb-1 block">{{ t('super_admin.grant_plan') }}</label>
                                <select v-model="extendForm.plan"
                                        class="w-full rounded-xl border border-ink-200 bg-white px-3 py-2 text-[14px] capitalize focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                                    <option v-for="p in plans" :key="p" :value="p">{{ p }}</option>
                                </select>
                                <p v-if="extendForm.errors.plan" class="mt-1 text-[12px] text-rose-500">{{ extendForm.errors.plan }}</p>
                                <p class="mt-1 text-[11.5px] text-ink-500">
                                    {{ t('super_admin.extend_plan_help') }}
                                </p>
                            </div>

                            <div>
                                <label class="font-mono text-[10.5px] tracking-widest text-ink-500 mb-1 block">{{ t('super_admin.grant_active_until_label') }}</label>
                                <input v-model="extendForm.until" type="date" :min="todayIso"
                                       class="w-full rounded-xl border border-ink-200 bg-white px-3 py-2 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                                <p v-if="extendForm.errors.until" class="mt-1 text-[12px] text-rose-500">{{ extendForm.errors.until }}</p>
                                <div class="mt-2 flex gap-1.5">
                                    <button v-for="(d, label) in { [t('super_admin.preset_7d')]: 7, [t('super_admin.preset_30d')]: 30, [t('super_admin.preset_90d')]: 90, [t('super_admin.preset_1y')]: 365 }"
                                            :key="label"
                                            type="button"
                                            @click="extendForm.until = todayPlus(d)"
                                            class="text-[11px] font-mono px-2 py-1 rounded-md border border-ink-200 hover:bg-white text-ink-600">
                                        {{ label }}
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="font-mono text-[10.5px] tracking-widest text-ink-500 mb-1 block">{{ t('super_admin.grant_note') }} <span class="text-ink-400">{{ t('super_admin.grant_note_optional') }}</span></label>
                                <textarea v-model="extendForm.note" rows="2"
                                          :placeholder="t('super_admin.extend_note_placeholder')"
                                          class="w-full rounded-xl border border-ink-200 bg-white px-3 py-2 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30"></textarea>
                            </div>

                            <div class="flex gap-2 justify-end pt-2">
                                <button type="button" @click="closeExtend" class="btn-ghost py-2 px-4 text-[13px]">{{ t('common.cancel') }}</button>
                                <button type="submit" :disabled="extendForm.processing"
                                        class="btn-primary py-2 px-4 text-[13px]"
                                        :class="{ 'opacity-60 pointer-events-none': extendForm.processing }">
                                    {{ extendForm.processing ? t('super_admin.saving_dots') : t('super_admin.extend_button') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </SuperAdminLayout>
</template>

<style scoped>
.extend-fade-enter-active,
.extend-fade-leave-active { transition: opacity .2s ease; }
.extend-fade-enter-from,
.extend-fade-leave-to     { opacity: 0; }
</style>
