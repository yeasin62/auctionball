<script setup>
import AuthShell from '@/Layouts/AuthShell.vue';
import Field from '@/Components/Field.vue';
import TextField from '@/Components/TextField.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const appDomain = computed(() => usePage().props.appDomain || 'auctionball.com');

const props = defineProps({
    plans: { type: Array, default: () => ['free', 'starter', 'pro'] },
});

const planFromUrl = (() => {
    const v = new URLSearchParams(window.location.search).get('plan');
    return v && ['free', 'starter', 'pro'].includes(v) ? v : 'free';
})();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    org_name: '',
    org_slug: '',
    plan: planFromUrl,
});

const slugify = (s) =>
    s.toLowerCase().trim()
        .replace(/[^a-z0-9-]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 60);

let slugTouched = false;
watch(() => form.org_name, (v) => {
    if (!slugTouched) form.org_slug = slugify(v);
});
const onSlugInput = (v) => { slugTouched = true; form.org_slug = slugify(v); };

const planMeta = {
    free:    { label: 'Free',     price: '৳0',         meta: '1 season · 20 players · 4 teams' },
    starter: { label: 'Starter',  price: '৳1,999/mo',  meta: '3 seasons · 100 players · 10 teams' },
    pro:     { label: 'Pro',      price: '৳4,999/mo',  meta: 'Unlimited everything' },
};

const visiblePlans = computed(() => props.plans.filter(p => planMeta[p]));

const submit = () => form.post(route('register'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
});
</script>

<template>
    <Head title="Create your AuctionBall organization" />
    <AuthShell title="Create your organization" subtitle="Pick a subdomain, add an admin, choose a plan. 30 seconds and you are running auctions.">
        <form @submit.prevent="submit" class="space-y-5">

            <!-- Org block -->
            <div class="space-y-4">
                <div class="font-mono text-[11px] tracking-widest text-ink-500">/ ORGANIZATION</div>
                <Field label="Organization name" :error="form.errors.org_name" required>
                    <TextField v-model="form.org_name" placeholder="BPL Cricket Cup" autofocus autocomplete="organization" />
                </Field>
                <Field label="Subdomain" :hint="'lowercase letters, numbers, dashes'" :error="form.errors.org_slug" required>
                    <TextField :modelValue="form.org_slug" @update:modelValue="onSlugInput"
                               leading="" :trailing="`.${appDomain}`" placeholder="bpl-2026" autocomplete="off" />
                </Field>
            </div>

            <!-- Admin block -->
            <div class="space-y-4 pt-3 border-t border-ink-200/60">
                <div class="font-mono text-[11px] tracking-widest text-ink-500">/ ADMIN ACCOUNT</div>
                <Field label="Your name" :error="form.errors.name" required>
                    <TextField v-model="form.name" placeholder="Rashed Hossain" autocomplete="name" />
                </Field>
                <Field label="Email" :error="form.errors.email" required>
                    <TextField v-model="form.email" type="email" placeholder="you@example.com" autocomplete="username" />
                </Field>
                <div class="grid grid-cols-2 gap-3">
                    <Field label="Password" :error="form.errors.password" required>
                        <TextField v-model="form.password" type="password" autocomplete="new-password" />
                    </Field>
                    <Field label="Confirm password" :error="form.errors.password_confirmation" required>
                        <TextField v-model="form.password_confirmation" type="password" autocomplete="new-password" />
                    </Field>
                </div>
            </div>

            <!-- Plan block -->
            <div class="space-y-3 pt-3 border-t border-ink-200/60">
                <div class="font-mono text-[11px] tracking-widest text-ink-500">/ PLAN</div>
                <div class="grid grid-cols-3 gap-2">
                    <label v-for="p in visiblePlans" :key="p"
                           class="cursor-pointer rounded-xl border p-3 transition"
                           :class="form.plan === p
                                ? 'border-brand-indigo bg-white shadow-cta'
                                : 'border-ink-200/70 bg-white/60 hover:bg-white'">
                        <input type="radio" :value="p" v-model="form.plan" class="sr-only" />
                        <div class="flex items-center justify-between">
                            <span class="text-[13.5px] font-semibold">{{ planMeta[p].label }}</span>
                            <span v-if="form.plan === p" class="h-4 w-4 rounded-full bg-gradient-brand"></span>
                            <span v-else class="h-4 w-4 rounded-full border border-ink-300"></span>
                        </div>
                        <div class="mt-1 text-[12.5px] font-mono text-ink-700">{{ planMeta[p].price }}</div>
                        <div class="text-[11px] text-ink-500 leading-snug mt-1">{{ planMeta[p].meta }}</div>
                    </label>
                </div>
                <p v-if="form.errors.plan" class="text-[12.5px] text-rose-500">{{ form.errors.plan }}</p>
            </div>

            <button type="submit" class="btn-primary w-full py-3"
                    :class="{ 'opacity-60 pointer-events-none': form.processing }"
                    :disabled="form.processing">
                {{ form.processing ? 'Creating organization…' : 'Create organization' }}
            </button>

            <p class="text-center text-[12.5px] text-ink-500 pt-1">
                By signing up you agree to our <a href="#terms" class="text-ink-700 underline">Terms</a> and
                <a href="#privacy" class="text-ink-700 underline">Privacy policy</a>.
            </p>
        </form>

        <template #footer>
            <p class="text-[13.5px] text-ink-500">
                Already have an account?
                <Link :href="route('login')" class="text-ink-900 font-medium hover:underline">Log in</Link>
            </p>
        </template>
    </AuthShell>
</template>
