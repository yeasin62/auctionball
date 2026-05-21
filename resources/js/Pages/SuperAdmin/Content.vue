<script setup>
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import Field from '@/Components/Field.vue';
import TextField from '@/Components/TextField.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

defineProps({
    categories: { type: Array, default: () => [] },
});

const categoryForm = useForm({
    name: '',
    slug: '',
    description: '',
});

const saveCategory = () => {
    categoryForm.post(route('admin.content.categories.store'), {
        preserveScroll: true,
        onSuccess: () => {
            categoryForm.reset();
        },
    });
};

const deleteCategory = (category) => {
    if (category.posts_count > 0) {
        window.alert('This category has posts. Move those posts to another category before deleting it.');
        return;
    }

    if (! window.confirm(`Delete category "${category.name}"?`)) return;
    router.delete(route('admin.content.categories.delete', category.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Content | Super Admin" />

    <SuperAdminLayout title="Content">
        <div class="grid gap-5 xl:grid-cols-12">
            <section class="glass rounded-2xl p-6 xl:col-span-7">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-[20px] font-extrabold tracking-tight text-ink-900">Content hub</h2>
                        <p class="mt-1 max-w-2xl text-[13px] leading-6 text-ink-500">
                            Manage shared content structure here. Blog writing and post editing now live on a separate Blog Posts page.
                        </p>
                    </div>
                    <Link :href="route('admin.content.blog-posts.index')" class="btn-primary px-4 py-2.5 text-[13px]">
                        Open Blog Posts
                    </Link>
                </div>

                <div class="mt-6 rounded-2xl border border-brand-indigo/15 bg-brand-indigo/5 p-5">
                    <h3 class="text-[16px] font-extrabold tracking-tight text-ink-900">Blog categories</h3>
                    <p class="mt-1 text-[12.5px] leading-5 text-ink-500">
                        Categories are shared with the Blog Posts editor and public blog listing.
                    </p>

                    <form @submit.prevent="saveCategory" class="mt-5 grid gap-4 md:grid-cols-2">
                        <Field label="Name" :error="categoryForm.errors.name" required>
                            <TextField v-model="categoryForm.name" placeholder="Auction operations" />
                        </Field>
                        <Field label="Slug" hint="Leave blank to auto-generate" :error="categoryForm.errors.slug">
                            <TextField v-model="categoryForm.slug" placeholder="auction-operations" />
                        </Field>
                        <Field label="Description" :error="categoryForm.errors.description" class="md:col-span-2">
                            <textarea v-model="categoryForm.description" rows="3" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-3 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                        </Field>
                        <div class="md:col-span-2">
                            <button type="submit" class="btn-primary px-4 py-2.5 text-[13px]" :disabled="categoryForm.processing">
                                {{ categoryForm.processing ? 'Saving...' : 'Add category' }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <aside class="space-y-5 xl:col-span-5">
                <section class="glass rounded-2xl p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-[18px] font-extrabold tracking-tight text-ink-900">Categories</h2>
                            <p class="mt-1 text-[12.5px] leading-5 text-ink-500">Keep these tidy before publishing posts.</p>
                        </div>
                        <span class="rounded-full bg-white/80 px-3 py-1 font-mono text-[11px] font-bold text-ink-500">
                            {{ categories.length }} total
                        </span>
                    </div>

                    <div class="mt-5 space-y-2">
                        <div v-for="category in categories" :key="category.id" class="flex items-center justify-between gap-3 rounded-xl border border-ink-200/70 bg-white/70 px-4 py-3">
                            <div class="min-w-0">
                                <div class="truncate text-[13px] font-bold text-ink-800">{{ category.name }}</div>
                                <div class="mt-0.5 font-mono text-[11px] text-ink-500">
                                    {{ category.slug }} · {{ category.posts_count }} posts
                                </div>
                                <p v-if="category.description" class="mt-1 line-clamp-2 text-[12px] leading-5 text-ink-500">
                                    {{ category.description }}
                                </p>
                            </div>
                            <button type="button" @click="deleteCategory(category)" class="btn-ghost shrink-0 px-3 py-1.5 text-[12px] text-rose-600" :disabled="category.posts_count > 0">
                                Delete
                            </button>
                        </div>
                        <p v-if="!categories.length" class="rounded-xl border border-dashed border-ink-200 p-5 text-center text-[13px] text-ink-500">
                            No categories yet.
                        </p>
                    </div>
                </section>
            </aside>
        </div>
    </SuperAdminLayout>
</template>
