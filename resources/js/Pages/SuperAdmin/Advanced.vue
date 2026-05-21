<script setup>
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import Field from '@/Components/Field.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    scriptSettings: { type: Object, default: () => ({}) },
});

const scriptForm = useForm({
    head_scripts: props.scriptSettings.head_scripts || '',
    body_start_scripts: props.scriptSettings.body_start_scripts || '',
    body_end_scripts: props.scriptSettings.body_end_scripts || '',
});

const saveScripts = () => {
    scriptForm.patch(route('admin.advanced.scripts.update'), { preserveScroll: true });
};
</script>

<template>
    <Head title="Advanced | Super Admin" />
    <SuperAdminLayout title="Advanced">
        <section class="glass rounded-2xl p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between mb-5">
                <div>
                    <h2 class="text-[20px] font-extrabold tracking-tight">Header, body, and footer scripts</h2>
                    <p class="mt-1 max-w-3xl text-[13px] leading-6 text-ink-500">
                        Use for analytics, pixels, chat widgets, or custom verification scripts. These render raw on every page, so paste only trusted code.
                    </p>
                </div>
                <button type="button" @click="saveScripts" class="btn-primary shrink-0 py-2.5 px-5 text-[13px]" :disabled="scriptForm.processing">
                    {{ scriptForm.processing ? 'Saving...' : 'Save scripts' }}
                </button>
            </div>
            <div class="grid lg:grid-cols-3 gap-4">
                <Field label="Header scripts" hint="Before </head>" :error="scriptForm.errors.head_scripts">
                    <textarea v-model="scriptForm.head_scripts" rows="13" class="font-mono w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-3 text-[12.5px] leading-5 focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" placeholder="<script>...</script>" />
                </Field>
                <Field label="Body scripts" hint="After <body>" :error="scriptForm.errors.body_start_scripts">
                    <textarea v-model="scriptForm.body_start_scripts" rows="13" class="font-mono w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-3 text-[12.5px] leading-5 focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" placeholder="<noscript>...</noscript>" />
                </Field>
                <Field label="Footer scripts" hint="Before </body>" :error="scriptForm.errors.body_end_scripts">
                    <textarea v-model="scriptForm.body_end_scripts" rows="13" class="font-mono w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-3 text-[12.5px] leading-5 focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" placeholder="<script src='...'></script>" />
                </Field>
            </div>
        </section>
    </SuperAdminLayout>
</template>
