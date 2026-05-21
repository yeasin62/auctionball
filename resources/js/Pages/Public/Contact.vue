<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicFooter from '@/Components/PublicFooter.vue';
import PublicHeader from '@/Components/PublicHeader.vue';

const props = defineProps({
    phone: { type: String, required: true },
    email: { type: String, required: true },
});

const page = usePage();
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
        <PublicHeader />

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
