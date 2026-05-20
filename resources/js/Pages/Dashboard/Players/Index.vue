<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Field from '@/Components/Field.vue';
import TextField from '@/Components/TextField.vue';
import CurrencyField from '@/Components/CurrencyField.vue';
import ImageCropper from '@/Components/ImageCropper.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n, I18nT } from 'vue-i18n';
import { useFmt } from '@/composables/useFmt';
import { useConfirm } from '@/composables/useConfirm';

const confirm = useConfirm();

const { t } = useI18n();

const props = defineProps({
    season:        Object,
    players:       Object,        // paginator
    filters:       Object,
    limits:        Object,
    used:          Number,
    pending_count: { type: Number, default: 0 },
    positions:     { type: Array, default: () => [] },
    custom_fields: { type: Array, default: () => [] },
});

const isCricket = props.season?.sport !== 'football';

// Per-season category names — falls back to legacy defaults if the season
// hasn't been migrated yet (defensive — the backend already backfills).
const seasonCategoryNames = computed(() => {
    const list = props.season?.player_categories || [];
    const names = list.map(c => c?.name).filter(Boolean);
    return names.length ? names : ['Elite', 'Regular', 'New'];
});

// Pre-seed the `custom` map with one slot per dynamic field — keeps reactivity
// stable so v-model binds even on first render.
const customSeed = () => {
    const seed = {};
    props.custom_fields.forEach((f) => {
        if (f.type === 'heading')      return;
        else if (f.type === 'checkbox') seed[f.id] = false;
        else if (f.type === 'multi')    seed[f.id] = [];
        else if (f.type === 'image')    seed[f.id] = null;
        else                             seed[f.id] = '';
    });
    return seed;
};

const inputClasses =
    'w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] ' +
    'placeholder:text-ink-400 focus:outline-none focus:ring-2 focus:ring-brand-indigo/30 ' +
    'focus:border-brand-indigo transition-shadow shadow-sm';

const showCreate = ref(false);
const editingId  = ref(null);    // player id currently being edited inline (or null)
const defaultCategory = () => seasonCategoryNames.value[0] || 'Regular';

const form = useForm({
    name: '',
    category: defaultCategory(),
    player_type: 'New',
    position: '',
    base_price: 50000,
    jersey_no: '',
    batting_style: '',
    bowling_style: '',
    profession: '',
    photo: null,
    custom: customSeed(),
});

// Helpers for option-style fields
const isFieldVisible = (f) => {
    if (! f.conditional?.field || ! f.conditional?.operator) return true;
    const sourceVal = form.custom?.[f.conditional.field];
    const compare   = f.conditional.value ?? '';
    switch (f.conditional.operator) {
        case 'equals':     return String(sourceVal) === String(compare);
        case 'not_equals': return String(sourceVal) !== String(compare);
        case 'is_set':     return sourceVal !== null && sourceVal !== undefined && sourceVal !== '' && sourceVal !== false;
        case 'is_empty':   return sourceVal === null || sourceVal === undefined || sourceVal === '' || sourceVal === false;
        default:           return true;
    }
};
const toggleMulti = (fieldId, opt) => {
    const arr = form.custom[fieldId] || [];
    const i = arr.indexOf(opt);
    if (i >= 0) arr.splice(i, 1); else arr.push(opt);
    form.custom[fieldId] = [...arr];
};

const filterForm = ref({ ...props.filters });

const resetForm = () => {
    form.reset();
    form.category = defaultCategory();
    form.player_type = 'New';
    form.position = '';
    form.base_price = 50000;
    form.custom = customSeed();
};

const startCreate = () => { editingId.value = null; resetForm(); showCreate.value = true; };

const startEdit = (p) => {
    editingId.value = p.id;
    form.name          = p.name ?? '';
    form.category      = p.category ?? defaultCategory();
    form.player_type   = p.player_type ?? 'New';
    form.position      = p.position ?? '';
    form.base_price    = p.base_price ?? 0;
    form.jersey_no     = p.jersey_no ?? '';
    form.batting_style = p.batting_style ?? '';
    form.bowling_style = p.bowling_style ?? '';
    form.profession    = p.profession ?? '';
    form.photo         = null;

    // Pre-fill custom-field values from the player's saved registration_data.
    // For multi-select we restore the array; for image we leave the slot null
    // (a fresh file replaces, otherwise the existing URL is preserved server-side).
    const seeded = customSeed();
    const saved  = p.registration_data || {};
    props.custom_fields.forEach((f) => {
        if (f.type === 'heading' || f.type === 'image') return;
        const entry = saved[f.id];
        if (! entry) return;
        if (f.type === 'multi')          seeded[f.id] = entry.values || [];
        else if (f.type === 'checkbox')  seeded[f.id] = entry.value === 'Yes';
        else                              seeded[f.id] = entry.value ?? '';
    });
    form.custom = seeded;

    showCreate.value   = true;
    // Smoothly bring the form into view.
    setTimeout(() => window.scrollTo({ top: 0, behavior: 'smooth' }), 50);
};

const cancelEdit = () => { showCreate.value = false; editingId.value = null; resetForm(); };

const submit = () => {
    if (editingId.value) {
        // Inertia expects POST for multipart edits with method spoofing.
        form.post(route('dashboard.players.update', editingId.value), {
            onSuccess: () => { showCreate.value = false; editingId.value = null; resetForm(); },
            preserveScroll: true,
            forceFormData: true,
        });
    } else {
        form.post(route('dashboard.players.store'), {
            onSuccess: () => { showCreate.value = false; resetForm(); },
            preserveScroll: true,
            forceFormData: true,
        });
    }
};

const applyFilters = () => router.get(route('dashboard.players.index'), filterForm.value, { preserveState: true });

const remove = async (p) => {
    if (! await confirm({
        title: t('players_page.confirm_delete_title', { name: p.name }),
        description: t('players_page.confirm_delete_body'),
        variant: 'danger',
        confirmText: t('players_page.delete_player'),
    })) return;
    router.delete(route('dashboard.players.destroy', p.id), { preserveScroll: true });
};

// Details modal — shows the player's full record (built-in fields + every
// org-defined custom field they answered on the public registration form).
const detailsPlayer = ref(null);
const showDetails = (p) => { detailsPlayer.value = p; };
const closeDetails = () => { detailsPlayer.value = null; };

// "Edit player" from inside the details modal: capture the player reference
// BEFORE closing, otherwise startEdit(null) is called (closeDetails wipes the
// ref first and Vue auto-unwraps the inline expression at call time).
const editFromDetails = () => {
    const p = detailsPlayer.value;
    closeDetails();
    if (p) startEdit(p);
};
// Detect "image-like" custom field values so we can render a thumbnail.
const isImageValue = (v) => typeof v === 'string' && /^(https?:|\/storage\/)/.test(v) && /\.(png|jpe?g|webp|gif)(\?|$)/i.test(v);

const approve = (p) => router.post(route('dashboard.players.approve', p.id), {}, { preserveScroll: true });
const reject  = async (p) => {
    if (! await confirm({
        title: t('players_page.confirm_reject_title', { name: p.name }),
        description: t('players_page.confirm_reject_body'),
        variant: 'danger',
        confirmText: t('players_page.reject_and_delete'),
    })) return;
    router.delete(route('dashboard.players.reject', p.id), { preserveScroll: true });
};
const approveAll = async () => {
    if (! await confirm({
        title: t('players_page.confirm_approve_all_title', { count: props.pending_count }),
        description: t('players_page.confirm_approve_all_body'),
        variant: 'info',
        confirmText: t('players_page.approve_all'),
    })) return;
    router.post(route('dashboard.players.approve-all'), {}, { preserveScroll: true });
};
const filterPending = () => { filterForm.value.status = 'pending'; applyFilters(); };

const csvInput = ref(null);
const onCsvPicked = (e) => {
    const file = e.target.files?.[0];
    if (! file) return;
    router.post(route('dashboard.players.import.preview'), { file }, { forceFormData: true });
    if (csvInput.value) csvInput.value.value = '';
};

const fmt = useFmt().money;
const statusColor = (s) => ({
    pending: 'bg-amber-50 text-amber-700 border-amber-100',
    queue:   'bg-blue-50 text-blue-700 border-blue-100',
    live:    'bg-rose-50 text-rose-700 border-rose-100',
    sold:    'bg-emerald-50 text-emerald-700 border-emerald-100',
    unsold:  'bg-ink-100 text-ink-500 border-ink-200',
}[s] || 'bg-ink-100 text-ink-500');
// Status pill against the dark hero background — translucent + bright accent.
const auctionBadgeDark = (s) => ({
    pending: 'bg-amber-400/15 text-amber-200 border-amber-300/30',
    queue:   'bg-blue-400/15 text-blue-200 border-blue-300/30',
    live:    'bg-rose-500/20 text-rose-100 border-rose-300/40 animate-pulse',
    sold:    'bg-emerald-500/15 text-emerald-200 border-emerald-300/30',
    unsold:  'bg-white/5 text-white/70 border-white/15',
}[s] || 'bg-white/10 text-white border-white/20');
const atLimit = props.season && props.used >= props.limits.players;
</script>

<template>
    <DashboardLayout :title="t('players_page.title')">
        <template #actions>
            <a v-if="limits.export_csv" :href="route('dashboard.players.export.csv')" class="btn-ghost py-2 px-4 text-[13px]">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 16V4m0 0l-4 4m4-4l4 4M4 16v4h16v-4"/></svg>
                {{ t('players_page.export_csv') }}
            </a>
            <a v-if="limits.export_pdf" :href="route('dashboard.players.export.pdf')" class="btn-ghost py-2 px-4 text-[13px]">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z"/><path d="M14 2v6h6"/></svg>
                {{ t('players_page.export_pdf') }}
            </a>
            <a :href="route('dashboard.players.import.template')" class="btn-ghost py-2 px-4 text-[13px]">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                {{ t('players_page.csv_template') }}
            </a>
            <label class="btn-ghost py-2 px-4 text-[13px] cursor-pointer">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 16V4m0 0l-4 4m4-4l4 4M4 16v4h16v-4"/></svg>
                <input ref="csvInput" type="file" accept=".csv,text/csv" class="hidden" @change="onCsvPicked" />
                {{ t('players_page.import_csv') }}
            </label>
            <button @click="startCreate"
                    class="btn-primary py-2 px-4 text-[13px]"
                    :class="{ 'opacity-50 pointer-events-none': !season || atLimit }"
                    :disabled="!season || atLimit">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
                {{ t('players_page.add_player') }}
            </button>
        </template>

        <div v-if="!season" class="glass rounded-2xl p-10 text-center">
            <p class="text-ink-500 text-[14px] max-w-md mx-auto">
                <i18n-t keypath="players_page.no_active_season_msg">
                    <template #link><Link href="/dashboard/seasons" class="text-ink-900 underline">{{ t('players_page.create_or_activate_link') }}</Link></template>
                </i18n-t>
            </p>
        </div>

        <template v-else>
            <div v-if="atLimit" class="mb-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-2.5 text-[13px] text-amber-800">
                {{ t('players_page.player_limit_reached') }} <strong>{{ used }} / {{ limits.players }}</strong>
                <a href="/dashboard/billing" class="underline font-medium">{{ t('seasons_page.upgrade_to_add') }}</a>{{ t('seasons_page.at_limit_suffix') }}
            </div>

            <!-- Create / Edit form -->
            <div v-if="showCreate" class="glass-strong rounded-2xl p-6 mb-5">
                <h3 class="text-[16px] font-bold tracking-tight mb-4">
                    {{ editingId ? t('players_page.edit_player_title') : t('forms.players.section_title') }}
                </h3>
                <form @submit.prevent="submit" class="grid md:grid-cols-3 gap-4">
                    <Field :label="t('forms.players.name')" :error="form.errors.name" required>
                        <TextField v-model="form.name" :placeholder="t('forms.players.name_placeholder')" autofocus />
                    </Field>
                    <Field :label="t('forms.players.category')" :error="form.errors.category" required>
                        <select v-model="form.category" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                            <option v-for="c in seasonCategoryNames" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </Field>
                    <Field :label="t('forms.players.type')" :error="form.errors.player_type" required>
                        <select v-model="form.player_type" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                            <option value="Old">{{ t('players_page.type_old') }}</option>
                            <option value="New">{{ t('players_page.type_new') }}</option>
                        </select>
                    </Field>
                    <Field :label="t('forms.players.position')" :error="form.errors.position">
                        <select v-model="form.position" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                            <option value="">—</option>
                            <option v-for="p in positions" :key="p" :value="p">{{ t(`positions.${p}`) }}</option>
                        </select>
                    </Field>
                    <Field :label="t('forms.players.base_price')" :error="form.errors.base_price" required>
                        <CurrencyField v-model="form.base_price" />
                    </Field>
                    <Field :label="t('forms.players.jersey_no')" :error="form.errors.jersey_no">
                        <TextField v-model="form.jersey_no" :placeholder="t('forms.players.jersey_placeholder')" />
                    </Field>
                    <Field :label="t('forms.players.profession')" :error="form.errors.profession">
                        <TextField v-model="form.profession" :placeholder="t('forms.players.profession_placeholder')" />
                    </Field>
                    <template v-if="isCricket">
                        <Field :label="t('forms.players.batting')" :error="form.errors.batting_style">
                            <TextField v-model="form.batting_style" :placeholder="t('forms.players.batting_placeholder')" />
                        </Field>
                        <Field :label="t('forms.players.bowling')" :error="form.errors.bowling_style">
                            <TextField v-model="form.bowling_style" :placeholder="t('forms.players.bowling_placeholder')" />
                        </Field>
                    </template>
                    <div class="md:col-span-3 pt-2 border-t border-ink-200/60">
                        <ImageCropper :size="300" :label="t('forms.players.photo_label')" @update:file="form.photo = $event" />
                        <p v-if="form.errors.photo" class="mt-1.5 text-[12.5px] text-rose-500">{{ form.errors.photo }}</p>
                    </div>

                    <!-- ============== Org-defined custom fields ============== -->
                    <div v-if="custom_fields.length" class="md:col-span-3 pt-3 border-t border-violet-200/60 space-y-3">
                        <div class="font-mono text-[10.5px] tracking-widest text-violet-700">/ FORM-BUILDER FIELDS ({{ custom_fields.length }})</div>

                        <template v-for="f in custom_fields" :key="f.id">
                            <!-- Section header divider -->
                            <div v-if="f.type === 'heading'" v-show="isFieldVisible(f)" class="pt-1">
                                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">/ {{ f.label?.toUpperCase() }}</div>
                                <div class="mt-1 h-px bg-ink-200/60"></div>
                            </div>

                            <!-- Yes/No checkbox: standalone label -->
                            <label v-else-if="f.type === 'checkbox'" v-show="isFieldVisible(f)"
                                   class="flex items-start gap-3 px-3 py-2.5 rounded-xl border cursor-pointer transition-colors"
                                   :class="form.custom[f.id]
                                       ? 'bg-emerald-50/70 border-emerald-200'
                                       : 'bg-white/70 border-ink-200/60 hover:bg-white'">
                                <input type="checkbox" v-model="form.custom[f.id]" class="h-5 w-5 mt-0.5 shrink-0 accent-brand-indigo cursor-pointer" />
                                <span class="flex-1">
                                    <span class="text-[14px] text-ink-800">{{ f.label }}<span v-if="f.required" class="text-rose-500 ml-0.5">*</span></span>
                                    <span v-if="form.errors[`custom.${f.id}`]" class="block mt-1 text-[12px] text-rose-500">{{ form.errors[`custom.${f.id}`] }}</span>
                                </span>
                            </label>

                            <Field v-else
                                   v-show="isFieldVisible(f)"
                                   :label="f.label"
                                   :error="form.errors[`custom.${f.id}`]"
                                   :required="!!f.required">

                                <input v-if="f.type === 'text'"     v-model="form.custom[f.id]" type="text" :placeholder="f.placeholder ?? ''" :class="inputClasses" />
                                <input v-else-if="f.type === 'number'" v-model="form.custom[f.id]" type="number" inputmode="decimal" step="any" :placeholder="f.placeholder ?? ''" :class="inputClasses" />
                                <input v-else-if="f.type === 'email'"  v-model="form.custom[f.id]" type="email" autocomplete="email" :placeholder="f.placeholder ?? ''" :class="inputClasses" />
                                <input v-else-if="f.type === 'phone'"  v-model="form.custom[f.id]" type="tel" inputmode="tel" :placeholder="f.placeholder ?? '01XXX-XXXXXXX'" :class="inputClasses" />
                                <input v-else-if="f.type === 'url'"    v-model="form.custom[f.id]" type="url" inputmode="url" :placeholder="f.placeholder ?? 'https://'" :class="inputClasses" />
                                <input v-else-if="f.type === 'date'"   v-model="form.custom[f.id]" type="date" :class="inputClasses" />
                                <input v-else-if="f.type === 'time'"   v-model="form.custom[f.id]" type="time" :class="inputClasses" />

                                <textarea v-else-if="f.type === 'textarea'"
                                          v-model="form.custom[f.id]"
                                          :placeholder="f.placeholder ?? ''"
                                          rows="3"
                                          :class="inputClasses"></textarea>

                                <select v-else-if="f.type === 'select'" v-model="form.custom[f.id]" :class="inputClasses">
                                    <option value="">— select —</option>
                                    <option v-for="opt in (f.options || [])" :key="opt" :value="opt">{{ opt }}</option>
                                </select>

                                <div v-else-if="f.type === 'radio'" class="space-y-1.5 pt-1">
                                    <label v-for="opt in (f.options || [])" :key="opt"
                                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                                           :class="form.custom[f.id] === opt ? 'bg-indigo-50/70 border-indigo-200' : 'bg-white/70 border-ink-200/60 hover:bg-white'">
                                        <input type="radio" :name="`field-${f.id}`" :value="opt" v-model="form.custom[f.id]" class="h-4 w-4 accent-brand-indigo cursor-pointer" />
                                        <span class="text-[13.5px] text-ink-800">{{ opt }}</span>
                                    </label>
                                </div>

                                <div v-else-if="f.type === 'multi'" class="space-y-1.5 pt-1">
                                    <label v-for="opt in (f.options || [])" :key="opt"
                                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                                           :class="(form.custom[f.id] || []).includes(opt) ? 'bg-emerald-50/70 border-emerald-200' : 'bg-white/70 border-ink-200/60 hover:bg-white'">
                                        <input type="checkbox" :checked="(form.custom[f.id] || []).includes(opt)" @change="toggleMulti(f.id, opt)" class="h-4 w-4 accent-brand-indigo cursor-pointer" />
                                        <span class="text-[13.5px] text-ink-800">{{ opt }}</span>
                                    </label>
                                </div>

                                <div v-else-if="f.type === 'image'" class="space-y-2">
                                    <ImageCropper :size="f.size || 600" :label="`${f.label} (${f.size || 600}×${f.size || 600})`" @update:file="form.custom[f.id] = $event" />
                                    <p v-if="editingId && detailsPlayer === null" class="text-[11.5px] text-ink-500">
                                        Leave blank to keep the existing image.
                                    </p>
                                </div>

                                <input v-else-if="f.type === 'payment'"
                                       v-model="form.custom[f.id]" type="text"
                                       :placeholder="f.placeholder ?? 'TrxID / bank reference'"
                                       :class="inputClasses" />
                            </Field>
                        </template>
                    </div>

                    <div class="md:col-span-3 flex gap-2 justify-end pt-1">
                        <button type="button" class="btn-ghost py-2 px-4 text-[13px]" @click="cancelEdit">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn-primary py-2 px-4 text-[13px]" :disabled="form.processing">
                            <template v-if="editingId">
                                {{ form.processing ? 'Updating…' : 'Update player' }}
                            </template>
                            <template v-else>
                                {{ form.processing ? t('forms.players.submitting') : t('forms.players.submit') }}
                            </template>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Filters -->
            <div class="glass rounded-2xl p-4 mb-4 flex flex-wrap items-center gap-3">
                <input v-model="filterForm.q" @input="applyFilters" :placeholder="t('players_page.search_placeholder')"
                       class="flex-1 min-w-[200px] rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                <select v-model="filterForm.category" @change="applyFilters" class="rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 text-[13px]">
                    <option value="">{{ t('players_page.all_categories') }}</option>
                    <option v-for="c in seasonCategoryNames" :key="c" :value="c">{{ c }}</option>
                </select>
                <select v-model="filterForm.type" @change="applyFilters" class="rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 text-[13px]">
                    <option value="">{{ t('players_page.old_plus_new') }}</option>
                    <option value="Old">{{ t('players_page.type_old') }}</option>
                    <option value="New">{{ t('players_page.type_new') }}</option>
                </select>
                <select v-model="filterForm.status" @change="applyFilters" class="rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 text-[13px]">
                    <option value="">{{ t('players_page.any_status') }}</option>
                    <option value="pending">{{ t('players_page.status_pending') }}</option>
                    <option value="queue">{{ t('players_page.status_queue') }}</option>
                    <option value="live">{{ t('players_page.status_live') }}</option>
                    <option value="sold">{{ t('players_page.status_sold') }}</option>
                    <option value="unsold">{{ t('players_page.status_unsold') }}</option>
                </select>
                <span class="text-[12px] font-mono text-ink-500">{{ used }} / {{ limits.players >= 999999999 ? t('billing.unlimited') : limits.players }}</span>
            </div>

            <!-- Pending review banner -->
            <div v-if="pending_count > 0 && filterForm.status !== 'pending'"
                 class="mb-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 flex items-center justify-between text-[13px]">
                <span class="text-amber-800">
                    {{ t('players_page.pending_review_msg', { count: pending_count }) }}
                </span>
                <div class="flex gap-2">
                    <button @click="filterPending" class="text-amber-900 underline font-medium text-[12.5px]">{{ t('players_page.view_pending') }}</button>
                    <button @click="approveAll" class="btn-primary py-1.5 px-3 text-[12px]">{{ t('players_page.approve_all') }}</button>
                </div>
            </div>

            <!-- Table -->
            <div class="glass rounded-2xl overflow-hidden">
                <table class="w-full text-[13.5px]">
                    <thead class="bg-white/50">
                        <tr class="text-left font-mono text-[10.5px] tracking-widest text-ink-500">
                            <th class="px-4 py-3">{{ t('players_page.th_name') }}</th>
                            <th class="px-4 py-3">{{ t('players_page.th_category') }}</th>
                            <th class="px-4 py-3">{{ t('players_page.th_type') }}</th>
                            <th class="px-4 py-3">{{ t('players_page.th_base') }}</th>
                            <th class="px-4 py-3">{{ t('players_page.th_status') }}</th>
                            <th class="px-4 py-3">{{ t('players_page.th_sold_to') }}</th>
                            <th class="px-4 py-3">{{ t('players_page.th_sold_price') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        <tr v-for="p in players.data" :key="p.id" class="hover:bg-white/40">
                            <td class="px-4 py-3">
                                <button @click="showDetails(p)"
                                        class="flex items-center gap-2.5 hover:bg-white/40 -mx-2 px-2 py-1 rounded-lg transition-colors text-left"
                                        :title="t('players_page.view_card_title')">
                                    <img v-if="p.photo_url" :src="p.photo_url" :alt="p.name"
                                         class="h-9 w-9 rounded-full object-cover border border-ink-200 shrink-0" />
                                    <div v-else class="avatar shrink-0 text-[10px]">{{ p.name.split(' ').map(s => s[0]).slice(0,2).join('') }}</div>
                                    <div>
                                        <div class="font-medium leading-tight hover:text-brand-indigo transition-colors">{{ p.name }}</div>
                                        <div v-if="p.jersey_no" class="text-[10.5px] font-mono text-ink-400">#{{ p.jersey_no }}</div>
                                    </div>
                                </button>
                            </td>
                            <td class="px-4 py-3 text-ink-700">{{ p.category }}</td>
                            <td class="px-4 py-3 text-ink-700">{{ p.player_type }}</td>
                            <td class="px-4 py-3 font-mono">{{ fmt(p.base_price) }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full font-mono text-[10.5px] uppercase border" :class="statusColor(p.auction_status)">{{ p.auction_status }}</span>
                            </td>
                            <td class="px-4 py-3 text-ink-700">{{ p.team?.name || '—' }}</td>
                            <td class="px-4 py-3 font-mono text-ink-700">{{ p.sold_price ? fmt(p.sold_price) : '—' }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button @click="showDetails(p)" class="text-ink-600 hover:text-ink-900 text-[12px] mr-3"
                                        :title="(p.registration_data && Object.keys(p.registration_data).length) ? t('players_page.view_submitted_responses') : t('players_page.view_player_details')">
                                    {{ t('players_page.details') }}
                                    <span v-if="p.registration_data && Object.keys(p.registration_data).length"
                                          class="ml-1 px-1.5 py-0.5 rounded-full bg-violet-100 text-violet-700 font-mono text-[9.5px]">
                                        {{ Object.keys(p.registration_data).length }}
                                    </span>
                                </button>
                                <template v-if="p.auction_status === 'pending'">
                                    <button @click="approve(p)" class="text-emerald-600 hover:text-emerald-700 text-[12px] font-medium mr-3">{{ t('players_page.approve') }}</button>
                                    <button @click="reject(p)"  class="text-rose-500 hover:text-rose-700 text-[12px]">{{ t('players_page.reject') }}</button>
                                </template>
                                <template v-else>
                                    <button @click="startEdit(p)" class="text-brand-indigo hover:underline text-[12px] mr-3"
                                            :class="{ 'opacity-50 pointer-events-none': p.auction_status === 'live' || p.auction_status === 'sold' }"
                                            :disabled="p.auction_status === 'live' || p.auction_status === 'sold'"
                                            :title="p.auction_status === 'live' ? t('players_page.cannot_edit_live') : p.auction_status === 'sold' ? t('players_page.cannot_edit_sold') : t('players_page.edit_title')">
                                        {{ t('players_page.edit') }}
                                    </button>
                                    <button @click="remove(p)" class="text-rose-500 hover:text-rose-700 text-[12px]">{{ t('players_page.delete') }}</button>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="players.data.length === 0">
                            <td colspan="8" class="px-4 py-12 text-center text-ink-500 text-[13.5px]">
                                <i18n-t keypath="players_page.empty_state">
                                    <template #add><strong>{{ t('players_page.add_player_strong') }}</strong></template>
                                </i18n-t>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="players.last_page > 1" class="mt-4 flex justify-center gap-1">
                <Link v-for="link in players.links" :key="link.label" :href="link.url || '#'"
                      v-html="link.label"
                      class="px-3 py-1.5 rounded-lg text-[13px] font-mono"
                      :class="link.active ? 'bg-gradient-brand text-white' : link.url ? 'text-ink-700 hover:bg-white/60' : 'text-ink-300'"
                      :preserve-scroll="true" />
            </div>
        </template>

        <!-- ============== Premium Player Card (Details modal) ============== -->
        <!-- flex justify-center keeps the card horizontally centered; items-start
             lets it sit near the top so a tall card scrolls naturally without
             overflowing off-screen on short viewports. -->
        <div v-if="detailsPlayer"
             class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-ink-900/55 backdrop-blur-sm p-4 pt-10"
             @click.self="closeDetails">
            <div class="player-card-wrap max-w-2xl w-full">
                <div class="rounded-3xl overflow-hidden shadow-glass-lg bg-white/95 backdrop-blur-md ring-1 ring-ink-200/40">

                    <!-- ============== HERO (centered, vertical layout) ============== -->
                    <div class="relative px-6 sm:px-8 pt-8 pb-8 text-white overflow-hidden text-center"
                         style="background:linear-gradient(135deg,#0a0e27 0%,#3b1d6e 50%,#1a4480 100%);">
                        <!-- decorative blobs -->
                        <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full pointer-events-none"
                             style="background:radial-gradient(circle,rgba(99,102,241,.45),transparent 70%);"></div>
                        <div class="absolute -bottom-20 -left-12 w-64 h-64 rounded-full pointer-events-none"
                             style="background:radial-gradient(circle,rgba(34,211,238,.35),transparent 70%);"></div>
                        <div class="absolute inset-0 grid-dark-bg opacity-20 pointer-events-none"></div>

                        <button @click="closeDetails" class="absolute top-3 right-3 z-10 grid place-items-center h-8 w-8 rounded-full bg-white/10 hover:bg-white/20 text-white text-[16px] backdrop-blur-sm">
                            ×
                        </button>

                        <!-- Photo centered + bigger -->
                        <div class="relative inline-block mb-5">
                            <img v-if="detailsPlayer.photo_url" :src="detailsPlayer.photo_url" :alt="detailsPlayer.name"
                                 class="h-36 w-36 sm:h-44 sm:w-44 rounded-[28px] object-cover ring-4 ring-white/30 shadow-2xl mx-auto" />
                            <div v-else class="h-36 w-36 sm:h-44 sm:w-44 rounded-[28px] grid place-items-center font-extrabold text-[40px] sm:text-[48px] ring-4 ring-white/30 shadow-2xl mx-auto"
                                 style="background:linear-gradient(135deg,rgba(99,102,241,.5),rgba(139,92,246,.5));">
                                {{ detailsPlayer.name.split(' ').map(s => s[0]).slice(0,2).join('') }}
                            </div>
                            <span v-if="detailsPlayer.jersey_no"
                                  class="absolute -bottom-2 -right-2 grid place-items-center h-11 w-11 rounded-full bg-white text-ink-900 font-mono text-[15px] font-bold shadow-lg">
                                #{{ detailsPlayer.jersey_no }}
                            </span>
                        </div>

                        <!-- Name + badges below the photo -->
                        <div class="relative">
                            <div class="font-mono text-[10px] tracking-widest text-white/60 mb-1">/ PLAYER</div>
                            <h2 class="text-[26px] sm:text-[32px] font-extrabold tracking-tight leading-tight">
                                {{ detailsPlayer.name }}
                            </h2>
                            <div class="mt-4 flex flex-wrap gap-1.5 justify-center">
                                <span v-if="detailsPlayer.position"
                                      class="px-3 py-1 rounded-full font-mono text-[10.5px] tracking-widest uppercase border border-white/20"
                                      style="background:linear-gradient(135deg,rgba(99,102,241,.35),rgba(139,92,246,.35));">
                                    {{ detailsPlayer.position }}
                                </span>
                                <span class="px-3 py-1 rounded-full font-mono text-[10.5px] tracking-widest uppercase bg-white/10 border border-white/15">
                                    {{ detailsPlayer.category }}
                                </span>
                                <span class="px-3 py-1 rounded-full font-mono text-[10.5px] tracking-widest uppercase border"
                                      :class="auctionBadgeDark(detailsPlayer.auction_status)">
                                    {{ detailsPlayer.auction_status }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- ============== STATS GRID ============== -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-px bg-ink-100">
                        <div class="bg-white px-4 py-4 text-center">
                            <div class="font-mono text-[9.5px] tracking-widest text-ink-500">{{ t('players_page.label_base_price') }}</div>
                            <div class="text-[18px] sm:text-[20px] font-extrabold tracking-tight font-mono mt-1 leading-none">{{ fmt(detailsPlayer.base_price) }}</div>
                        </div>
                        <div class="bg-white px-4 py-4 text-center">
                            <div class="font-mono text-[9.5px] tracking-widest text-ink-500">{{ t('players_page.label_sold_price') }}</div>
                            <div class="mt-1 leading-none">
                                <span v-if="detailsPlayer.sold_price" class="text-[18px] sm:text-[20px] font-extrabold tracking-tight font-mono text-emerald-600">{{ fmt(detailsPlayer.sold_price) }}</span>
                                <span v-else class="text-[14px] text-ink-400 font-mono">—</span>
                            </div>
                        </div>
                        <div class="bg-white px-4 py-4 text-center">
                            <div class="font-mono text-[9.5px] tracking-widest text-ink-500">{{ t('players_page.label_sold_to') }}</div>
                            <div class="mt-1 leading-none">
                                <span v-if="detailsPlayer.team?.name" class="text-[14px] sm:text-[15px] font-bold tracking-tight">{{ detailsPlayer.team.name }}</span>
                                <span v-else class="text-[14px] text-ink-400 font-mono">—</span>
                            </div>
                        </div>
                        <div class="bg-white px-4 py-4 text-center">
                            <div class="font-mono text-[9.5px] tracking-widest text-ink-500">{{ t('players_page.label_type') }}</div>
                            <div class="mt-1 leading-none text-[14px] sm:text-[15px] font-bold tracking-tight">
                                {{ detailsPlayer.player_type }}
                            </div>
                        </div>
                    </div>

                    <!-- ============== BODY ============== -->
                    <div class="px-6 sm:px-8 py-6 space-y-5">

                        <!-- Built-in details -->
                        <div v-if="detailsPlayer.profession || (isCricket && (detailsPlayer.batting_style || detailsPlayer.bowling_style)) || detailsPlayer.registration_txn_id">
                            <div class="font-mono text-[10.5px] tracking-widest text-ink-500 mb-2">/ PLAYER PROFILE</div>
                            <dl class="rounded-2xl bg-ink-50/60 border border-ink-200/60 px-5 py-4 space-y-2 text-[13.5px]">
                                <div v-if="detailsPlayer.profession" class="flex justify-between gap-3">
                                    <dt class="text-ink-500">{{ t('players_page.label_profession') }}</dt>
                                    <dd class="font-medium text-ink-900 truncate text-right">{{ detailsPlayer.profession }}</dd>
                                </div>
                                <div v-if="isCricket && detailsPlayer.batting_style" class="flex justify-between gap-3">
                                    <dt class="text-ink-500">{{ t('players_page.label_batting') }}</dt>
                                    <dd class="font-medium text-ink-900 truncate text-right">{{ detailsPlayer.batting_style }}</dd>
                                </div>
                                <div v-if="isCricket && detailsPlayer.bowling_style" class="flex justify-between gap-3">
                                    <dt class="text-ink-500">{{ t('players_page.label_bowling') }}</dt>
                                    <dd class="font-medium text-ink-900 truncate text-right">{{ detailsPlayer.bowling_style }}</dd>
                                </div>
                                <div v-if="detailsPlayer.registration_txn_id" class="flex justify-between gap-3 pt-1 border-t border-ink-200/60">
                                    <dt class="text-ink-500">{{ t('players_page.label_reg_trxid') }}</dt>
                                    <dd class="font-mono text-[12.5px] text-ink-700 truncate text-right">{{ detailsPlayer.registration_txn_id }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Custom registration form responses -->
                        <div v-if="detailsPlayer.registration_data && Object.keys(detailsPlayer.registration_data).length">
                            <div class="flex items-center justify-between mb-2">
                                <div class="font-mono text-[10.5px] tracking-widest text-violet-700">/ FORM RESPONSES</div>
                                <span class="font-mono text-[10.5px] text-ink-500">{{ Object.keys(detailsPlayer.registration_data).length }} field{{ Object.keys(detailsPlayer.registration_data).length === 1 ? '' : 's' }}</span>
                            </div>
                            <div class="rounded-2xl bg-violet-50/50 border border-violet-200/60 p-4 space-y-3">
                                <div v-for="(entry, key) in detailsPlayer.registration_data" :key="key"
                                     class="rounded-lg bg-white border border-ink-200/60 px-3 py-2.5">
                                    <div class="font-mono text-[9.5px] tracking-widest text-violet-700 mb-1">{{ entry.label }}</div>
                                    <a v-if="isImageValue(entry.value)" :href="entry.value" target="_blank" class="inline-block">
                                        <img :src="entry.value" :alt="entry.label" class="h-32 rounded-lg border border-ink-200 object-cover" />
                                    </a>
                                    <div v-else class="text-[13.5px] text-ink-900 leading-snug break-words">{{ entry.value }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============== ACTIONS ============== -->
                    <div class="px-6 sm:px-8 pb-6 pt-2 flex flex-wrap gap-2 justify-end">
                        <template v-if="detailsPlayer.auction_status === 'pending'">
                            <button @click="reject(detailsPlayer); closeDetails()" class="btn-ghost py-2 px-4 text-[13px] text-rose-600 border-rose-200 hover:bg-rose-50">{{ t('players_page.reject') }}</button>
                            <button @click="approve(detailsPlayer); closeDetails()" class="btn-primary py-2 px-4 text-[13px]">{{ t('players_page.approve_into_queue') }}</button>
                        </template>
                        <template v-else-if="detailsPlayer.auction_status !== 'live' && detailsPlayer.auction_status !== 'sold'">
                            <button @click="closeDetails" class="btn-ghost py-2 px-4 text-[13px]">{{ t('players_page.close') }}</button>
                            <button @click="editFromDetails" class="btn-primary py-2 px-4 text-[13px]">{{ t('players_page.edit_player') }}</button>
                        </template>
                        <template v-else>
                            <button @click="closeDetails" class="btn-primary py-2 px-4 text-[13px]">{{ t('players_page.close') }}</button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>

<style scoped>
.player-card-wrap {
    animation: cardSlide 0.32s cubic-bezier(.2,.9,.3,1.4);
}
@keyframes cardSlide {
    from { opacity: 0; transform: translateY(20px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0)    scale(1);    }
}
</style>
