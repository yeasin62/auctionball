<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Field from '@/Components/Field.vue';
import TextField from '@/Components/TextField.vue';
import CurrencyField from '@/Components/CurrencyField.vue';
import ImageCropper from '@/Components/ImageCropper.vue';
import Toggle from '@/Components/Toggle.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n, I18nT } from 'vue-i18n';
import { useFmt } from '@/composables/useFmt';
import { useConfirm, useAlert } from '@/composables/useConfirm';

const confirm = useConfirm();
const alertDialog = useAlert();

const { t } = useI18n();

const props = defineProps({
    season:        Object,
    teams:         Array,
    limits:        Object,
    used:          Number,
    pending_count: { type: Number, default: 0 },
});

const showCreate = ref(false);
const editingId  = ref(null);
const form = useForm({
    name: '',
    short_code: '',
    owner_name: '',
    initial_budget: props.season?.budget_per_team ?? 500000,
    logo: null,
});

const resetForm = () => {
    form.reset();
    form.initial_budget = props.season?.budget_per_team ?? 500000;
};
const startCreate = () => { editingId.value = null; resetForm(); showCreate.value = true; };
const startEdit = (team) => {
    editingId.value = team.id;
    form.name           = team.name ?? '';
    form.short_code     = team.short_code ?? '';
    form.owner_name     = team.owner_name ?? '';
    form.initial_budget = team.initial_budget ?? props.season?.budget_per_team ?? 500000;
    form.logo           = null;
    showCreate.value    = true;
    setTimeout(() => window.scrollTo({ top: 0, behavior: 'smooth' }), 50);
};
const cancelEdit = () => { showCreate.value = false; editingId.value = null; resetForm(); };

const submit = () => {
    if (editingId.value) {
        form.post(route('dashboard.teams.update', editingId.value), {
            onSuccess: () => { showCreate.value = false; editingId.value = null; resetForm(); },
            preserveScroll: true,
            forceFormData: true,
        });
    } else {
        form.post(route('dashboard.teams.store'), {
            onSuccess: () => { showCreate.value = false; resetForm(); },
            preserveScroll: true,
            forceFormData: true,
        });
    }
};

const remove = async (team) => {
    if (! await confirm({
        title: t('teams_page.confirm_delete_title', { name: team.name }),
        description: t('teams_page.confirm_delete_body'),
        variant: 'danger',
        confirmText: t('teams_page.delete_team'),
    })) return;
    router.delete(route('dashboard.teams.destroy', team.id), { preserveScroll: true });
};

const approve = (team) => router.post(route('dashboard.teams.approve', team.id), {}, { preserveScroll: true });
const reject = async (team) => {
    if (! await confirm({
        title: t('teams_page.confirm_reject_title', { name: team.name }),
        description: t('teams_page.confirm_reject_body'),
        variant: 'danger',
        confirmText: t('teams_page.reject_and_delete'),
    })) return;
    router.delete(route('dashboard.teams.reject', team.id), { preserveScroll: true });
};

// Registration link controls
const toggleRegistration = (open) => {
    useForm({
        open,
        team_registration_fee:          props.season?.team_registration_fee ?? 0,
        team_registration_instructions: props.season?.team_registration_instructions ?? '',
    }).post(route('dashboard.teams.registration'), { preserveScroll: true });
};
const updateRegistrationFee = (fee, instructions) => {
    useForm({
        open: !! props.season?.team_registration_open,
        team_registration_fee:          fee,
        team_registration_instructions: instructions,
    }).post(route('dashboard.teams.registration'), { preserveScroll: true });
};
const regenerateToken = async () => {
    if (! await confirm({
        title: t('teams_page.regenerate_link_title'),
        description: t('teams_page.regenerate_link_body'),
        variant: 'warning',
        confirmText: t('teams_page.regenerate_link_button'),
    })) return;
    useForm({}).post(route('dashboard.teams.registration.regenerate'), { preserveScroll: true });
};
const publicUrl = computed(() => props.season?.team_registration_token
    ? `${window.location.origin}/tr/${props.season.team_registration_token}`
    : null);
const copyLink = async () => {
    if (! publicUrl.value) return;
    await navigator.clipboard.writeText(publicUrl.value);
    alertDialog({ title: t('teams_page.copied_title'), description: t('teams_page.copied_body'), variant: 'info' });
};

const showRegistrationPanel = ref(false);

const fmt = useFmt().money;
const atLimit = computed(() => props.season && props.used >= props.limits.teams);
</script>

<template>
    <DashboardLayout :title="t('teams_page.title')">
        <template #actions>
            <a v-if="limits.export_csv" :href="route('dashboard.teams.export.csv')" class="btn-ghost py-2 px-4 text-[13px]">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 16V4m0 0l-4 4m4-4l4 4M4 16v4h16v-4"/></svg>
                {{ t('teams_page.export_csv') }}
            </a>
            <a v-if="limits.export_pdf" :href="route('dashboard.teams.export.pdf')" class="btn-ghost py-2 px-4 text-[13px]">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z"/><path d="M14 2v6h6"/></svg>
                {{ t('teams_page.export_pdf') }}
            </a>
            <button @click="showRegistrationPanel = !showRegistrationPanel"
                    class="btn-ghost py-2 px-4 text-[13px]"
                    :class="{ 'opacity-50 pointer-events-none': !season }"
                    :disabled="!season">
                {{ t('teams_page.public_registration') }}
                <span v-if="(season?.team_registration_open)" class="ml-1 px-1.5 py-0.5 rounded-full font-mono text-[9.5px] tracking-widest bg-emerald-50 text-emerald-700 border border-emerald-100">{{ t('teams_page.badge_open') }}</span>
            </button>
            <button @click="startCreate"
                    class="btn-primary py-2 px-4 text-[13px]"
                    :class="{ 'opacity-50 pointer-events-none': !season || atLimit }"
                    :disabled="!season || atLimit">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
                {{ t('teams_page.add_team') }}
            </button>
        </template>

        <div v-if="!season" class="glass rounded-2xl p-10 text-center">
            <p class="text-ink-500 text-[14px]">
                <I18nT keypath="teams_page.no_active_season_msg">
                    <template #link><Link href="/dashboard/seasons" class="text-ink-900 underline">{{ t('teams_page.create_one_link') }}</Link></template>
                </I18nT>
            </p>
        </div>

        <template v-else>
            <div v-if="atLimit" class="mb-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-2.5 text-[13px] text-amber-800">
                {{ t('teams_page.team_limit_reached') }} <strong>{{ used }} / {{ limits.teams }}</strong>
                <a href="/dashboard/billing" class="underline font-medium">{{ t('seasons_page.upgrade_to_add') }}</a>{{ t('seasons_page.at_limit_suffix') }}
            </div>

            <!-- Pending registrations banner -->
            <div v-if="pending_count > 0" class="mb-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 flex items-center justify-between text-[13px]">
                <span class="text-amber-800">
                    {{ t('teams_page.pending_review_msg', { count: pending_count }) }}
                </span>
            </div>

            <!-- Public registration panel -->
            <div v-if="showRegistrationPanel" class="glass-strong rounded-2xl p-6 mb-5 space-y-4">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('teams_page.section_team_reg') }}</div>
                        <p class="text-[13px] text-ink-600 mt-1 max-w-lg">
                            {{ t('teams_page.section_team_reg_subtitle') }}
                        </p>
                    </div>
                    <Toggle :model-value="!! season.team_registration_open"
                            @update:model-value="(v) => toggleRegistration(v)"
                            :on-label="t('teams_page.toggle_open')" :off-label="t('teams_page.toggle_closed')" />
                </div>

                <div v-if="season.team_registration_open && season.team_registration_token" class="rounded-xl bg-white/70 border border-ink-200/60 p-4">
                    <div class="font-mono text-[10.5px] tracking-widest text-ink-500 mb-1.5">{{ t('teams_page.shareable_url') }}</div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <code class="flex-1 font-mono text-[12.5px] truncate bg-white px-3 py-2 rounded-lg border border-ink-200 min-w-[200px]">
                            {{ publicUrl }}
                        </code>
                        <button @click="copyLink" class="btn-ghost py-2 px-3 text-[12px] whitespace-nowrap">{{ t('teams_page.copy') }}</button>
                        <a :href="publicUrl" target="_blank" class="btn-ghost py-2 px-3 text-[12px] whitespace-nowrap">{{ t('teams_page.open_url') }}</a>
                        <button @click="regenerateToken" class="text-[11px] text-rose-500 hover:text-rose-700 px-2 whitespace-nowrap">{{ t('teams_page.regenerate') }}</button>
                    </div>
                </div>

                <div v-if="season.team_registration_open" class="grid md:grid-cols-2 gap-4">
                    <Field :label="t('teams_page.registration_fee_label')">
                        <CurrencyField :modelValue="season.team_registration_fee"
                                       @update:modelValue="(v) => updateRegistrationFee(v, season.team_registration_instructions)" />
                    </Field>
                    <Field :label="t('teams_page.registration_instructions_label')">
                        <textarea :value="season.team_registration_instructions ?? ''"
                                  :placeholder="t('teams_page.registration_instructions_placeholder')"
                                  class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30"
                                  rows="2"
                                  @blur="(e) => updateRegistrationFee(season.team_registration_fee, e.target.value)"></textarea>
                    </Field>
                </div>
            </div>

            <!-- Create / Edit form -->
            <div v-if="showCreate" class="glass-strong rounded-2xl p-6 mb-5">
                <h3 class="text-[16px] font-bold tracking-tight mb-4">
                    {{ editingId ? t('teams_page.edit_team') : t('forms.teams.section_title') }}
                </h3>
                <form @submit.prevent="submit" class="grid md:grid-cols-3 gap-4">
                    <Field :label="t('forms.teams.name')" :error="form.errors.name" required>
                        <TextField v-model="form.name" :placeholder="t('forms.teams.name_placeholder')" autofocus />
                    </Field>
                    <Field :label="t('forms.teams.short_code')" :error="form.errors.short_code" :hint="t('forms.teams.short_hint')">
                        <TextField v-model="form.short_code" :placeholder="t('forms.teams.short_placeholder')" />
                    </Field>
                    <Field :label="t('teams_page.owner_name_label')" :error="form.errors.owner_name">
                        <TextField v-model="form.owner_name" :placeholder="t('teams_page.owner_name_placeholder')" />
                    </Field>
                    <Field :label="t('forms.teams.initial_budget')" :error="form.errors.initial_budget" required>
                        <CurrencyField v-model="form.initial_budget" />
                    </Field>
                    <div class="md:col-span-3 pt-2 border-t border-ink-200/60">
                        <ImageCropper :size="400" :label="t('teams_page.team_logo_label')" @update:file="form.logo = $event" />
                        <p v-if="form.errors.logo" class="mt-1.5 text-[12.5px] text-rose-500">{{ form.errors.logo }}</p>
                    </div>
                    <div class="md:col-span-3 flex gap-2 justify-end pt-1">
                        <button type="button" class="btn-ghost py-2 px-4 text-[13px]" @click="cancelEdit">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn-primary py-2 px-4 text-[13px]" :disabled="form.processing">
                            <template v-if="editingId">{{ form.processing ? t('teams_page.updating') : t('teams_page.update_team') }}</template>
                            <template v-else>{{ form.processing ? t('forms.teams.submitting') : t('forms.teams.submit') }}</template>
                        </button>
                    </div>
                </form>
            </div>

            <div v-if="teams.length" class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="team in teams" :key="team.id" class="glass rounded-2xl p-5"
                     :class="team.registration_status === 'pending' ? 'ring-2 ring-amber-300/70' : ''">
                    <div class="flex items-center gap-3 mb-4">
                        <img v-if="team.logo_url" :src="team.logo_url" :alt="team.name"
                             class="h-12 w-12 rounded-xl object-cover bg-white border border-ink-200" />
                        <div v-else class="h-12 w-12 rounded-xl bg-gradient-to-br from-cyan-200 to-violet-300 grid place-items-center font-mono text-[12px] font-bold text-indigo-700">
                            {{ team.short || team.name.slice(0,3).toUpperCase() }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[15px] font-bold tracking-tight truncate">{{ team.name }}</span>
                                <span v-if="team.registration_status === 'pending'"
                                      class="px-1.5 py-0.5 rounded-full font-mono text-[9px] tracking-widest bg-amber-50 text-amber-700 border border-amber-100">
                                    {{ t('teams_page.badge_pending') }}
                                </span>
                            </div>
                            <div class="text-[11.5px] font-mono text-ink-500">
                                <span v-if="team.short">{{ team.short }} · </span>{{ t('teams_page.n_players_bought', { count: team.players_count }) }}
                            </div>
                            <div v-if="team.owner_name" class="text-[10.5px] text-ink-500 truncate">{{ t('teams_page.owner_prefix', { name: team.owner_name }) }}</div>
                        </div>
                    </div>

                    <div class="space-y-2 text-[12.5px]">
                        <div class="flex justify-between"><span class="text-ink-500">{{ t('teams_page.stat_initial') }}</span><span class="font-mono font-semibold">{{ fmt(team.initial) }}</span></div>
                        <div class="flex justify-between"><span class="text-ink-500">{{ t('teams_page.stat_spent') }}</span><span class="font-mono font-semibold text-rose-600">- {{ fmt(team.spent) }}</span></div>
                        <div class="flex justify-between"><span class="text-ink-500">{{ t('teams_page.stat_remaining') }}</span><span class="font-mono font-semibold text-emerald-700">{{ fmt(team.remaining) }}</span></div>
                    </div>
                    <div class="mt-3">
                        <div class="bar-track"><div class="bar-fill" :style="{ width: team.pct + '%' }"></div></div>
                        <div class="mt-1 text-[10.5px] font-mono text-ink-400 text-right">{{ t('teams_page.pct_spent', { pct: team.pct }) }}</div>
                    </div>

                    <div v-if="team.registration_txn_id" class="mt-3 pt-3 border-t border-ink-200/50 text-[11px]">
                        <span class="text-ink-500">{{ t('teams_page.reg_trxid_label') }}</span>
                        <code class="ml-1 font-mono text-ink-700">{{ team.registration_txn_id }}</code>
                    </div>

                    <!-- Actions -->
                    <div class="mt-4 pt-3 border-t border-ink-100 flex gap-2 justify-end">
                        <template v-if="team.registration_status === 'pending'">
                            <button @click="reject(team)"  class="text-rose-500 hover:text-rose-700 text-[12px]">{{ t('teams_page.reject') }}</button>
                            <button @click="approve(team)" class="text-emerald-600 hover:text-emerald-700 text-[12px] font-medium">{{ t('teams_page.approve') }}</button>
                        </template>
                        <template v-else>
                            <button @click="startEdit(team)" class="text-brand-indigo hover:underline text-[12px]">{{ t('teams_page.edit') }}</button>
                            <button @click="remove(team)" class="text-rose-500 hover:text-rose-700 text-[12px]">{{ t('teams_page.delete') }}</button>
                        </template>
                    </div>
                </div>
            </div>
            <div v-else class="glass rounded-2xl p-10 text-center">
                <div class="font-mono text-[11px] tracking-widest text-ink-500 mb-3">{{ t('teams_page.no_teams_label') }}</div>
                <p class="text-ink-500 text-[14px]">{{ t('teams_page.no_teams_body') }}</p>
                <button @click="startCreate" class="btn-primary inline-flex mt-5 px-5">{{ t('teams_page.add_a_team') }}</button>
            </div>
        </template>
    </DashboardLayout>
</template>
