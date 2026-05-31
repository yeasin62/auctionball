<script setup>
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import Field from '@/Components/Field.vue';
import TextField from '@/Components/TextField.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps({
    integrations: {
        type: Object,
        default: () => ({
            openai: {
                model: 'gpt-5.5',
                has_database_key: false,
                has_env_key: false,
            },
            anthropic: {
                model: 'claude-opus-4-1-20250805',
                has_database_key: false,
                has_env_key: false,
            },
            ai_provider: 'auto',
        }),
    },
});

const form = useForm({
    ai_provider: props.integrations.ai_provider || 'auto',
    openai_api_key: '',
    openai_model: props.integrations.openai?.model || 'gpt-5.5',
    clear_openai_api_key: false,
    anthropic_api_key: '',
    anthropic_model: props.integrations.anthropic?.model || 'claude-opus-4-1-20250805',
    clear_anthropic_api_key: false,
});

const fallbackOpenAiModels = [
    { value: 'gpt-5.5', label: 'GPT-5.5 (Latest)' },
    { value: 'gpt-5.5-pro', label: 'GPT-5.5 Pro' },
    { value: 'gpt-5.4', label: 'GPT-5.4' },
    { value: 'gpt-5.4-mini', label: 'GPT-5.4 Mini' },
    { value: 'gpt-5.4-nano', label: 'GPT-5.4 Nano' },
    { value: 'gpt-5.2', label: 'GPT-5.2' },
    { value: 'gpt-5.2-pro', label: 'GPT-5.2 Pro' },
    { value: 'gpt-5.1', label: 'GPT-5.1' },
    { value: 'gpt-5', label: 'GPT-5' },
    { value: 'gpt-5-mini', label: 'GPT-5 Mini' },
    { value: 'gpt-5-nano', label: 'GPT-5 Nano' },
    { value: 'gpt-4.1', label: 'GPT-4.1' },
    { value: 'gpt-4.1-mini', label: 'GPT-4.1 Mini' },
];

const fallbackAnthropicModels = [
    { value: 'claude-opus-4-8', label: 'Claude Opus 4.8 (Latest / if enabled)' },
    { value: 'claude-sonnet-4-8', label: 'Claude Sonnet 4.8 (if enabled)' },
    { value: 'claude-opus-4-1-20250805', label: 'Claude Opus 4.1 (Latest official)' },
    { value: 'claude-opus-4-1', label: 'Claude Opus 4.1 Alias' },
    { value: 'claude-opus-4-20250514', label: 'Claude Opus 4' },
    { value: 'claude-opus-4-0', label: 'Claude Opus 4 Alias' },
    { value: 'claude-sonnet-4-20250514', label: 'Claude Sonnet 4' },
    { value: 'claude-sonnet-4-0', label: 'Claude Sonnet 4 Alias' },
    { value: 'claude-3-7-sonnet-20250219', label: 'Claude Sonnet 3.7' },
    { value: 'claude-3-7-sonnet-latest', label: 'Claude Sonnet 3.7 Latest Alias' },
    { value: 'claude-3-5-haiku-20241022', label: 'Claude Haiku 3.5' },
    { value: 'claude-3-5-haiku-latest', label: 'Claude Haiku 3.5 Latest Alias' },
];

const openAiModels = ref([...fallbackOpenAiModels]);
const anthropicModels = ref([...fallbackAnthropicModels]);
const modelFetchState = ref({
    openai: 'idle',
    anthropic: 'idle',
});

const mergeModels = (fetched, fallback) => {
    const seen = new Set();

    return [...fetched, ...fallback].filter((model) => {
        if (!model?.value || seen.has(model.value)) return false;
        seen.add(model.value);
        return true;
    });
};

const fetchProviderModels = async (provider) => {
    modelFetchState.value[provider] = 'loading';

    try {
        const response = await window.axios.get(route('admin.integrations.models', { provider }));
        const fetched = (response.data?.models || []).map((model) => ({
            value: model.value,
            label: model.label || model.value,
        }));

        if (provider === 'openai') {
            openAiModels.value = mergeModels(fetched, fallbackOpenAiModels);
        } else {
            anthropicModels.value = mergeModels(fetched, fallbackAnthropicModels);
        }

        modelFetchState.value[provider] = fetched.length ? 'loaded' : 'fallback';
    } catch (error) {
        modelFetchState.value[provider] = 'fallback';
    }
};

onMounted(() => {
    fetchProviderModels('openai');
    fetchProviderModels('anthropic');
});

const save = () => {
    form.patch(route('admin.integrations.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.openai_api_key = '';
            form.clear_openai_api_key = false;
            form.anthropic_api_key = '';
            form.clear_anthropic_api_key = false;
        },
    });
};
</script>

<template>
    <Head title="Integrations | Super Admin" />

    <SuperAdminLayout title="Integrations">
        <div class="grid gap-5 xl:grid-cols-12">
            <section class="xl:col-span-7 glass rounded-2xl p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-[20px] font-extrabold tracking-tight text-ink-900">API integrations</h2>
                        <p class="mt-1 max-w-2xl text-[13px] leading-6 text-ink-500">
                            Manage backend API credentials used by platform features. Keys are saved encrypted and are never shown back in the browser.
                        </p>
                    </div>
                </div>

                <form @submit.prevent="save" class="mt-6 space-y-5">
                    <section class="rounded-2xl border border-brand-indigo/15 bg-brand-indigo/5 p-5">
                        <Field label="AI provider preference" hint="Auto uses any configured key. You can force OpenAI or Claude if both are configured." :error="form.errors.ai_provider">
                            <select v-model="form.ai_provider" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                                <option value="auto">Auto - use whichever key is available</option>
                                <option value="openai">Prefer OpenAI</option>
                                <option value="anthropic">Prefer Claude</option>
                            </select>
                        </Field>
                    </section>

                    <section class="rounded-2xl border border-ink-200/70 bg-white/70 p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-[16px] font-extrabold tracking-tight text-ink-900">OpenAI</h3>
                                <p class="mt-1 text-[12.5px] leading-5 text-ink-500">
                                    Used for blog post draft generation from the Content page.
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span v-if="integrations.openai?.has_database_key" class="rounded-full bg-emerald-50 px-3 py-1 font-mono text-[11px] font-bold text-emerald-700">
                                    DB key saved
                                </span>
                                <span v-if="integrations.openai?.has_env_key" class="rounded-full bg-sky-50 px-3 py-1 font-mono text-[11px] font-bold text-sky-700">
                                    ENV key available
                                </span>
                                <span v-if="!integrations.openai?.has_database_key && !integrations.openai?.has_env_key" class="rounded-full bg-amber-50 px-3 py-1 font-mono text-[11px] font-bold text-amber-700">
                                    Key missing
                                </span>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <Field label="API key" hint="Paste a new key only when you want to replace the saved key." :error="form.errors.openai_api_key">
                                <TextField v-model="form.openai_api_key" type="password" autocomplete="off" placeholder="sk-..." />
                            </Field>
                            <Field label="Model" :error="form.errors.openai_model">
                                <select v-model="form.openai_model" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                                    <option v-if="form.openai_model && !openAiModels.some((model) => model.value === form.openai_model)" :value="form.openai_model">
                                        {{ form.openai_model }} (current)
                                    </option>
                                    <option v-for="model in openAiModels" :key="model.value" :value="model.value">
                                        {{ model.label }}
                                    </option>
                                </select>
                                <p class="mt-2 text-[12px] text-ink-500">
                                    <span v-if="modelFetchState.openai === 'loading'">Fetching latest models...</span>
                                    <span v-else-if="modelFetchState.openai === 'loaded'">Latest API model list loaded.</span>
                                    <span v-else-if="modelFetchState.openai === 'fallback'">Using fallback list. You can still paste any valid model ID below.</span>
                                </p>
                                <TextField v-model="form.openai_model" class="mt-3" placeholder="Or paste a custom OpenAI model ID" />
                            </Field>
                        </div>

                        <label class="mt-4 inline-flex items-center gap-2 rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 cursor-pointer">
                            <input v-model="form.clear_openai_api_key" type="checkbox" class="h-4 w-4" />
                            <span class="text-[13px] font-medium text-ink-700">Clear saved database key</span>
                        </label>
                    </section>

                    <section class="rounded-2xl border border-ink-200/70 bg-white/70 p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-[16px] font-extrabold tracking-tight text-ink-900">Claude API</h3>
                                <p class="mt-1 text-[12.5px] leading-5 text-ink-500">
                                    Alternative provider for blog post draft generation.
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span v-if="integrations.anthropic?.has_database_key" class="rounded-full bg-emerald-50 px-3 py-1 font-mono text-[11px] font-bold text-emerald-700">
                                    DB key saved
                                </span>
                                <span v-if="integrations.anthropic?.has_env_key" class="rounded-full bg-sky-50 px-3 py-1 font-mono text-[11px] font-bold text-sky-700">
                                    ENV key available
                                </span>
                                <span v-if="!integrations.anthropic?.has_database_key && !integrations.anthropic?.has_env_key" class="rounded-full bg-amber-50 px-3 py-1 font-mono text-[11px] font-bold text-amber-700">
                                    Key missing
                                </span>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <Field label="API key" hint="Paste a new key only when you want to replace the saved key." :error="form.errors.anthropic_api_key">
                                <TextField v-model="form.anthropic_api_key" type="password" autocomplete="off" placeholder="sk-ant-..." />
                            </Field>
                            <Field label="Model" :error="form.errors.anthropic_model">
                                <select v-model="form.anthropic_model" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                                    <option v-if="form.anthropic_model && !anthropicModels.some((model) => model.value === form.anthropic_model)" :value="form.anthropic_model">
                                        {{ form.anthropic_model }} (current)
                                    </option>
                                    <option v-for="model in anthropicModels" :key="model.value" :value="model.value">
                                        {{ model.label }}
                                    </option>
                                </select>
                                <p class="mt-2 text-[12px] text-ink-500">
                                    <span v-if="modelFetchState.anthropic === 'loading'">Fetching latest models...</span>
                                    <span v-else-if="modelFetchState.anthropic === 'loaded'">Latest API model list loaded.</span>
                                    <span v-else-if="modelFetchState.anthropic === 'fallback'">Using fallback list. You can still paste any valid model ID below.</span>
                                </p>
                                <TextField v-model="form.anthropic_model" class="mt-3" placeholder="Or paste a custom Claude model ID" />
                            </Field>
                        </div>

                        <label class="mt-4 inline-flex items-center gap-2 rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 cursor-pointer">
                            <input v-model="form.clear_anthropic_api_key" type="checkbox" class="h-4 w-4" />
                            <span class="text-[13px] font-medium text-ink-700">Clear saved database key</span>
                        </label>
                    </section>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary px-5 py-2.5 text-[13px]" :disabled="form.processing">
                            {{ form.processing ? 'Saving...' : 'Save integrations' }}
                        </button>
                    </div>
                </form>
            </section>

            <aside class="xl:col-span-5 space-y-5">
                <section class="glass rounded-2xl p-6">
                    <h2 class="text-[18px] font-extrabold tracking-tight text-ink-900">How it works</h2>
                    <div class="mt-4 space-y-3 text-[13px] leading-6 text-ink-600">
                        <p>The app first checks encrypted database keys. If no database key is saved, it falls back to <span class="font-mono">OPENAI_API_KEY</span> or <span class="font-mono">ANTHROPIC_API_KEY</span> from the server environment.</p>
                        <p>Auto mode means one working provider is enough. If both are configured, OpenAI is used first unless you choose Claude as the preferred provider.</p>
                    </div>
                </section>
            </aside>
        </div>
    </SuperAdminLayout>
</template>
