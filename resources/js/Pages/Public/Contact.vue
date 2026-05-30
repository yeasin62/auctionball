<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicFooter from '@/Components/PublicFooter.vue';
import PublicHeader from '@/Components/PublicHeader.vue';

const props = defineProps({
    phone: { type: String, required: true },
    email: { type: String, required: true },
    captcha: { type: Object, required: true },
});

const page = usePage();
const flash = computed(() => page.props.flash || {});
const appDomain = computed(() => page.props.appDomain || 'auctionball.com');
const canonicalUrl = computed(() => `https://${appDomain.value}/contact`);
const { t } = useI18n();

const form = useForm({
    name: '',
    email: '',
    phone: '',
    organization: '',
    message: '',
    website: '',
    captcha_answer: '',
});

const submit = () => {
    form.post('/contact', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head :title="t('contact_page.seo_title')">
        <meta name="description" :content="t('contact_page.seo_description')" head-key="description" />
        <meta name="robots" content="index,follow" head-key="robots" />
        <link rel="canonical" :href="canonicalUrl" head-key="canonical" />
        <link rel="alternate" hreflang="en" :href="canonicalUrl + '?lang=en'" />
        <link rel="alternate" hreflang="bn" :href="canonicalUrl + '?lang=bn'" />
        <link rel="alternate" hreflang="x-default" :href="canonicalUrl" />
    </Head>

    <div class="page-bg min-h-screen text-ink-900">
        <PublicHeader />

        <main class="mx-auto max-w-6xl px-4 sm:px-6 py-14">
            <section class="grid lg:grid-cols-12 gap-8 lg:gap-12 items-start">
                <div class="lg:col-span-5">
                    <div class="font-mono text-[11px] tracking-widest text-brand-indigo uppercase mb-4">{{ t('contact_page.eyebrow') }}</div>
                    <h1 class="text-[34px] sm:text-[48px] leading-tight font-extrabold tracking-tight">{{ t('contact_page.heading') }}</h1>
                    <p class="mt-5 text-[17px] leading-8 text-ink-600">
                        {{ t('contact_page.subtitle') }}
                    </p>

                    <div class="mt-8 grid gap-3">
                        <a :href="`tel:${phone}`" class="rounded-lg border border-ink-200/70 bg-white p-5 hover:border-brand-indigo/40 transition">
                            <div class="font-mono text-[10.5px] tracking-widest text-ink-500 uppercase">{{ t('contact_page.phone') }}</div>
                            <div class="mt-1 text-[20px] font-bold tracking-tight">{{ phone }}</div>
                        </a>
                        <a :href="`mailto:${email}`" class="rounded-lg border border-ink-200/70 bg-white p-5 hover:border-brand-indigo/40 transition">
                            <div class="font-mono text-[10.5px] tracking-widest text-ink-500 uppercase">{{ t('contact_page.email') }}</div>
                            <div class="mt-1 text-[18px] font-bold tracking-tight break-all">{{ email }}</div>
                        </a>
                    </div>
                </div>

                <div class="lg:col-span-7 rounded-lg border border-ink-200/70 bg-white p-5 sm:p-7 shadow-soft">
                    <div v-if="flash.success" class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-[13.5px] text-emerald-700">
                        {{ flash.success }}
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <input v-model="form.website" type="text" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true" />

                        <div class="grid sm:grid-cols-2 gap-4">
                            <label class="block">
                                <span class="font-mono text-[10.5px] tracking-widest text-ink-500 uppercase">{{ t('contact_page.name') }}</span>
                                <input v-model="form.name" type="text" autocomplete="name" class="mt-2 w-full rounded-lg border border-ink-200 bg-white px-4 py-3 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/25" />
                                <span v-if="form.errors.name" class="mt-1 block text-[12px] text-rose-500">{{ form.errors.name }}</span>
                            </label>
                            <label class="block">
                                <span class="font-mono text-[10.5px] tracking-widest text-ink-500 uppercase">{{ t('contact_page.email') }}</span>
                                <input v-model="form.email" type="email" autocomplete="email" class="mt-2 w-full rounded-lg border border-ink-200 bg-white px-4 py-3 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/25" />
                                <span v-if="form.errors.email" class="mt-1 block text-[12px] text-rose-500">{{ form.errors.email }}</span>
                            </label>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <label class="block">
                                <span class="font-mono text-[10.5px] tracking-widest text-ink-500 uppercase">{{ t('contact_page.phone') }}</span>
                                <input v-model="form.phone" type="tel" autocomplete="tel" class="mt-2 w-full rounded-lg border border-ink-200 bg-white px-4 py-3 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/25" />
                                <span v-if="form.errors.phone" class="mt-1 block text-[12px] text-rose-500">{{ form.errors.phone }}</span>
                            </label>
                            <label class="block">
                                <span class="font-mono text-[10.5px] tracking-widest text-ink-500 uppercase">{{ t('contact_page.organization') }}</span>
                                <input v-model="form.organization" type="text" autocomplete="organization" class="mt-2 w-full rounded-lg border border-ink-200 bg-white px-4 py-3 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/25" />
                                <span v-if="form.errors.organization" class="mt-1 block text-[12px] text-rose-500">{{ form.errors.organization }}</span>
                            </label>
                        </div>

                        <label class="block">
                            <span class="font-mono text-[10.5px] tracking-widest text-ink-500 uppercase">{{ t('contact_page.message') }}</span>
                            <textarea v-model="form.message" rows="7" class="mt-2 w-full rounded-lg border border-ink-200 bg-white px-4 py-3 text-[14px] leading-7 focus:outline-none focus:ring-2 focus:ring-brand-indigo/25" :placeholder="t('contact_page.message_placeholder')"></textarea>
                            <span v-if="form.errors.message" class="mt-1 block text-[12px] text-rose-500">{{ form.errors.message }}</span>
                        </label>

                        <label class="block rounded-lg border border-ink-200 bg-ink-50/70 px-4 py-3">
                            <span class="font-mono text-[10.5px] tracking-widest text-ink-500 uppercase">{{ t('contact_page.captcha_label') }}</span>
                            <span class="mt-1 block text-[13px] text-ink-600">{{ t('contact_page.captcha_help') }}</span>
                            <div class="mt-3 grid sm:grid-cols-[160px_1fr] gap-3 items-center">
                                <div class="rounded-lg border border-ink-200 bg-white px-4 py-3 font-mono text-[16px] font-bold text-ink-900 text-center">
                                    {{ captcha.question }}
                                </div>
                                <input v-model="form.captcha_answer" type="number" inputmode="numeric" autocomplete="off" class="w-full rounded-lg border border-ink-200 bg-white px-4 py-3 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/25" :placeholder="t('contact_page.captcha_placeholder')" />
                            </div>
                            <span v-if="form.errors.captcha_answer" class="mt-1 block text-[12px] text-rose-500">{{ form.errors.captcha_answer }}</span>
                        </label>

                        <button type="submit" class="btn-primary w-full sm:w-auto px-6 py-3" :disabled="form.processing" :class="{ 'opacity-60 pointer-events-none': form.processing }">
                            {{ form.processing ? t('contact_page.sending') : t('contact_page.send_message') }}
                        </button>
                    </form>
                </div>
            </section>
        </main>

        <PublicFooter />
    </div>
</template>
