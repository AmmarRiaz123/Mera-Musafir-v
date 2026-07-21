<template>
  <div class="image-upload" :class="{ 'image-upload--round': round }">
    <div
      class="drop"
      :class="{ 'drop--round': round, 'drop--busy': uploading }"
      :style="preview ? { backgroundImage: `url(${preview})` } : null"
      @click="pick"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
      :data-dragging="dragging"
    >
      <template v-if="!preview">
        <q-icon :name="icon" size="26px" />
        <span class="drop-hint">{{ label }}</span>
        <span class="drop-sub">JPG, PNG or WebP · max 5MB</span>
      </template>

      <div v-if="uploading" class="drop-overlay">
        <q-spinner-dots color="white" size="28px" />
      </div>

      <div v-else-if="preview" class="drop-overlay drop-overlay--hover">
        <q-icon name="photo_camera" size="22px" color="white" />
        <span>Change</span>
      </div>
    </div>

    <q-btn
      v-if="preview && !uploading"
      flat dense no-caps size="sm" color="grey-7"
      icon="close" label="Remove"
      class="q-mt-xs"
      @click.stop="clear"
    />

    <input
      ref="input"
      type="file"
      accept="image/jpeg,image/png,image/webp"
      class="hidden-input"
      @change="onSelect"
    />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useQuasar } from 'quasar'
import { api } from 'src/boot/axios'

const props = defineProps({
  // Stored value: a path (or absolute URL) returned by the upload endpoint.
  modelValue: { type: String, default: null },
  // Upload bucket: avatar | trip_cover | package_cover | agency_logo | destination
  type: { type: String, required: true },
  label: { type: String, default: 'Upload an image' },
  icon: { type: String, default: 'add_photo_alternate' },
  round: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

const $q = useQuasar()
const input = ref(null)
const uploading = ref(false)
const dragging = ref(false)
const localUrl = ref(null)

// The API returns an absolute URL; a stored value may be a bare path.
const preview = computed(() => {
  if (localUrl.value) return localUrl.value
  const v = props.modelValue
  if (!v) return null
  return /^(https?:|data:|\/\/)/.test(v) ? v : `http://localhost:8000/storage/${v}`
})

const pick = () => input.value?.click()

const onSelect = (e) => {
  const file = e.target.files?.[0]
  if (file) upload(file)
  e.target.value = ''
}

const onDrop = (e) => {
  dragging.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file) upload(file)
}

const upload = async (file) => {
  if (!file.type.startsWith('image/')) {
    $q.notify({ type: 'negative', message: 'Please choose an image file', position: 'top' })
    return
  }

  uploading.value = true
  try {
    const form = new FormData()
    form.append('file', file)
    form.append('type', props.type)

    // The axios instance defaults to application/json. For FormData that header
    // must be cleared so the browser can set multipart/form-data *with* its
    // boundary — otherwise the file never reaches the server.
    const { data } = await api.post('/api/v1/uploads', form, {
      headers: { 'Content-Type': undefined },
    })
    localUrl.value = data.url
    emit('update:modelValue', data.path)
  } catch (err) {
    $q.notify({
      type: 'negative',
      position: 'top',
      message: err.response?.data?.errors?.file?.[0] || 'Upload failed',
    })
  } finally {
    uploading.value = false
  }
}

const clear = () => {
  localUrl.value = null
  emit('update:modelValue', null)
}
</script>

<style scoped>
.image-upload { display: inline-flex; flex-direction: column; align-items: center; }

.drop {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  width: 100%;
  min-width: 200px;
  height: 132px;
  padding: 10px;
  border: 1.5px dashed #d9cfe2;
  border-radius: 12px;
  background: #faf7fc center/cover no-repeat;
  color: #8a7a92;
  cursor: pointer;
  overflow: hidden;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}
.drop:hover { border-color: var(--q-primary); }
.drop[data-dragging='true'] { border-color: var(--q-primary); background-color: #f3e9f8; }
.drop--round { width: 108px; min-width: 108px; height: 108px; border-radius: 50%; }
.drop--busy { cursor: progress; }

.drop-hint { font-size: 12.5px; font-weight: 500; }
.drop-sub { font-size: 10.5px; color: #b0a3b8; }
.drop--round .drop-sub { display: none; }

.drop-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 3px;
  background: rgba(43, 27, 51, 0.5);
  color: #fff;
  font-size: 11.5px;
  font-weight: 500;
}
.drop-overlay--hover { opacity: 0; transition: opacity 0.15s ease; }
.drop:hover .drop-overlay--hover { opacity: 1; }

.hidden-input { display: none; }
</style>
