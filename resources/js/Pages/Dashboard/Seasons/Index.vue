<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Field from '@/Components/Field.vue';
import TextField from '@/Components/TextField.vue';
import CurrencyField from '@/Components/CurrencyField.vue';
import Toggle from '@/Components/Toggle.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useFmt } from '@/composables/useFmt';
import { useConfirm, useAlert, usePrompt } from '@/composables/useConfirm';

const confirm = useConfirm();
const alertDialog = useAlert();
const promptDialog = usePrompt();

const { t } = useI18n();

const props = defineProps({
    seasons: Array,
    limits:  Object,
    used:    Number,
});

const showCreate = ref(false);
const expandedRegistration = ref(null);
const expandedForm = ref(null);   // form-builder accordion: separate from registration block
const expandedCategories = ref(null);
const expandedEdit = ref(null);
const categoriesState = ref({});  // seasonId → draft array of {name, base_price}
const editState = ref({});        // seasonId → draft of editable season fields

const ensureEdit = (season) => {
    if (! editState.value[season.id]) {
        editState.value[season.id] = {
            name:            season.name,
            year:            season.year,
            sport:           season.sport || 'cricket',
            budget_per_team: season.budget_per_team,
            start_date:      season.start_date || '',
            end_date:        season.end_date || '',
        };
    }
    return editState.value[season.id];
};
const resetEditDraft = (season) => {
    editState.value[season.id] = null;
    ensureEdit(season);
};
const saveEdit = (season) => {
    const d = ensureEdit(season);
    const payload = {
        name:            (d.name || '').trim(),
        year:            parseInt(d.year, 10),
        sport:           d.sport,
        budget_per_team: parseInt(d.budget_per_team, 10) || 0,
        start_date:      d.start_date || null,
        end_date:        d.end_date || null,
    };
    router.patch(route('dashboard.seasons.update', season.id), payload, {
        preserveScroll: true,
        onSuccess: () => { expandedEdit.value = null; editState.value[season.id] = null; },
    });
};

const ensureCategories = (season) => {
    if (! categoriesState.value[season.id]) {
        categoriesState.value[season.id] = JSON.parse(JSON.stringify(season.player_categories || []));
    }
    return categoriesState.value[season.id];
};
const addCategory = (season) => {
    ensureCategories(season).push({ name: '', base_price: 10000 });
};
const removeCategory = (season, idx) => {
    ensureCategories(season).splice(idx, 1);
};
const resetCategoriesDraft = (season) => {
    categoriesState.value[season.id] = JSON.parse(JSON.stringify(season.player_categories || []));
};
const saveCategories = (season) => {
    const cats = ensureCategories(season)
        .map(c => ({ name: (c.name || '').trim(), base_price: parseInt(c.base_price, 10) || 0 }))
        .filter(c => c.name);
    if (! cats.length) {
        alertDialog({ title: 'At least one category is required.', variant: 'warning' });
        return;
    }
    useForm({ categories: cats }).post(route('dashboard.seasons.categories', season.id), { preserveScroll: true });
};

const form = useForm({
    name: '',
    year: new Date().getFullYear(),
    sport: 'cricket',
    budget_per_team: 500000,
    bid_increment: 1000,
    bid_increment_usd: 10,
    start_date: '',
    end_date: '',
});

const submit = () => form.post(route('dashboard.seasons.store'), {
    onSuccess: () => { showCreate.value = false; form.reset(); },
});

const activate = async (season) => {
    if (! await confirm({
        title: `Make "${season.name}" the active season?`,
        description: 'The currently active season (if any) will be deactivated. Players, teams, and the auction will switch to this season.',
        variant: 'warning',
        confirmText: 'Set active',
    })) return;
    useForm({}).post(route('dashboard.seasons.activate', season.id));
};

const deactivate = async (season) => {
    if (! await confirm({
        title: `Deactivate "${season.name}"?`,
        description: 'The season is kept (with all its players, teams, and bid history) but the dashboard will show no active season — live auction, big screen, and bidding pages will show "No active season" until you activate one.',
        variant: 'warning',
        confirmText: 'Set inactive',
    })) return;
    useForm({}).post(route('dashboard.seasons.deactivate', season.id), { preserveScroll: true });
};

const toggleReg = (season, payload) => {
    useForm(payload).post(route('dashboard.seasons.registration', season.id), { preserveScroll: true });
};

const deleteSeason = async (season) => {
    // Build a description that names what's about to be wiped so the admin
    // sees the cascade impact before they confirm.
    const parts = [];
    if (season.players_count > 0) parts.push(`${season.players_count} players`);
    if (season.teams_count   > 0) parts.push(`${season.teams_count} teams`);
    if (season.bids_count    > 0) parts.push(`${season.bids_count} bids`);
    const cascade = parts.length
        ? `Will permanently delete ${parts.join(', ')} and all auction state for this season. This cannot be undone.`
        : 'Will permanently delete this season. This cannot be undone.';

    if (! await confirm({
        title: `Delete season "${season.name}"?`,
        description: cascade,
        variant: 'danger',
        confirmText: 'Delete season',
    })) return;

    router.delete(route('dashboard.seasons.destroy', season.id), { preserveScroll: true });
};

const regenerate = async (season) => {
    if (! await confirm({
        title: 'Generate a new registration link?',
        description: 'The current public registration URL will stop working immediately. Anyone who has already submitted is unaffected — only future visitors with the old link will see a 404.',
        variant: 'warning',
        confirmText: 'Generate new link',
    })) return;
    useForm({}).post(route('dashboard.seasons.registration.regenerate', season.id), { preserveScroll: true });
};

const changeIncrement = async (season) => {
    const raw = await promptDialog({
        title: `BDT bid step for "${season.name}"`,
        description: 'Each new bid must exceed the current by at least this many ৳.',
        defaultValue: season.bid_increment || 1000,
        inputType: 'number',
        inputMin: 1,
        confirmText: 'Save',
    });
    if (raw === null) return;
    const v = parseInt(raw, 10);
    if (! Number.isFinite(v) || v < 1) {
        await alertDialog({ title: 'Invalid value', description: 'Enter a positive integer.', variant: 'warning' });
        return;
    }
    router.patch(route('dashboard.seasons.update', season.id), { bid_increment: v }, { preserveScroll: true });
};

const changeIncrementUsd = async (season) => {
    const raw = await promptDialog({
        title: `USD bid step for "${season.name}"`,
        description: 'Each new bid must exceed the current by at least this many $.',
        defaultValue: season.bid_increment_usd || 10,
        inputType: 'number',
        inputMin: 1,
        confirmText: 'Save',
    });
    if (raw === null) return;
    const v = parseInt(raw, 10);
    if (! Number.isFinite(v) || v < 1) {
        await alertDialog({ title: 'Invalid value', description: 'Enter a positive integer.', variant: 'warning' });
        return;
    }
    router.patch(route('dashboard.seasons.update', season.id), { bid_increment_usd: v }, { preserveScroll: true });
};

const publicUrl = (token) => `${window.location.origin}/r/${token}`;
const copyLink  = async (link) => {
    await navigator.clipboard.writeText(link);
    alertDialog({ title: 'Copied', description: 'Registration link copied to clipboard.', variant: 'info' });
};

// ============== Custom registration form builder ==============
// Local working copies of each season's schema so drag/edit doesn't push every
// keystroke to the server. Keyed by season id; saved on "Save form" click.
const builderState = ref({});

const fieldTypes = [
    { value: 'heading',  label: 'Section header' },
    { value: 'text',     label: 'Short text' },
    { value: 'textarea', label: 'Paragraph' },
    { value: 'number',   label: 'Number' },
    { value: 'email',    label: 'Email' },
    { value: 'phone',    label: 'Phone' },
    { value: 'url',      label: 'URL / link' },
    { value: 'date',     label: 'Date' },
    { value: 'time',     label: 'Time' },
    { value: 'select',   label: 'Dropdown' },
    { value: 'radio',    label: 'Radio (single choice)' },
    { value: 'multi',    label: 'Multi-select checkboxes' },
    { value: 'checkbox', label: 'Yes/No checkbox' },
    { value: 'image',    label: 'Image upload' },
    { value: 'payment',  label: 'Payment (bKash / bank / etc.)' },
];

// Types that have a list of options (radio / multi / select).
const isOptionType = (type) => ['select', 'radio', 'multi'].includes(type);

const methodKinds = [
    { value: 'bkash',  label: 'bKash' },
    { value: 'nagad',  label: 'Nagad' },
    { value: 'rocket', label: 'Rocket' },
    { value: 'bank',   label: 'Bank account' },
    { value: 'other',  label: 'Other / mobile wallet' },
];

const addMethod = (field, kind = 'bkash') => {
    if (! field.methods) field.methods = [];
    field.methods.push(kind === 'bank'
        ? { kind, bank: '', account: '', holder: '', branch: '' }
        : { kind, label: '', number: '', instructions: '' });
};
const removeMethod = (field, idx) => field.methods.splice(idx, 1);

const ensureBuilder = (season) => {
    if (! builderState.value[season.id]) {
        // Deep clone so edits don't mutate the prop array.
        builderState.value[season.id] = JSON.parse(JSON.stringify(season.registration_form_schema || []));
    }
    return builderState.value[season.id];
};

const newId = () => Math.random().toString(36).slice(2, 12);

const addField = (season, type = 'text') => {
    const fields = ensureBuilder(season);
    const f = { id: newId(), type, label: 'New field', required: false };
    if (isOptionType(type)) f.options = ['Option 1'];
    if (type === 'image')   f.size    = 600;    // square crop dimension in px (recommended default)
    if (type === 'heading') f.label   = 'Section title';
    if (type === 'payment') {
        f.label   = 'Pay registration fee — enter your transaction ID';
        f.methods = [{ kind: 'bkash', label: 'bKash', number: '', instructions: 'Send Money' }];
    }
    fields.push(f);
};

// Each field can optionally depend on another field's value. Toggling the
// "Conditional" checkbox on a card seeds a default rule pointing at the first
// other field; the user then refines source/operator/value.
const operators = [
    { value: 'equals',     label: 'equals' },
    { value: 'not_equals', label: 'not equals' },
    { value: 'is_set',     label: 'is filled in' },
    { value: 'is_empty',   label: 'is empty' },
];
const toggleConditional = (season, field) => {
    if (field.conditional) {
        delete field.conditional;
        return;
    }
    const others = ensureBuilder(season).filter((g) => g.id !== field.id);
    if (! others.length) {
        alertDialog({ title: 'Need another field first', description: 'Conditions depend on the value of another field. Add at least one other field, then come back and turn on the conditional rule.', variant: 'warning' });
        return;
    }
    field.conditional = { field: others[0].id, operator: 'equals', value: '' };
};
const otherFields = (season, field) => ensureBuilder(season).filter((g) => g.id !== field.id);
const sourceField = (season, field) => {
    if (! field.conditional?.field) return null;
    return ensureBuilder(season).find((g) => g.id === field.conditional.field) || null;
};
// Operators that compare a literal value (and therefore need a value input).
const operatorNeedsValue = (op) => ['equals', 'not_equals'].includes(op);

const removeField = (season, idx) => {
    const fields = ensureBuilder(season);
    fields.splice(idx, 1);
};

const addOption = (field) => {
    if (! field.options) field.options = [];
    field.options.push(`Option ${field.options.length + 1}`);
};
const removeOption = (field, idx) => field.options.splice(idx, 1);

// Native HTML5 drag-and-drop for reordering. We track the dragged index per
// season so two open builders don't interfere.
const dragIdx = ref({});
const onDragStart = (seasonId, idx, evt) => {
    dragIdx.value[seasonId] = idx;
    evt.dataTransfer.effectAllowed = 'move';
};
const onDrop = (season, toIdx) => {
    const fromIdx = dragIdx.value[season.id];
    if (fromIdx === undefined || fromIdx === toIdx) return;
    const fields = ensureBuilder(season);
    const [moved] = fields.splice(fromIdx, 1);
    fields.splice(toIdx, 0, moved);
    dragIdx.value[season.id] = undefined;
};

const saveFormSchema = (season) => {
    const fields = ensureBuilder(season);
    useForm({ fields }).post(route('dashboard.seasons.registration.form', season.id), {
        preserveScroll: true,
    });
};

const resetSchemaDraft = (season) => {
    builderState.value[season.id] = JSON.parse(JSON.stringify(season.registration_form_schema || []));
};

const fmt = useFmt().money;
const atLimit = props.used >= props.limits.seasons;
</script>

<template>
    <DashboardLayout title="Seasons">
        <template #actions>
            <button @click="showCreate = !showCreate"
                    class="btn-primary py-2 px-4 text-[13px]"
                    :class="{ 'opacity-50 pointer-events-none': atLimit }"
                    :disabled="atLimit">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
                New season
            </button>
        </template>

        <div v-if="atLimit" class="mb-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-2.5 text-[13px] text-amber-800">
            You have used <strong>{{ used }} of {{ limits.seasons }}</strong> seasons on your plan.
            <a href="/dashboard/billing" class="underline font-medium">Upgrade</a> to add more.
        </div>

        <!-- Create form -->
        <div v-if="showCreate" class="glass-strong rounded-2xl p-6 mb-5">
            <h3 class="text-[16px] font-bold tracking-tight mb-4">{{ t('forms.seasons.section_title') }}</h3>
            <form @submit.prevent="submit" class="grid md:grid-cols-2 gap-4">
                <Field :label="t('forms.seasons.name')" :error="form.errors.name" required>
                    <TextField v-model="form.name" :placeholder="t('forms.seasons.name_placeholder')" autofocus />
                </Field>
                <Field :label="t('forms.seasons.year')" :error="form.errors.year" required>
                    <TextField v-model="form.year" type="number" :placeholder="t('forms.seasons.year_placeholder')" />
                </Field>
                <Field :label="t('forms.seasons.sport')" :error="form.errors.sport" required>
                    <select v-model="form.sport"
                            class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                        <option value="cricket">{{ t('forms.seasons.sport_cricket') }}</option>
                        <option value="football">{{ t('forms.seasons.sport_football') }}</option>
                    </select>
                </Field>
                <Field :label="t('forms.seasons.budget_per_team')" :error="form.errors.budget_per_team" required>
                    <CurrencyField v-model="form.budget_per_team" />
                </Field>
                <Field label="Bid step (BDT)" hint="Each new bid in BDT mode must beat the current bid by this many ৳. Used as-is — no conversion." :error="form.errors.bid_increment">
                    <TextField v-model="form.bid_increment" type="number" leading="৳" />
                </Field>
                <Field label="Bid step (USD)" hint="Each new bid in USD mode must beat the current bid by this many $. Independent of BDT step — no conversion." :error="form.errors.bid_increment_usd">
                    <TextField v-model="form.bid_increment_usd" type="number" leading="$" />
                </Field>
                <Field :label="t('forms.seasons.start_date')" :error="form.errors.start_date">
                    <TextField v-model="form.start_date" type="date" />
                </Field>
                <Field :label="t('forms.seasons.end_date')" :error="form.errors.end_date">
                    <TextField v-model="form.end_date" type="date" />
                </Field>
                <div class="md:col-span-2 flex gap-2 justify-end pt-2">
                    <button type="button" class="btn-ghost py-2 px-4 text-[13px]" @click="showCreate = false">{{ t('common.cancel') }}</button>
                    <button type="submit" class="btn-primary py-2 px-4 text-[13px]" :disabled="form.processing">
                        {{ form.processing ? t('forms.seasons.submitting') : t('forms.seasons.submit') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- List -->
        <div v-if="seasons.length" class="space-y-3">
            <div v-for="s in seasons" :key="s.id" class="glass rounded-2xl p-5">
                <div class="flex flex-col gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span class="text-[16px] font-bold tracking-tight">{{ s.name }}</span>
                            <span class="font-mono text-[11px] text-ink-500">· {{ s.year }}</span>
                            <span class="font-mono text-[10px] tracking-widest px-2 py-0.5 rounded-full bg-ink-100 text-ink-600 uppercase">{{ s.sport || 'cricket' }}</span>
                            <span v-if="s.is_active" class="px-2 py-0.5 rounded-full font-mono text-[10px] tracking-widest bg-emerald-50 text-emerald-700 border border-emerald-100">ACTIVE</span>
                            <span v-else class="px-2 py-0.5 rounded-full font-mono text-[10px] tracking-widest bg-ink-100 text-ink-500">{{ s.status?.toUpperCase() }}</span>
                            <span v-if="s.registration_open" class="px-2 py-0.5 rounded-full font-mono text-[10px] tracking-widest bg-blue-50 text-blue-700 border border-blue-100">PUBLIC REGISTRATION OPEN</span>
                        </div>
                        <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-0.5 text-[12px] font-mono text-ink-500">
                            <span><strong class="text-ink-700">{{ s.players_count }}</strong> players</span>
                            <span><strong class="text-ink-700">{{ s.teams_count }}</strong> teams</span>
                            <span><strong class="text-ink-700">{{ s.bids_count }}</strong> bids</span>
                            <span>budget {{ fmt(s.budget_per_team) }} /team</span>
                            <span class="inline-flex items-center gap-1">
                                <span>step</span>
                                <strong class="text-ink-700">৳{{ s.bid_increment || 1000 }}</strong>
                                <button type="button" @click="changeIncrement(s)" class="text-brand-indigo hover:underline ml-1">edit</button>
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <span>step</span>
                                <strong class="text-ink-700">${{ s.bid_increment_usd || 10 }}</strong>
                                <button type="button" @click="changeIncrementUsd(s)" class="text-brand-indigo hover:underline ml-1">edit</button>
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                    <button v-if="!s.is_active" @click="activate(s)" class="btn-ghost py-2 px-4 text-[13px] whitespace-nowrap">Set active</button>
                    <button v-else @click="deactivate(s)"
                            class="py-2 px-4 text-[13px] font-medium rounded-xl border whitespace-nowrap bg-amber-50 hover:bg-amber-100 text-amber-800 border-amber-200 transition-colors">
                        Set inactive
                    </button>
                    <button @click="expandedEdit = expandedEdit === s.id ? null : s.id"
                            class="btn-ghost py-2 px-4 text-[13px] whitespace-nowrap"
                            :class="{ 'bg-brand-indigo/10 border-brand-indigo/30 text-brand-indigo': expandedEdit === s.id }">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        {{ expandedEdit === s.id ? 'Hide edit' : 'Edit' }}
                    </button>
                    <a :href="`/dashboard/seasons/${s.id}/export.pdf`" target="_blank" class="btn-ghost py-2 px-4 text-[13px] whitespace-nowrap">
                        Summary PDF
                    </a>
                    <button @click="expandedRegistration = expandedRegistration === s.id ? null : s.id"
                            class="btn-ghost py-2 px-4 text-[13px] whitespace-nowrap">
                        {{ expandedRegistration === s.id ? 'Hide registration' : 'Public registration' }}
                    </button>
                    <button @click="expandedForm = expandedForm === s.id ? null : s.id"
                            class="btn-ghost py-2 px-4 text-[13px] whitespace-nowrap"
                            :class="{ 'bg-violet-50 border-violet-200 text-violet-700': expandedForm === s.id }">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        {{ expandedForm === s.id ? 'Hide form builder' : 'Form builder' }}
                        <span v-if="(s.registration_form_schema?.length || 0) > 0"
                              class="ml-1 px-1.5 py-0.5 rounded-full bg-violet-100 text-violet-700 font-mono text-[10px]">
                            {{ s.registration_form_schema.length }}
                        </span>
                    </button>
                    <button @click="expandedCategories = expandedCategories === s.id ? null : s.id"
                            class="btn-ghost py-2 px-4 text-[13px] whitespace-nowrap"
                            :class="{ 'bg-emerald-50 border-emerald-200 text-emerald-700': expandedCategories === s.id }">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M7 7h.01M7 3h5a2 2 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
                        {{ expandedCategories === s.id ? 'Hide categories' : 'Player categories' }}
                        <span v-if="(s.player_categories?.length || 0) > 0"
                              class="ml-1 px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-mono text-[10px]">
                            {{ s.player_categories.length }}
                        </span>
                    </button>
                    <button @click="deleteSeason(s)"
                            class="py-2 px-4 text-[13px] font-medium rounded-xl border whitespace-nowrap bg-rose-50 hover:bg-rose-100 text-rose-700 border-rose-200 transition-colors">
                        <svg class="inline h-3.5 w-3.5 -mt-0.5 mr-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                        Delete
                    </button>
                    </div>
                </div>

                <!-- ============== Edit season basics ============== -->
                <div v-if="expandedEdit === s.id" class="mt-5 pt-5 border-t border-ink-200/60 space-y-3">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div>
                            <div class="font-mono text-[10.5px] tracking-widest text-brand-indigo">/ EDIT SEASON</div>
                            <p class="text-[13px] text-ink-700 mt-1">Update the season's name, year, sport, budget and dates.</p>
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <button type="button" @click="resetEditDraft(s)" class="text-[11.5px] text-ink-500 hover:text-ink-900 px-2">Reset</button>
                            <button type="button" @click="saveEdit(s)" class="btn-primary py-2 px-4 text-[12.5px]">Save changes</button>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <Field label="Season name">
                            <TextField v-model="ensureEdit(s).name" placeholder="BPL 2026 Spring Cup" />
                        </Field>
                        <Field label="Year">
                            <TextField :modelValue="ensureEdit(s).year"
                                       @update:modelValue="(v) => ensureEdit(s).year = v"
                                       type="number" placeholder="2026" />
                        </Field>
                        <Field label="Sport">
                            <select v-model="ensureEdit(s).sport" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                                <option value="cricket">Cricket</option>
                                <option value="football">Football</option>
                            </select>
                        </Field>
                        <Field label="Budget per team (৳)">
                            <CurrencyField :modelValue="ensureEdit(s).budget_per_team"
                                           @update:modelValue="(v) => ensureEdit(s).budget_per_team = v" />
                        </Field>
                        <Field label="Start date">
                            <input type="date" v-model="ensureEdit(s).start_date"
                                   class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                        </Field>
                        <Field label="End date">
                            <input type="date" v-model="ensureEdit(s).end_date"
                                   class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                        </Field>
                    </div>
                </div>

                <!-- Public registration block -->
                <div v-if="expandedRegistration === s.id" class="mt-5 pt-5 border-t border-ink-200/60 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-mono text-[10.5px] tracking-widest text-ink-500">/ public registration link</div>
                            <p class="text-[13px] text-ink-600 mt-1 max-w-lg">
                                Share a public URL where players can self-register. You review and approve them in the Players page.
                            </p>
                        </div>
                        <Toggle :model-value="!!s.registration_open"
                                @update:model-value="(v) => toggleReg(s, { open: v, registration_fee: s.registration_fee, registration_instructions: s.registration_instructions })"
                                on-label="OPEN" off-label="CLOSED" />
                    </div>

                    <div v-if="s.registration_open && s.registration_token" class="rounded-xl bg-white/70 border border-ink-200/60 p-4">
                        <div class="font-mono text-[10.5px] tracking-widest text-ink-500 mb-1.5">SHAREABLE URL</div>
                        <div class="flex items-center gap-2">
                            <code class="flex-1 font-mono text-[12.5px] truncate bg-white px-3 py-2 rounded-lg border border-ink-200">
                                {{ publicUrl(s.registration_token) }}
                            </code>
                            <button @click="copyLink(publicUrl(s.registration_token))" class="btn-ghost py-2 px-3 text-[12px] whitespace-nowrap">Copy</button>
                            <a :href="publicUrl(s.registration_token)" target="_blank" class="btn-ghost py-2 px-3 text-[12px] whitespace-nowrap">Open</a>
                            <button @click="regenerate(s)" class="text-[11px] text-rose-500 hover:text-rose-700 px-2 whitespace-nowrap">Regenerate</button>
                        </div>
                    </div>

                    <div v-if="s.registration_open" class="grid md:grid-cols-2 gap-4">
                        <Field label="Registration fee (optional)">
                            <CurrencyField :modelValue="s.registration_fee"
                                           @update:modelValue="(v) => s.registration_fee = v"
                                           @blur="toggleReg(s, { open: true, registration_fee: s.registration_fee, registration_instructions: s.registration_instructions })" />
                        </Field>
                        <Field label="Instructions (shown on registration page)">
                            <textarea :value="s.registration_instructions ?? ''"
                                      placeholder="bKash 01XXX-XXXXXXX. Note your name in the reference."
                                      class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30"
                                      rows="2"
                                      @blur="(e) => toggleReg(s, { open: true, registration_fee: s.registration_fee, registration_instructions: e.target.value })"></textarea>
                        </Field>
                    </div>

                </div>

                <!-- ============== Form builder block (independent of registration toggle) ============== -->
                <div v-if="expandedForm === s.id" class="mt-5 pt-5 border-t border-ink-200/60 space-y-3">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div>
                            <div class="font-mono text-[10.5px] tracking-widest text-violet-700">/ REGISTRATION FORM BUILDER</div>
                            <p class="text-[13px] text-ink-700 mt-1">
                                Add custom fields to collect any info you want from registering players.
                                Drag to reorder. These appear below the built-in fields on the public registration page.
                            </p>
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <button type="button" @click="resetSchemaDraft(s)" class="text-[11.5px] text-ink-500 hover:text-ink-900 px-2">Reset</button>
                            <button type="button" @click="saveFormSchema(s)" class="btn-primary py-2 px-4 text-[12.5px]">Save form</button>
                        </div>
                    </div>

                    <!-- Field cards. Only the drag handle (⋮⋮ icon) is `draggable=true`,
                         so clicking inside inputs lets you select / delete text normally.
                         The card itself is the drop target. -->
                    <div class="space-y-2">
                        <div v-for="(field, idx) in ensureBuilder(s)" :key="field.id"
                             @dragover.prevent
                             @drop="onDrop(s, idx)"
                             class="rounded-xl bg-white border border-ink-200/60 p-3 hover:border-violet-300 transition-colors">

                            <div class="flex items-start gap-2">
                                <span draggable="true"
                                      @dragstart="onDragStart(s.id, idx, $event)"
                                      class="grid place-items-center h-7 w-7 rounded text-ink-400 hover:text-ink-700 hover:bg-ink-100 shrink-0 cursor-grab active:cursor-grabbing select-none"
                                      title="Drag to reorder">
                                    <svg class="h-4 w-4 pointer-events-none" fill="currentColor" viewBox="0 0 24 24"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>
                                </span>

                                <div class="flex-1 grid sm:grid-cols-3 gap-2">
                                    <input v-model="field.label" type="text" placeholder="Field label"
                                           class="rounded-lg border border-ink-200/70 px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30 sm:col-span-2" />
                                    <select v-model="field.type"
                                            class="rounded-lg border border-ink-200/70 px-2 py-1.5 text-[12.5px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                                        <option v-for="t in fieldTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                                    </select>
                                    <input v-if="!['select','radio','multi','checkbox','image','heading','payment'].includes(field.type)"
                                           v-model="field.placeholder" type="text" placeholder="Placeholder (optional)"
                                           class="rounded-lg border border-ink-200/70 px-3 py-1.5 text-[12.5px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30 sm:col-span-2" />
                                    <label v-if="field.type !== 'heading'" class="inline-flex items-center gap-1.5 text-[12.5px] text-ink-700">
                                        <input type="checkbox" v-model="field.required" class="h-3.5 w-3.5" />
                                        Required
                                    </label>
                                </div>

                                <button type="button" @click="removeField(s, idx)" class="text-rose-500 hover:text-rose-700 px-2 text-[15px] shrink-0" title="Remove field">×</button>
                            </div>

                            <!-- Options editor — used by select / radio / multi -->
                            <div v-if="isOptionType(field.type)" class="mt-3 ml-9 space-y-1.5">
                                <div class="font-mono text-[10px] tracking-widest text-ink-500">OPTIONS</div>
                                <div v-for="(opt, oIdx) in field.options || []" :key="oIdx" class="flex items-center gap-2">
                                    <input v-model="field.options[oIdx]" type="text"
                                           class="flex-1 rounded-lg border border-ink-200/70 px-2 py-1 text-[12.5px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                                    <button type="button" @click="removeOption(field, oIdx)" class="text-rose-500 text-[14px]">×</button>
                                </div>
                                <button type="button" @click="addOption(field)" class="text-[11.5px] text-brand-indigo hover:underline">+ add option</button>
                            </div>

                            <!-- Heading: no input on the public form, just a label divider -->
                            <p v-if="field.type === 'heading'" class="mt-2 ml-9 text-[11px] text-ink-500">
                                Renders as a section title on the public form — no input field, just a visual divider between groups of questions.
                            </p>

                            <!-- Payment methods editor — multiple bKash / bank / etc. -->
                            <div v-if="field.type === 'payment'" class="mt-3 ml-9 space-y-2">
                                <div class="font-mono text-[10px] tracking-widest text-ink-500">PAYMENT METHODS — players see all of these on the form</div>
                                <div v-for="(m, mIdx) in (field.methods || [])" :key="mIdx"
                                     class="rounded-lg bg-violet-50/50 border border-violet-200/70 p-2.5 space-y-1.5">
                                    <div class="flex items-center gap-2">
                                        <select v-model="m.kind"
                                                class="rounded-md border border-ink-200/70 bg-white px-2 py-1 text-[12.5px] focus:outline-none focus:ring-2 focus:ring-violet-300">
                                            <option v-for="k in methodKinds" :key="k.value" :value="k.value">{{ k.label }}</option>
                                        </select>
                                        <span class="flex-1"></span>
                                        <button type="button" @click="removeMethod(field, mIdx)" class="text-rose-500 hover:text-rose-700 text-[14px] px-2" title="Remove method">×</button>
                                    </div>

                                    <!-- Mobile wallets (bKash / Nagad / Rocket / Other) -->
                                    <div v-if="m.kind !== 'bank'" class="grid sm:grid-cols-3 gap-1.5">
                                        <input v-model="m.label" type="text" placeholder="Label (e.g. Personal bKash)"
                                               class="rounded-md border border-ink-200/70 bg-white px-2 py-1 text-[12.5px] focus:outline-none focus:ring-2 focus:ring-violet-300" />
                                        <input v-model="m.number" type="text" placeholder="Number (01XXX-XXXXXXX)"
                                               class="rounded-md border border-ink-200/70 bg-white px-2 py-1 text-[12.5px] font-mono focus:outline-none focus:ring-2 focus:ring-violet-300" />
                                        <input v-model="m.instructions" type="text" placeholder="Instructions (e.g. Send Money)"
                                               class="rounded-md border border-ink-200/70 bg-white px-2 py-1 text-[12.5px] focus:outline-none focus:ring-2 focus:ring-violet-300" />
                                    </div>

                                    <!-- Bank account -->
                                    <div v-else class="grid sm:grid-cols-2 gap-1.5">
                                        <input v-model="m.bank" type="text" placeholder="Bank name (BRAC Bank)"
                                               class="rounded-md border border-ink-200/70 bg-white px-2 py-1 text-[12.5px] focus:outline-none focus:ring-2 focus:ring-violet-300" />
                                        <input v-model="m.account" type="text" placeholder="Account number"
                                               class="rounded-md border border-ink-200/70 bg-white px-2 py-1 text-[12.5px] font-mono focus:outline-none focus:ring-2 focus:ring-violet-300" />
                                        <input v-model="m.holder" type="text" placeholder="Account holder name (optional)"
                                               class="rounded-md border border-ink-200/70 bg-white px-2 py-1 text-[12.5px] focus:outline-none focus:ring-2 focus:ring-violet-300" />
                                        <input v-model="m.branch" type="text" placeholder="Branch (optional)"
                                               class="rounded-md border border-ink-200/70 bg-white px-2 py-1 text-[12.5px] focus:outline-none focus:ring-2 focus:ring-violet-300" />
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" v-for="k in methodKinds" :key="k.value"
                                            @click="addMethod(field, k.value)"
                                            class="rounded-md bg-ink-100 hover:bg-violet-100 hover:text-violet-700 text-ink-700 px-2 py-1 text-[11px] transition-colors">
                                        + {{ k.label }}
                                    </button>
                                </div>

                                <p class="text-[11px] text-ink-500">Players see all configured methods on the form, then enter their bKash TrxID or bank reference in a single text input below the cards.</p>
                            </div>

                            <!-- Image: square-crop size config -->
                            <div v-if="field.type === 'image'" class="mt-3 ml-9">
                                <div class="font-mono text-[10px] tracking-widest text-ink-500 mb-1.5">CROP SIZE (PX, SQUARE)</div>
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" v-for="px in [300, 400, 600, 800, 1200]" :key="px"
                                            @click="field.size = px"
                                            class="rounded-md px-2.5 py-1 text-[11.5px] font-mono border transition"
                                            :class="(field.size || 600) === px
                                                ? 'bg-violet-100 border-violet-300 text-violet-700'
                                                : 'bg-ink-50 border-ink-200 text-ink-700 hover:bg-violet-50'">
                                        {{ px }}×{{ px }}
                                    </button>
                                    <input v-model.number="field.size" type="number" min="100" max="2000" placeholder="Custom"
                                           class="w-20 rounded-md border border-ink-200/70 px-2 py-1 text-[11.5px] font-mono focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                                </div>
                                <p class="mt-1 text-[11px] text-ink-500">Uploaded image is cropped to this square size before save. Recommended 600×600.</p>
                            </div>

                            <!-- Conditional visibility -->
                            <div class="mt-3 ml-9">
                                <label class="inline-flex items-center gap-2 cursor-pointer text-[12px] text-ink-700">
                                    <input type="checkbox" :checked="!!field.conditional" @change="toggleConditional(s, field)" class="h-3.5 w-3.5" />
                                    Show this field only when…
                                </label>
                                <div v-if="field.conditional" class="mt-2 rounded-lg bg-violet-50/60 border border-violet-200/70 p-3 space-y-2">
                                    <div class="grid sm:grid-cols-3 gap-2 text-[12px]">
                                        <select v-model="field.conditional.field"
                                                class="rounded-md border border-ink-200/70 bg-white px-2 py-1.5 text-[12.5px] focus:outline-none focus:ring-2 focus:ring-violet-300">
                                            <option v-for="f in otherFields(s, field)" :key="f.id" :value="f.id">{{ f.label || f.id }}</option>
                                        </select>
                                        <select v-model="field.conditional.operator"
                                                class="rounded-md border border-ink-200/70 bg-white px-2 py-1.5 text-[12.5px] focus:outline-none focus:ring-2 focus:ring-violet-300">
                                            <option v-for="op in operators" :key="op.value" :value="op.value">{{ op.label }}</option>
                                        </select>

                                        <!-- Value input — type-aware: dropdown when source is select, text otherwise -->
                                        <template v-if="operatorNeedsValue(field.conditional.operator)">
                                            <select v-if="sourceField(s, field)?.type === 'select'"
                                                    v-model="field.conditional.value"
                                                    class="rounded-md border border-ink-200/70 bg-white px-2 py-1.5 text-[12.5px] focus:outline-none focus:ring-2 focus:ring-violet-300">
                                                <option value="">— pick value —</option>
                                                <option v-for="opt in (sourceField(s, field)?.options || [])" :key="opt" :value="opt">{{ opt }}</option>
                                            </select>
                                            <select v-else-if="sourceField(s, field)?.type === 'checkbox'"
                                                    v-model="field.conditional.value"
                                                    class="rounded-md border border-ink-200/70 bg-white px-2 py-1.5 text-[12.5px] focus:outline-none focus:ring-2 focus:ring-violet-300">
                                                <option value="true">Yes (checked)</option>
                                                <option value="false">No (unchecked)</option>
                                            </select>
                                            <input v-else v-model="field.conditional.value" type="text" placeholder="value"
                                                   class="rounded-md border border-ink-200/70 bg-white px-2 py-1.5 text-[12.5px] focus:outline-none focus:ring-2 focus:ring-violet-300" />
                                        </template>
                                        <span v-else class="text-[11.5px] text-ink-500 self-center">(no value needed)</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="ensureBuilder(s).length === 0" class="text-center py-8 rounded-xl border-2 border-dashed border-ink-200 text-ink-500">
                            <div class="text-[13px] font-medium mb-1">No custom fields yet</div>
                            <div class="text-[12px]">Click any field type below to add. Built-in fields (name, category, position, photo) are always included.</div>
                        </div>
                    </div>

                    <!-- Add field row -->
                    <div class="flex flex-wrap gap-1.5 pt-3 border-t border-ink-200/60">
                        <span class="text-[10.5px] font-mono text-ink-500 self-center mr-1">ADD FIELD:</span>
                        <button v-for="t in fieldTypes" :key="t.value" type="button"
                                @click="addField(s, t.value)"
                                class="rounded-md bg-ink-100 hover:bg-violet-100 hover:text-violet-700 text-ink-700 px-2.5 py-1 text-[11.5px] transition-colors">
                            + {{ t.label }}
                        </button>
                    </div>
                </div>

                <!-- ============== Player categories editor ============== -->
                <div v-if="expandedCategories === s.id" class="mt-5 pt-5 border-t border-ink-200/60 space-y-3">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div>
                            <div class="font-mono text-[10.5px] tracking-widest text-emerald-700">/ PLAYER CATEGORIES</div>
                            <p class="text-[13px] text-ink-700 mt-1 max-w-2xl">
                                Define the categories players are sorted into for this season, and the default base price each one gets on public registration. Renaming or removing a category will leave existing players with that category uncategorised — you'll need to re-assign them.
                            </p>
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <button type="button" @click="resetCategoriesDraft(s)" class="text-[11.5px] text-ink-500 hover:text-ink-900 px-2">Reset</button>
                            <button type="button" @click="saveCategories(s)" class="btn-primary py-2 px-4 text-[12.5px]">Save categories</button>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div v-for="(c, idx) in ensureCategories(s)" :key="idx"
                             class="grid grid-cols-12 gap-2 items-center rounded-xl border border-ink-200/70 bg-white/70 px-3 py-2">
                            <div class="col-span-12 sm:col-span-5">
                                <label class="font-mono text-[10px] tracking-widest text-ink-500">NAME</label>
                                <input v-model="c.name" type="text" placeholder="Elite"
                                       class="mt-1 w-full rounded-lg border border-ink-200/70 bg-white px-3 py-2 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-emerald-300" />
                            </div>
                            <div class="col-span-9 sm:col-span-5">
                                <label class="font-mono text-[10px] tracking-widest text-ink-500">DEFAULT BASE PRICE (৳)</label>
                                <input v-model.number="c.base_price" type="number" min="0" placeholder="50000"
                                       class="mt-1 w-full rounded-lg border border-ink-200/70 bg-white px-3 py-2 text-[13.5px] font-mono focus:outline-none focus:ring-2 focus:ring-emerald-300" />
                            </div>
                            <div class="col-span-3 sm:col-span-2 flex justify-end pt-5">
                                <button type="button" @click="removeCategory(s, idx)"
                                        class="text-[11.5px] text-rose-500 hover:text-rose-700 px-2">Remove</button>
                            </div>
                        </div>
                        <div v-if="ensureCategories(s).length === 0" class="text-center py-6 rounded-xl border-2 border-dashed border-ink-200 text-ink-500">
                            <div class="text-[13px] font-medium mb-1">No categories</div>
                            <div class="text-[12px]">Add at least one — the player form needs something to pick from.</div>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-ink-200/60">
                        <button type="button" @click="addCategory(s)"
                                class="rounded-md bg-ink-100 hover:bg-emerald-100 hover:text-emerald-700 text-ink-700 px-3 py-1.5 text-[12px] transition-colors">
                            + Add category
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div v-else class="glass rounded-2xl p-10 text-center">
            <div class="font-mono text-[11px] tracking-widest text-ink-500 mb-3">/ no seasons yet</div>
            <p class="text-ink-500 text-[14px] max-w-md mx-auto">Create your first season to add players and run an auction.</p>
            <button @click="showCreate = true" class="btn-primary inline-flex mt-5 px-5">Create a season</button>
        </div>
    </DashboardLayout>
</template>
