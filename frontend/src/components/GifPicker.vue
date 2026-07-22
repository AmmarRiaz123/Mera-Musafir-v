<template>
  <q-dialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)">
    <q-card class="picker">
      <q-card-section class="picker-head">
        <div class="text-h6">Choose a GIF</div>
        <q-btn flat round dense icon="close" v-close-popup />
      </q-card-section>

      <q-card-section class="q-pt-none">
        <q-input
          v-model="query" outlined dense rounded clearable
          placeholder="Search GIFs…" debounce="400"
          @update:model-value="search"
        >
          <template v-slot:prepend><q-icon name="search" /></template>
        </q-input>
      </q-card-section>

      <q-card-section class="picker-body">
        <div v-if="notice" class="picker-notice">
          <q-icon name="info" size="26px" /><div>{{ notice }}</div>
        </div>
        <div v-else-if="loading" class="picker-loading"><q-spinner-dots color="primary" size="28px" /></div>
        <div v-else-if="!gifs.length" class="picker-notice"><div>No GIFs found.</div></div>
        <div v-else class="gif-grid">
          <button v-for="g in gifs" :key="g.id" type="button" class="gif-cell" @click="pick(g)">
            <img :src="g.preview" :alt="g.title" loading="lazy" />
          </button>
        </div>
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { ref, watch } from 'vue'
import { api } from 'src/boot/axios'

const props = defineProps({ modelValue: { type: Boolean, default: false } })
const emit = defineEmits(['update:modelValue', 'picked'])

const query = ref('')
const gifs = ref([])
const loading = ref(false)
const notice = ref('')

const search = async () => {
  loading.value = true
  notice.value = ''
  try {
    const { data } = await api.get('/api/v1/media/gifs', { params: { q: query.value || '' } })
    if (data.configured === false) {
      notice.value = data.message
      gifs.value = []
    } else {
      gifs.value = data.data || []
    }
  } catch (err) {
    notice.value = err.response?.data?.message || "GIFs aren't available right now."
    gifs.value = []
  } finally {
    loading.value = false
  }
}

const pick = (gif) => {
  emit('picked', { url: gif.url, type: 'gif' })
  emit('update:modelValue', false)
}

watch(() => props.modelValue, (open) => { if (open && !gifs.value.length) search() })
</script>

<style scoped>
.picker { width: 560px; max-width: 92vw; border-radius: 14px; }
.picker-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.picker-body { max-height: 52vh; overflow-y: auto; }
.picker-loading { display: flex; justify-content: center; padding: 32px 0; }
.picker-notice {
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  padding: 34px 16px; text-align: center; color: #9b8aa5; font-size: 13px;
}
.gif-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.gif-cell {
  border: 0; padding: 0; border-radius: 8px; overflow: hidden; cursor: pointer;
  background: #f2eef5; aspect-ratio: 1; transition: transform 0.12s ease;
}
.gif-cell:hover { transform: scale(0.97); }
.gif-cell img { width: 100%; height: 100%; object-fit: cover; display: block; }
</style>
