<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import LanguageToggle from '@/Components/LanguageToggle.vue';
import PublicFooter from '@/Components/PublicFooter.vue';

const props = defineProps({
    phone: { type: String, required: true },
    email: { type: String, required: true },
});

const page = usePage();
const appLogo = computed(() => page.props.appLogo);
const user = computed(() => page.props.auth?.user);
const flash = computed(() => page.props.flash || {});

const form = useForm({
    name: '',
    email: '',
    phone: '',
    organization: '',
    message: '',
    website: '',
});

const submit = () => {
    form.post('/contact', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Contact | AuctionBall">
        <meta name="description" content="Contact AuctionBall for setup help, billing support, and live tournament auction questions." head-key="description" />
        <meta name="robots" content="index,follow" head-key="robots" />
    </Head>

    <div class="page-bg min-h-screen text-ink-900">
        <header class="sticky top-0 z-30 bg-white/75 backdrop-blur-md border-b border-ink-200/50">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 py-3 flex items-center gap-3">
                <Link href="/" class="flex items-center gap-2.5">
                    <img v-if="appLogo" :src="appLogo" alt="AuctionBall" class="h-9 w-9 rounded-lg object-contain bg-white border border-ink-200/40" />
                    <span v-else class="grid place-items-center h-9 w-9 rounded-lg bg-gradient-brand shadow-cta">
                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12h8M8 8h5"/></svg>
                    </span>
                    <span class="font-semibold text-[16px] tracking-tight">AuctionBall</span>
                </Link>
                <nav class="hidden md:flex items-center gap-5 ml-8 text-[13px] text-ink-600">
                    <Link href="/features" class="hover:text-ink-900">Features</Link>
                    <Link href="/pricing" class="hover:text-ink-900">Pricing</Link>
                    <Link href="/blog" class="hover:text-ink-900">Blog</Link>
                    <Link href="/help" class="hover:text-ink-900">Help</Link>
                </nav>
                <div class="ml-auto flex items-center gap-2">
                    <LanguageToggle />
                    <template v-if="user">
                        <Link href="/logout" method="post" as="button" type="button" class="hidden sm:inline-flex btn-ghost text-[13px] py-2 px-3">Log out</Link>
                        <Link href="/dashboard" class="btn-primary text-[13px] py-2 px-3">Dashboard</Link>
                    </template>
                    <template v-else>
                        <Link href="/login" class="hidden sm:inline-flex btn-ghost text-[13px] py-2 px-3">Log in</Link>
                        <Link href="/register" class="btn-primary text-[13px] py-2 px-3">Start free</Link>
                    </template>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 sm:px-6 py-14">
            <section class="grid lg:grid-cols-12 gap-8 lg:gap-12 items-start">
                <div class="lg:col-span-5">
                    <div class="font-mono text-[11px] tracking-widest text-brand-indigo uppercase mb-4">Contact</div>
                    <h1 class="text-[34px] sm:text-[48px] leading-tight font-extrabold tracking-tight">Let us help with your auction</h1>
                    <p class="mt-5 text-[17px] leading-8 text-ink-600">
                        Send your setup, billing, or event-day question. Include your organization name or tournament date when useful.
                    </p>

                    <div class="mt-8 grid gap-3">
                        <a :href="`tel:${phone}`" class="rounded-lg border border-ink-200/70 bg-white p-5 hover:border-brand-indigo/40 transition">
                            <div class="font-mono text-[10.5px] tracking-widest text-ink-500 uppercase">Phone</div>
                            <div class="mt-1 text-[20px] font-bold tracking-tight">{{ phone }}</div>
                        </a>
                        <a :href="`mailto:${email}`" class="rounded-lg border border-ink-200/70 bg-white p-5 hover:border-brand-indigo/40 transition">
                            <div class="font-mono text-[10.5px] tracking-widest text-ink-500 uppercase">Email</div>
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
                                <span class="font-mono text-[10.5px] tracking-widest text-ink-500 uppercase">Name</span>
                                <input v-model="form.name" type="text" autocomplete="name" class="mt-2 w-full rounded-lg border border-ink-200 bg-white px-4 py-3 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/25" />
                                <span v-if="form.errors.name" class="mt-1 block text-[12px] text-rose-500">{{ form.errors.name }}</span>
                            </label>
                            <label class="block">
                                <span class="font-mono text-[10.5px] tracking-widest text-ink-500 uppercase">Email</span>
                                <input v-model="form.email" type="email" autocomplete="email" class="mt-2 w-full rounded-lg border border-ink-200 bg-white px-4 py-3 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/25" />
                                <span v-if="form.errors.email" class="mt-1 block text-[12px] text-rose-500">{{ form.errors.email }}</span>
                            </label>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <label class="block">
                                <span class="font-mono text-[10.5px] tracking-widest text-ink-500 uppercase">Phone</span>
                                <input v-model="form.phone" type="tel" autocomplete="tel" class="mt-2 w-full rounded-lg border border-ink-200 bg-white px-4 py-3 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/25" />
                                <span v-if="form.errors.phone" class="mt-1 block text-[12px] text-rose-500">{{ form.errors.phone }}</span>
                            </label>
                            <label class="block">
                                <span class="font-mono text-[10.5px] tracking-widest text-ink-500 uppercase">Organization</span>
                                <input v-model="form.organization" type="text" autocomplete="organization" class="mt-2 w-full rounded-lg border border-ink-200 bg-white px-4 py-3 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/25" />
                                <span v-if="form.errors.organization" class="mt-1 block text-[12px] text-rose-500">{{ form.errors.organization }}</span>
                            </label>
                        </div>

                        <label class="block">
                            <span class="font-mono text-[10.5px] tracking-widest text-ink-500 uppercase">Message</span>
                            <textarea v-model="form.message" rows="7" class="mt-2 w-full rounded-lg border border-ink-200 bg-white px-4 py-3 text-[14px] leading-7 focus:outline-none focus:ring-2 focus:ring-brand-indigo/25" placeholder="Tell us what you need help with..."></textarea>
                            <span v-if="form.errors.message" class="mt-1 block text-[12px] text-rose-500">{{ form.errors.message }}</span>
                        </label>

                        <button type="submit" class="btn-primary w-full sm:w-auto px-6 py-3" :disabled="form.processing" :class="{ 'opacity-60 pointer-events-none': form.processing }">
                            {{ form.processing ? 'Sending...' : 'Send message' }}
                        </button>
                    </form>
                </div>
            </section>
        </main>

        <PublicFooter />
    </div>
</template>
