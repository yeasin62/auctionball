<script setup>
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useConfirm, useAlert } from '@/composables/useConfirm';

const confirm = useConfirm();
const alertDialog = useAlert();

const props = defineProps({
    users:   Object,
    filters: Object,
    orgs:    { type: Array, default: () => [] },
    plans:   { type: Array, default: () => [] },
    roles:   { type: Array, default: () => [] },
});
const page = usePage();
const me = page.props.auth?.user?.id;

const f = ref({ ...props.filters });
const apply = () => router.get(route('admin.users.index'), f.value, { preserveState: true });
const clear = () => { f.value = { q: '', is_super_admin: '' }; apply(); };

// --- Create user modal ---
const showCreate = ref(false);
const createForm = useForm({
    name: '',
    email: '',
    password: '',
    is_super_admin: false,
    attach_mode: 'new_org',
    // new_org
    org_name: '',
    org_slug: '',
    plan: 'free',
    gift_months: 1,
    // existing_org
    organization_id: null,
    role: 'org_admin',
});

const slugify = (s) => (s || '').toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60);
const onOrgNameInput = () => {
    if (! createForm.org_slug) createForm.org_slug = slugify(createForm.org_name);
};

const submitCreate = () => {
    createForm.post(route('admin.users.store'), {
        preserveScroll: true,
        onSuccess: () => { showCreate.value = false; createForm.reset(); createForm.attach_mode = 'new_org'; createForm.plan = 'free'; createForm.role = 'org_admin'; createForm.gift_months = 1; },
    });
};

const isPaidPlan = computed(() => createForm.plan && createForm.plan !== 'free');

const toggleSuperAdmin = async (u) => {
    if (u.id === me) return alertDialog({ title: "You can't change your own super-admin flag.", variant: 'warning' });
    if (! await confirm({
        title: u.is_super_admin
            ? `Remove super-admin from ${u.name}?`
            : `Promote ${u.name} to super-admin?`,
        description: u.is_super_admin
            ? 'They will lose access to /admin and all platform-management tools.'
            : 'They will gain full platform access — every org, every payment, every audit log. Only enable for trusted operators.',
        variant: u.is_super_admin ? 'warning' : 'danger',
        confirmText: u.is_super_admin ? 'Remove super-admin' : 'Promote',
    })) return;
    router.post(route('admin.users.toggle-super-admin', u.id), {}, { preserveScroll: true });
};

const resetPassword = async (u) => {
    if (u.id === me) return;
    if (! await confirm({
        title: `Reset password for ${u.name}?`,
        description: 'A new temporary password will be generated and shown to you once. Share it securely with the user.',
        variant: 'warning',
        confirmText: 'Reset password',
    })) return;
    router.post(route('admin.users.reset-password', u.id), {}, { preserveScroll: true });
};

const deleteUser = async (u) => {
    if (u.id === me) return alertDialog({ title: "You can't delete yourself.", variant: 'warning' });
    if (! await confirm({
        title: `Permanently delete ${u.name}?`,
        description: `${u.email}\n\nThis removes their account and all org memberships. If they are the sole admin of an organization, that org will lose all admin access. Type DELETE below to confirm.`,
        variant: 'danger',
        confirmText: 'Delete account',
        typeToConfirm: 'DELETE',
    })) return;
    router.delete(route('admin.users.delete', u.id), { preserveScroll: true });
};
</script>

<template>
    <SuperAdminLayout title="Users">

        <div class="flex justify-end mb-4">
            <button @click="showCreate = true" class="btn-primary py-2 px-4 text-[13px]">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
                Create user
            </button>
        </div>

        <!-- Filters -->
        <div class="glass rounded-2xl p-4 mb-4 flex flex-wrap items-center gap-2">
            <input v-model="f.q" @keyup.enter="apply"
                   placeholder="Search name or email…"
                   class="flex-1 min-w-[220px] rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
            <select v-model="f.is_super_admin" @change="apply" class="rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 text-[13px]">
                <option value="">All users</option>
                <option value="1">Super admins only</option>
                <option value="0">Regular only</option>
            </select>
            <button @click="apply" class="btn-primary py-2 px-4 text-[13px]">Apply</button>
            <button @click="clear" class="btn-ghost py-2 px-3 text-[12px]">Reset</button>
            <span class="text-[12px] font-mono text-ink-500 ml-auto">{{ users.total }} total</span>
        </div>

        <!-- Users table -->
        <div class="glass rounded-2xl overflow-hidden">
            <table class="w-full text-[13.5px]">
                <thead class="bg-white/40">
                    <tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                        <th class="px-4 py-2.5">USER</th>
                        <th class="px-4 py-2.5">EMAIL</th>
                        <th class="px-4 py-2.5">ORGS</th>
                        <th class="px-4 py-2.5">SAMPLE ORG</th>
                        <th class="px-4 py-2.5">JOINED</th>
                        <th class="px-4 py-2.5">SUPER</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="u in users.data" :key="u.id" class="hover:bg-white/40">
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2.5">
                                <div class="h-8 w-8 rounded-full bg-gradient-to-br from-cyan-200 to-indigo-300 grid place-items-center font-mono text-[10px] font-bold text-indigo-700 shrink-0">
                                    {{ u.name?.[0]?.toUpperCase() }}
                                </div>
                                <div class="leading-tight">
                                    <div class="font-medium">{{ u.name }}</div>
                                    <div v-if="u.id === me" class="font-mono text-[9.5px] text-violet-600">YOU</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-[12px] text-ink-700">{{ u.email }}</td>
                        <td class="px-4 py-2.5 font-mono">{{ u.organizations_count }}</td>
                        <td class="px-4 py-2.5">
                            <div v-if="u.sample_org" class="leading-tight">
                                <div class="text-[12.5px]">{{ u.sample_org.name }}</div>
                                <div class="font-mono text-[10.5px] text-ink-500">{{ u.sample_org.role }}</div>
                            </div>
                            <span v-else class="text-ink-400">—</span>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-[12px] text-ink-500">{{ u.created_at }}</td>
                        <td class="px-4 py-2.5">
                            <button @click="toggleSuperAdmin(u)"
                                    class="inline-flex items-center gap-1.5 cursor-pointer"
                                    :class="u.id === me ? 'opacity-40 pointer-events-none' : ''">
                                <span class="h-4 w-7 rounded-full transition-colors relative"
                                      :class="u.is_super_admin ? 'bg-violet-500' : 'bg-ink-200'">
                                    <span class="absolute top-0.5 left-0.5 h-3 w-3 bg-white rounded-full transition-transform"
                                          :class="u.is_super_admin ? 'translate-x-3' : ''"></span>
                                </span>
                                <span v-if="u.is_super_admin" class="font-mono text-[10px] text-violet-700 font-bold tracking-wider">SUPER</span>
                            </button>
                        </td>
                        <td class="px-4 py-2.5 text-right whitespace-nowrap">
                            <button @click="resetPassword(u)" class="text-[11.5px] text-brand-indigo hover:underline mr-3"
                                    :class="u.id === me ? 'opacity-40 pointer-events-none' : ''">
                                Reset password
                            </button>
                            <button @click="deleteUser(u)" class="text-[11.5px] text-rose-500 hover:text-rose-700"
                                    :class="u.id === me ? 'opacity-40 pointer-events-none' : ''">
                                Delete
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="users.last_page > 1" class="mt-4 flex justify-center gap-1">
            <Link v-for="link in users.links" :key="link.label" :href="link.url || '#'" v-html="link.label"
                  class="px-3 py-1.5 rounded-lg text-[13px] font-mono"
                  :class="link.active ? 'bg-gradient-brand text-white' : link.url ? 'text-ink-700 hover:bg-white/60' : 'text-ink-300'"
                  :preserve-scroll="true" />
        </div>

        <!-- Create user modal -->
        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-ink-900/50 backdrop-blur-sm p-4 pt-10" @click.self="showCreate = false">
            <div class="glass-strong rounded-2xl max-w-xl w-full p-6 shadow-glass-lg">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <div class="font-mono text-[10.5px] tracking-widest text-ink-500">CREATE USER</div>
                        <div class="mt-1 text-[18px] font-bold tracking-tight">Hand-create an account</div>
                    </div>
                    <button @click="showCreate = false" class="text-ink-400 hover:text-ink-700 text-[20px]">×</button>
                </div>

                <form @submit.prevent="submitCreate" class="space-y-4">
                    <!-- Identity -->
                    <div class="grid md:grid-cols-2 gap-3">
                        <div>
                            <label class="font-mono text-[10.5px] tracking-widest text-ink-500">FULL NAME</label>
                            <input v-model="createForm.name" type="text" autofocus
                                   class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                            <p v-if="createForm.errors.name" class="mt-1 text-[12px] text-rose-500">{{ createForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="font-mono text-[10.5px] tracking-widest text-ink-500">EMAIL</label>
                            <input v-model="createForm.email" type="email"
                                   class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                            <p v-if="createForm.errors.email" class="mt-1 text-[12px] text-rose-500">{{ createForm.errors.email }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="font-mono text-[10.5px] tracking-widest text-ink-500">PASSWORD <span class="text-ink-400 normal-case tracking-normal">(leave blank to auto-generate)</span></label>
                        <input v-model="createForm.password" type="text" placeholder="Auto-generated if empty"
                               class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] font-mono focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                        <p v-if="createForm.errors.password" class="mt-1 text-[12px] text-rose-500">{{ createForm.errors.password }}</p>
                    </div>

                    <label class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl bg-violet-50 border border-violet-200 cursor-pointer">
                        <input type="checkbox" v-model="createForm.is_super_admin" class="h-4 w-4" />
                        <div class="flex-1">
                            <div class="text-[13px] font-semibold">Mark as super-admin</div>
                            <div class="text-[11.5px] text-violet-700">Full platform access — only enable for trusted admins.</div>
                        </div>
                    </label>

                    <!-- Attach mode tabs -->
                    <div>
                        <label class="font-mono text-[10.5px] tracking-widest text-ink-500 mb-2 block">ORGANIZATION</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" v-for="m in [
                                { v: 'new_org', label: 'New org + gift plan' },
                                { v: 'existing_org', label: 'Add to existing org' },
                                { v: 'none', label: 'No org (test user)' },
                            ]" :key="m.v"
                                @click="createForm.attach_mode = m.v"
                                class="rounded-xl border px-3 py-2.5 text-[12px] transition"
                                :class="createForm.attach_mode === m.v
                                    ? 'bg-gradient-brand text-white border-transparent shadow-cta'
                                    : 'bg-white/70 border-ink-200/70 text-ink-700 hover:bg-white'">
                                {{ m.label }}
                            </button>
                        </div>
                    </div>

                    <!-- New org branch -->
                    <div v-if="createForm.attach_mode === 'new_org'" class="rounded-xl bg-white/70 border border-ink-200/60 p-4 space-y-3">
                        <div class="grid md:grid-cols-2 gap-3">
                            <div>
                                <label class="font-mono text-[10.5px] tracking-widest text-ink-500">ORG NAME</label>
                                <input v-model="createForm.org_name" @input="onOrgNameInput" type="text"
                                       class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                                <p v-if="createForm.errors.org_name" class="mt-1 text-[12px] text-rose-500">{{ createForm.errors.org_name }}</p>
                            </div>
                            <div>
                                <label class="font-mono text-[10.5px] tracking-widest text-ink-500">SLUG (subdomain)</label>
                                <input v-model="createForm.org_slug" type="text" placeholder="myclub"
                                       class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] font-mono focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                                <p v-if="createForm.errors.org_slug" class="mt-1 text-[12px] text-rose-500">{{ createForm.errors.org_slug }}</p>
                            </div>
                        </div>

                        <div class="grid md:grid-cols-2 gap-3">
                            <div>
                                <label class="font-mono text-[10.5px] tracking-widest text-ink-500">GIFT PLAN</label>
                                <select v-model="createForm.plan"
                                        class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] capitalize focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                                    <option v-for="p in plans" :key="p" :value="p">{{ p }}</option>
                                </select>
                            </div>
                            <div v-if="isPaidPlan">
                                <label class="font-mono text-[10.5px] tracking-widest text-ink-500">GIFT DURATION (MONTHS)</label>
                                <input v-model.number="createForm.gift_months" type="number" min="1" max="60"
                                       class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                            </div>
                        </div>
                        <p v-if="isPaidPlan" class="text-[11.5px] text-ink-500">
                            A manual subscription is created — no payment row, auto-renew is off, expires after {{ createForm.gift_months }} month(s).
                        </p>
                    </div>

                    <!-- Existing org branch -->
                    <div v-else-if="createForm.attach_mode === 'existing_org'" class="rounded-xl bg-white/70 border border-ink-200/60 p-4 space-y-3">
                        <div>
                            <label class="font-mono text-[10.5px] tracking-widest text-ink-500">ORGANIZATION</label>
                            <select v-model.number="createForm.organization_id"
                                    class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                                <option :value="null" disabled>— pick an org —</option>
                                <option v-for="o in orgs" :key="o.id" :value="o.id">{{ o.name }} ({{ o.slug }} · {{ o.plan }})</option>
                            </select>
                            <p v-if="createForm.errors.organization_id" class="mt-1 text-[12px] text-rose-500">{{ createForm.errors.organization_id }}</p>
                        </div>
                        <div>
                            <label class="font-mono text-[10.5px] tracking-widest text-ink-500">ROLE</label>
                            <select v-model="createForm.role"
                                    class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                                <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                            </select>
                        </div>
                    </div>

                    <div v-else class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-2.5 text-[12.5px] text-amber-800">
                        No organization will be attached. Useful for super-admin-only logins or quick test accounts.
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button type="button" @click="showCreate = false" class="btn-ghost py-2 px-4 text-[13px] flex-1">Cancel</button>
                        <button type="submit" class="btn-primary py-2 px-4 text-[13px] flex-1" :disabled="createForm.processing">
                            {{ createForm.processing ? 'Creating…' : 'Create user' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </SuperAdminLayout>
</template>
