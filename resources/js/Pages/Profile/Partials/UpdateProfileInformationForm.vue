<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const avatarInput = ref(null);

const form = useForm({
    name: user.value.name,
    email: user.value.email,
});

const avatarForm = useForm({
    avatar: null,
});

const initials = computed(() => String(user.value.name || '')
    .split(' ')
    .map((part) => part[0])
    .filter(Boolean)
    .slice(0, 2)
    .join('')
    .toUpperCase());

const chooseAvatar = () => {
    avatarInput.value?.click();
};

const uploadAvatar = (event) => {
    const file = event.target.files?.[0];
    if (! file) return;

    avatarForm.avatar = file;
    avatarForm.post(route('profile.avatar.update'), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            avatarForm.reset('avatar');
            event.target.value = '';
        },
    });
};

const removeAvatar = () => {
    avatarForm.delete(route('profile.avatar.delete'), { preserveScroll: true });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-[18px] font-extrabold tracking-tight text-ink-900">
                {{ t('profile.info_title') }}
            </h2>

            <p class="mt-1 text-[13px] leading-6 text-ink-500">
                {{ t('profile.info_subtitle') }}
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="mt-6 space-y-6"
        >
            <div>
                <InputLabel value="Profile image" />
                <div class="mt-2 flex flex-wrap items-center gap-4">
                    <img v-if="user.avatar_url" :src="user.avatar_url" :alt="user.name" class="h-20 w-20 rounded-2xl border border-ink-200/70 object-cover shadow-card" />
                    <div v-else class="grid h-20 w-20 place-items-center rounded-2xl border border-ink-200/70 bg-indigo-50 text-lg font-bold text-indigo-700 shadow-card">
                        {{ initials || 'U' }}
                    </div>
                    <div class="space-y-2">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="chooseAvatar" class="btn-primary px-4 py-2 text-[12px]" :disabled="avatarForm.processing">
                                {{ avatarForm.processing ? 'Uploading...' : 'Upload image' }}
                            </button>
                            <button v-if="user.avatar_url" type="button" @click="removeAvatar" class="btn-ghost px-4 py-2 text-[12px] text-rose-600" :disabled="avatarForm.processing">
                                Remove
                            </button>
                        </div>
                        <p class="text-[12px] text-ink-500">JPG, PNG, WebP, or GIF. Max 4 MB.</p>
                        <input ref="avatarInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" @change="uploadAvatar" />
                        <InputError class="mt-2" :message="avatarForm.errors.avatar" />
                    </div>
                </div>
            </div>

            <div>
                <InputLabel for="name" :value="t('profile.name')" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" :value="t('common.email')" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    {{ t('profile.email_unverified') }}
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        {{ t('profile.resend_verification') }}
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    {{ t('profile.verification_link_sent') }}
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">{{ t('common.save') }}</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-ink-500"
                    >
                        {{ t('profile.saved') }}
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
