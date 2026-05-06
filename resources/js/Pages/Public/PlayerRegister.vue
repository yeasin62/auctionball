<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import Field from '@/Components/Field.vue';
import TextField from '@/Components/TextField.vue';
import ImageCropper from '@/Components/ImageCropper.vue';
import { computed } from 'vue';

const props = defineProps({ org: Object, season: Object, positions: { type: Array, default: () => [] } });
const page = usePage();

const isCricket = props.season?.sport !== 'football';
const customFields = props.season?.custom_fields ?? [];

// Pre-seed the `custom` map with one slot per dynamic field so v-model bindings
// stay reactive (Vue won't re-track new keys added after form creation).
const customSeed = {};
customFields.forEach((f) => {
    if (f.type === 'heading')        return;                  // not an input
    else if (f.type === 'checkbox')  customSeed[f.id] = false;
    else if (f.type === 'multi')     customSeed[f.id] = [];   // array of selected option strings
    else if (f.type === 'image')     customSeed[f.id] = null; // File object once cropped
    else                              customSeed[f.id] = '';
});

// Reusable Tailwind classes for raw inputs — matches TextField visual style so
// browser-native widgets (date picker, number stepper, time picker) inherit
// padding/border without the wrapper component swallowing the input type.
const inputClasses =
    'w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] ' +
    'placeholder:text-ink-400 focus:outline-none focus:ring-2 focus:ring-brand-indigo/30 ' +
    'focus:border-brand-indigo transition-shadow shadow-sm';

// Toggle a value into the multi-select array.
const toggleMulti = (fieldId, opt) => {
    const arr = form.custom[fieldId] || [];
    const i = arr.indexOf(opt);
    if (i >= 0) arr.splice(i, 1); else arr.push(opt);
    form.custom[fieldId] = [...arr];
};

// Payment-field helpers — kind-aware visuals + copy-to-clipboard for numbers/accounts.
const methodTone = (kind) => ({
    bkash:  { bg: 'bg-pink-50/80',    border: 'border-pink-200',    accent: 'text-pink-700',    dot: 'bg-pink-500'   },
    nagad:  { bg: 'bg-amber-50/80',   border: 'border-amber-200',   accent: 'text-amber-700',   dot: 'bg-amber-500'  },
    rocket: { bg: 'bg-violet-50/80',  border: 'border-violet-200',  accent: 'text-violet-700',  dot: 'bg-violet-500' },
    bank:   { bg: 'bg-blue-50/80',    border: 'border-blue-200',    accent: 'text-blue-700',    dot: 'bg-blue-500'   },
    other:  { bg: 'bg-ink-50',        border: 'border-ink-200',     accent: 'text-ink-700',     dot: 'bg-ink-400'    },
}[kind] || { bg: 'bg-ink-50', border: 'border-ink-200', accent: 'text-ink-700', dot: 'bg-ink-400' });

const methodKindName = (kind) => ({ bkash: 'bKash', nagad: 'Nagad', rocket: 'Rocket', bank: 'Bank account', other: 'Other' }[kind] || kind);
const methodCopy = (txt) => { if (txt) navigator.clipboard?.writeText(txt); };

const form = useForm({
    name: '',
    category: 'Regular',
    position: '',
    jersey_no: '',
    batting_style: '',
    bowling_style: '',
    profession: '',
    photo: null,
    registration_txn_id: '',
    custom: customSeed,
});

const fee = computed(() => props.season.registration_fee);
// If the org has added a custom "payment" field via the builder, the legacy
// fee+TrxID block is redundant (payment field collects it). Hide it.
const hasPaymentField = customFields.some((f) => f.type === 'payment');
// Mark the first payment field so the fee headline can render right above it
// (and not get repeated if the org has multiple payment blocks).
const firstPaymentFieldId = customFields.find((f) => f.type === 'payment')?.id;

/**
 * Per-field visibility — true unless a `conditional` rule says hide. Mirrors
 * the server-side evaluator in PublicRegistrationController so client + server
 * make the same decision and never disagree on what got submitted.
 */
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

// Public page: org-driven currency display (props pass display_currency + rate);
// page.props.locale flips digits between Bengali and Western.
const fmt = (n) => {
    const cur  = props.org?.display_currency ?? 'BDT';
    const rate = Math.max(1, props.org?.bdt_per_usd ?? 110);
    const lang = (page.props.locale === 'bn') ? 'bn-IN' : 'en-IN';
    const v    = Number(n) || 0;
    if (cur === 'USD') {
        return '$' + new Intl.NumberFormat(lang).format(Math.round(v / rate));
    }
    return '৳' + new Intl.NumberFormat(lang).format(v);
};

// forceFormData ensures multipart/form-data even if the user uploads no files —
// keeps the encoding consistent across submissions when image fields are dynamic.
const submit = () => form.post(route('public-register.store', props.season.token), {
    forceFormData: true,
    onSuccess: () => form.reset(),
});
</script>

<template>
    <Head :title="`Register · ${org.name} · ${season.name}`" />
    <div class="page-bg min-h-screen">
        <header class="px-6 py-5 border-b border-ink-200/40 bg-white/40 backdrop-blur-md">
            <div class="max-w-2xl mx-auto flex items-center gap-3">
                <span class="grid place-items-center h-9 w-9 rounded-lg bg-gradient-brand shadow-cta">
                    <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12h8M8 8h5"/></svg>
                </span>
                <div>
                    <div class="text-[15px] font-bold tracking-tight">{{ org.name }}</div>
                    <div class="font-mono text-[10.5px] text-ink-500">{{ season.name }} · {{ season.year }} · player registration</div>
                </div>
            </div>
        </header>

        <main class="max-w-2xl mx-auto px-6 py-10">
            <div v-if="page.props.flash?.success" class="mb-5 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-[13.5px] text-emerald-800">
                {{ page.props.flash.success }}
            </div>

            <div class="text-center mb-7">
                <h1 class="text-[34px] font-extrabold tracking-tight">Register as a player</h1>
                <p class="mt-2 text-ink-500 max-w-md mx-auto text-[14px]">
                    Fill in your details. The organizer will review your registration before the auction starts.
                </p>
            </div>

            <div v-if="season.registration_instructions" class="mb-5 rounded-xl bg-white/70 border border-ink-200/60 px-5 py-4 text-[13.5px] text-ink-700 whitespace-pre-line">
                {{ season.registration_instructions }}
            </div>

            <form @submit.prevent="submit" class="glass-strong rounded-2xl p-7 space-y-5">
                <div class="text-[15px] font-bold tracking-wider text-ink-800">YOUR DETAILS</div>

                <Field label="Full name" :error="form.errors.name" required>
                    <TextField v-model="form.name" placeholder="Shakib Rahman" autofocus />
                </Field>

                <div class="grid md:grid-cols-2 gap-4">
                    <Field label="Category" :error="form.errors.category" required>
                        <select v-model="form.category" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                            <option>Elite</option><option>Regular</option><option>New</option>
                        </select>
                    </Field>
                    <Field :label="isCricket ? 'Player position' : 'Position'" :error="form.errors.position">
                        <select v-model="form.position" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                            <option value="">—</option>
                            <option v-for="p in positions" :key="p" :value="p">{{ p }}</option>
                        </select>
                    </Field>
                    <Field label="Jersey #" :error="form.errors.jersey_no">
                        <TextField v-model="form.jersey_no" placeholder="07" />
                    </Field>
                    <Field label="Profession" :error="form.errors.profession">
                        <TextField v-model="form.profession" placeholder="Software engineer" />
                    </Field>
                    <template v-if="isCricket">
                        <Field label="Batting style" :error="form.errors.batting_style">
                            <TextField v-model="form.batting_style" placeholder="Right-hand bat" />
                        </Field>
                        <Field label="Bowling style" :error="form.errors.bowling_style">
                            <TextField v-model="form.bowling_style" placeholder="Right-arm off-spin" />
                        </Field>
                    </template>
                </div>

                <div class="pt-3 border-t border-ink-200/60">
                    <ImageCropper :size="300" label="Player photo (300×300)" @update:file="form.photo = $event" />
                    <p v-if="form.errors.photo" class="mt-1.5 text-[12.5px] text-rose-500">{{ form.errors.photo }}</p>
                </div>

                <!-- ============== Org-defined custom fields ============== -->
                <div v-if="customFields.length" class="pt-4 border-t border-ink-200/60 space-y-4">
                    <div class="text-[15px] font-bold tracking-wider text-ink-800">ADDITIONAL DETAILS</div>

                    <template v-for="f in customFields" :key="f.id">
                        <!-- Fee headline — sits immediately above the first payment field
                             so players see what they owe right before the bKash/bank cards. -->
                        <div v-if="f.id === firstPaymentFieldId && fee > 0"
                             class="rounded-xl bg-gradient-to-r from-amber-50 to-amber-100/60 border border-amber-200 px-5 py-3 flex items-center gap-3">
                            <span class="grid place-items-center h-9 w-9 rounded-lg bg-amber-200/70 shrink-0">
                                <svg class="h-4 w-4 text-amber-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3 1.34 3 3-1.34 3-3 3m0-12V6m0 12v2"/></svg>
                            </span>
                            <div class="flex-1">
                                <div class="font-mono text-[10.5px] tracking-widest text-amber-700">REGISTRATION FEE</div>
                                <div class="text-[20px] font-extrabold tracking-tight text-amber-900 leading-none">{{ fmt(fee) }}</div>
                            </div>
                        </div>

                        <!-- Section header: visual divider, no input -->
                        <div v-if="f.type === 'heading'" v-show="isFieldVisible(f)" class="pt-2">
                            <div class="font-mono text-[10.5px] tracking-widest text-ink-500">/ {{ f.label?.toUpperCase() }}</div>
                            <div class="mt-1 h-px bg-ink-200/60"></div>
                        </div>

                        <!-- Inline yes/no checkbox: standalone label, accented input,
                             whole row clickable, checked-row gets a tint so the state
                             is obvious even on a glance. -->
                        <label v-else-if="f.type === 'checkbox'" v-show="isFieldVisible(f)"
                               class="flex items-start gap-3 px-4 py-3 rounded-xl border cursor-pointer transition-colors"
                               :class="form.custom[f.id]
                                   ? 'bg-emerald-50/70 border-emerald-200'
                                   : 'bg-white/70 border-ink-200/60 hover:bg-white/95'">
                            <input type="checkbox" v-model="form.custom[f.id]"
                                   class="h-5 w-5 mt-0.5 shrink-0 rounded accent-brand-indigo cursor-pointer" />
                            <span class="flex-1">
                                <span class="text-[14px] text-ink-800">{{ f.label }}<span v-if="f.required" class="text-rose-500 ml-0.5">*</span></span>
                                <span v-if="form.custom[f.id]" class="ml-2 font-mono text-[10.5px] tracking-widest text-emerald-700 uppercase">checked</span>
                                <span v-if="form.errors[`custom.${f.id}`]" class="block mt-1 text-[12px] text-rose-500">{{ form.errors[`custom.${f.id}`] }}</span>
                            </span>
                        </label>

                        <!-- All other types use the labeled Field wrapper -->
                        <Field v-else
                               v-show="isFieldVisible(f)"
                               :label="f.label"
                               :error="form.errors[`custom.${f.id}`]"
                               :required="!!f.required">

                            <input v-if="f.type === 'text'"
                                   v-model="form.custom[f.id]"
                                   type="text" :placeholder="f.placeholder ?? ''" :class="inputClasses" />

                            <input v-else-if="f.type === 'number'"
                                   v-model="form.custom[f.id]"
                                   type="number" inputmode="decimal" step="any"
                                   :placeholder="f.placeholder ?? ''" :class="inputClasses" />

                            <input v-else-if="f.type === 'email'"
                                   v-model="form.custom[f.id]"
                                   type="email" autocomplete="email"
                                   :placeholder="f.placeholder ?? ''" :class="inputClasses" />

                            <input v-else-if="f.type === 'phone'"
                                   v-model="form.custom[f.id]"
                                   type="tel" inputmode="tel" autocomplete="tel"
                                   :placeholder="f.placeholder ?? '01XXX-XXXXXXX'" :class="inputClasses" />

                            <input v-else-if="f.type === 'url'"
                                   v-model="form.custom[f.id]"
                                   type="url" inputmode="url"
                                   :placeholder="f.placeholder ?? 'https://example.com'" :class="inputClasses" />

                            <input v-else-if="f.type === 'date'"
                                   v-model="form.custom[f.id]"
                                   type="date" :class="inputClasses" />

                            <input v-else-if="f.type === 'time'"
                                   v-model="form.custom[f.id]"
                                   type="time" :class="inputClasses" />

                            <textarea v-else-if="f.type === 'textarea'"
                                      v-model="form.custom[f.id]"
                                      :placeholder="f.placeholder ?? ''"
                                      rows="3"
                                      :class="inputClasses"></textarea>

                            <select v-else-if="f.type === 'select'"
                                    v-model="form.custom[f.id]"
                                    :class="inputClasses">
                                <option value="">— select —</option>
                                <option v-for="opt in (f.options || [])" :key="opt" :value="opt">{{ opt }}</option>
                            </select>

                            <!-- Radio: single choice, vertical list, selected-row tint -->
                            <div v-else-if="f.type === 'radio'" class="space-y-1.5 pt-1">
                                <label v-for="opt in (f.options || [])" :key="opt"
                                       class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg border cursor-pointer transition-colors"
                                       :class="form.custom[f.id] === opt
                                           ? 'bg-indigo-50/70 border-indigo-200'
                                           : 'bg-white/70 border-ink-200/60 hover:bg-white/95'">
                                    <input type="radio" :name="`field-${f.id}`" :value="opt" v-model="form.custom[f.id]"
                                           class="h-4 w-4 accent-brand-indigo cursor-pointer" />
                                    <span class="text-[13.5px] text-ink-800">{{ opt }}</span>
                                </label>
                            </div>

                            <!-- Multi-select checkboxes — selected-row tint -->
                            <div v-else-if="f.type === 'multi'" class="space-y-1.5 pt-1">
                                <label v-for="opt in (f.options || [])" :key="opt"
                                       class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg border cursor-pointer transition-colors"
                                       :class="(form.custom[f.id] || []).includes(opt)
                                           ? 'bg-emerald-50/70 border-emerald-200'
                                           : 'bg-white/70 border-ink-200/60 hover:bg-white/95'">
                                    <input type="checkbox" :checked="(form.custom[f.id] || []).includes(opt)"
                                           @change="toggleMulti(f.id, opt)"
                                           class="h-4 w-4 accent-brand-indigo cursor-pointer" />
                                    <span class="text-[13.5px] text-ink-800">{{ opt }}</span>
                                </label>
                            </div>

                            <ImageCropper v-else-if="f.type === 'image'"
                                          :size="f.size || 600"
                                          :label="`${f.label} (${f.size || 600}×${f.size || 600})`"
                                          @update:file="form.custom[f.id] = $event" />

                            <!-- Payment: stack of method cards + a single TrxID input -->
                            <div v-else-if="f.type === 'payment'" class="space-y-3">
                                <div v-for="(m, mi) in (f.methods || [])" :key="mi"
                                     class="rounded-xl border px-4 py-3 flex items-start gap-3"
                                     :class="[methodTone(m.kind).bg, methodTone(m.kind).border]">
                                    <span class="grid place-items-center h-9 w-9 rounded-lg shrink-0 mt-0.5 font-mono text-[10px] font-bold tracking-widest text-white"
                                          :class="methodTone(m.kind).dot">
                                        {{ (m.kind || 'X').slice(0,2).toUpperCase() }}
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-[13px] font-bold tracking-tight" :class="methodTone(m.kind).accent">
                                            {{ m.label || methodKindName(m.kind) }}
                                        </div>
                                        <!-- Bank account body -->
                                        <div v-if="m.kind === 'bank'" class="mt-1 space-y-0.5">
                                            <div class="text-[12.5px] text-ink-700">{{ m.bank }}<span v-if="m.holder"> · {{ m.holder }}</span></div>
                                            <div class="flex items-center gap-2">
                                                <code class="font-mono text-[14px] font-bold text-ink-900 bg-white px-2 py-0.5 rounded border border-ink-200">{{ m.account }}</code>
                                                <button type="button" @click="methodCopy(m.account)" class="btn-ghost py-1 px-2 text-[10.5px]">Copy</button>
                                            </div>
                                            <div v-if="m.branch" class="text-[11px] text-ink-500">{{ m.branch }}</div>
                                        </div>
                                        <!-- Mobile wallet body (bkash / nagad / rocket / other) -->
                                        <div v-else class="mt-1">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <code class="font-mono text-[15px] font-bold text-ink-900 bg-white px-2.5 py-0.5 rounded border border-ink-200">{{ m.number }}</code>
                                                <button type="button" @click="methodCopy(m.number)" class="btn-ghost py-1 px-2 text-[10.5px]">Copy</button>
                                            </div>
                                            <div v-if="m.instructions" class="mt-1 text-[11.5px] text-ink-600">{{ m.instructions }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- TrxID input — single text the player submits -->
                                <div class="rounded-xl bg-emerald-50/70 border border-emerald-200/70 p-3">
                                    <label class="font-mono text-[10.5px] tracking-widest text-emerald-800">YOUR TRANSACTION ID / REFERENCE</label>
                                    <input v-model="form.custom[f.id]" type="text"
                                           :placeholder="f.placeholder ?? 'e.g. 9F4XYZ123 (bKash) or bank reference number'"
                                           class="mt-1.5 w-full rounded-lg border border-emerald-200 bg-white px-3 py-2 text-[14px] font-mono focus:outline-none focus:ring-2 focus:ring-emerald-300" />
                                    <p class="mt-1.5 text-[11.5px] text-emerald-700">
                                        Paste the TrxID from your bKash SMS, or your bank deposit reference. We verify before approving the registration.
                                    </p>
                                </div>
                            </div>
                        </Field>
                    </template>
                </div>

                <!-- Legacy TrxID block — only when fee > 0 AND no custom payment field -->
                <div v-if="fee > 0 && !hasPaymentField" class="pt-3 border-t border-ink-200/60 space-y-4">
                    <!-- Fee headline directly above the Pay registration fee field -->
                    <div class="rounded-xl bg-gradient-to-r from-amber-50 to-amber-100/60 border border-amber-200 px-5 py-3 flex items-center gap-3">
                        <span class="grid place-items-center h-9 w-9 rounded-lg bg-amber-200/70 shrink-0">
                            <svg class="h-4 w-4 text-amber-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3 1.34 3 3-1.34 3-3 3m0-12V6m0 12v2"/></svg>
                        </span>
                        <div class="flex-1">
                            <div class="font-mono text-[10.5px] tracking-widest text-amber-700">REGISTRATION FEE</div>
                            <div class="text-[20px] font-extrabold tracking-tight text-amber-900 leading-none">{{ fmt(fee) }}</div>
                        </div>
                    </div>
                    <div class="text-[15px] font-bold tracking-wider text-ink-800">PAYMENT</div>
                    <Field :label="`Transaction ID (${fmt(fee)} paid)`" :error="form.errors.registration_txn_id" required>
                        <TextField v-model="form.registration_txn_id" placeholder="bKash / Nagad / Rocket TxnID" />
                    </Field>
                </div>

                <button type="submit" class="btn-primary w-full py-3"
                        :disabled="form.processing"
                        :class="{ 'opacity-60 pointer-events-none': form.processing }">
                    {{ form.processing ? 'Submitting…' : 'Submit registration' }}
                </button>
            </form>
        </main>
    </div>
</template>
