<script setup>
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import { onBeforeUnmount, ref, watch, nextTick } from 'vue';

const props = defineProps({
    /**
     * Final exported size in CSS pixels (square).
     * Default 300×300 — this is what gets uploaded.
     */
    size:    { type: Number, default: 300 },
    label:   { type: String, default: 'Photo (300×300)' },
    initial: { type: String, default: null },          // existing URL preview
});
const emit = defineEmits(['update:file']);

const fileInput  = ref(null);
const cropperImg = ref(null);
const cropper    = ref(null);
const sourceUrl  = ref(null);                          // raw uploaded image (data URL)
const previewUrl = ref(props.initial);                 // 300x300 cropped preview
const showModal  = ref(false);

const onFile = (e) => {
    const file = e.target.files?.[0];
    if (! file) return;
    const reader = new FileReader();
    reader.onload = () => {
        sourceUrl.value = reader.result;
        showModal.value = true;
        nextTick(() => initCropper());
    };
    reader.readAsDataURL(file);
};

const initCropper = () => {
    if (! cropperImg.value) return;
    cropper.value?.destroy();
    cropper.value = new Cropper(cropperImg.value, {
        aspectRatio: 1,
        viewMode: 1,
        autoCropArea: 1,
        background: false,
        movable: true,
        zoomable: true,
        scalable: false,
        rotatable: false,
        responsive: true,
        guides: true,
    });
};

const save = async () => {
    if (! cropper.value) return;
    const canvas = cropper.value.getCroppedCanvas({
        width:  props.size,
        height: props.size,
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high',
        fillColor: '#ffffff',
    });
    canvas.toBlob((blob) => {
        if (! blob) return;
        const file = new File([blob], `photo-${props.size}.jpg`, { type: 'image/jpeg' });
        previewUrl.value = canvas.toDataURL('image/jpeg', 0.9);
        emit('update:file', file);
        close();
    }, 'image/jpeg', 0.9);
};

const close = () => {
    showModal.value = false;
    cropper.value?.destroy();
    cropper.value = null;
    sourceUrl.value = null;
    if (fileInput.value) fileInput.value.value = '';
};

const clearPreview = () => {
    previewUrl.value = null;
    emit('update:file', null);
    if (fileInput.value) fileInput.value.value = '';
};

onBeforeUnmount(() => cropper.value?.destroy());
watch(() => props.initial, (v) => { if (! cropper.value) previewUrl.value = v; });
</script>

<template>
    <div>
        <div class="flex items-start gap-4">
            <!-- Preview tile -->
            <div class="relative h-24 w-24 rounded-xl overflow-hidden border border-ink-200 bg-white/70 shrink-0 grid place-items-center">
                <img v-if="previewUrl" :src="previewUrl" class="h-full w-full object-cover" alt="Player photo preview" />
                <svg v-else class="h-8 w-8 text-ink-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <circle cx="12" cy="9" r="3.5"/>
                    <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                </svg>
                <button v-if="previewUrl" type="button" @click="clearPreview"
                        class="absolute top-0.5 right-0.5 h-5 w-5 grid place-items-center rounded-full bg-white/80 text-rose-500 hover:bg-white text-[12px]">×</button>
            </div>

            <div class="flex-1">
                <div class="text-[13px] font-medium text-ink-700 mb-1">{{ label }}</div>
                <p class="text-[12px] text-ink-500 mb-2">Pick any image — you'll crop a square that gets uploaded as {{ size }}×{{ size }}.</p>
                <label class="btn-ghost py-1.5 px-3 text-[12.5px] cursor-pointer inline-block">
                    <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFile" />
                    {{ previewUrl ? 'Replace photo' : 'Choose photo' }}
                </label>
            </div>
        </div>

        <!-- Crop modal -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 grid place-items-center px-4">
                <div class="absolute inset-0 bg-ink-900/60 backdrop-blur-sm" @click="close"></div>
                <div class="relative w-full max-w-xl glass-strong rounded-2xl p-5 shadow-glass-lg">
                    <h3 class="text-[16px] font-bold tracking-tight mb-3">Crop your photo to {{ size }}×{{ size }}</h3>

                    <div class="rounded-xl overflow-hidden bg-ink-100" style="max-height: 480px;">
                        <img ref="cropperImg" :src="sourceUrl" alt="Crop source" class="block max-w-full" style="max-height: 480px;" />
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2 justify-end">
                        <button type="button" class="btn-ghost py-2 px-4 text-[13px]" @click="close">Cancel</button>
                        <button type="button" class="btn-primary py-2 px-4 text-[13px]" @click="save">Crop &amp; use</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
