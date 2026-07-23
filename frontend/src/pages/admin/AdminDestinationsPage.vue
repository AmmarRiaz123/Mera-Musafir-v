<template>
  <q-page class="admin-page">
    <header class="ap-head">
      <div>
        <h1 class="ap-title">Destinations</h1>
        <p class="ap-sub">Feature places on the homepage, or hide them from discovery.</p>
      </div>
    </header>

    <div class="ap-filters">
      <q-input
        v-model="search" class="ap-search" outlined dense rounded clearable
        placeholder="Search destinations…" debounce="350" @update:model-value="reload"
      >
        <template #prepend><q-icon name="search" /></template>
      </q-input>
    </div>

    <div v-if="loading" class="ap-loading"><q-spinner-dots color="deep-purple" size="30px" /></div>

    <div v-else-if="!destinations.length" class="ap-card">
      <div class="ap-empty"><q-icon name="public_off" size="36px" /><div class="ap-empty-title">No destinations match</div></div>
    </div>

    <div v-else class="ds-grid">
      <article v-for="d in destinations" :key="d.id" class="ds-card" :class="{ 'ds-card--hidden': !d.is_active }">
        <div class="ds-cover">
          <img v-if="d.cover_image" :src="d.cover_image" :alt="d.name" />
          <div v-else class="ds-cover-empty"><q-icon name="landscape" size="30px" /></div>
          <span v-if="d.is_featured" class="ds-featured"><q-icon name="star" size="12px" />Featured</span>
          <span v-if="!d.is_active" class="ds-hidden-tag">Hidden</span>
        </div>

        <div class="ds-body">
          <div class="ds-name">{{ d.name }}</div>
          <div class="ds-meta">{{ d.province }} · {{ d.trips_count }} trip{{ d.trips_count === 1 ? '' : 's' }}</div>

          <div class="ds-toggles">
            <q-toggle
              :model-value="d.is_featured" size="sm" color="deep-purple" label="Featured"
              :disable="acting === d.id" @update:model-value="toggle(d, 'feature')"
            />
            <q-toggle
              :model-value="d.is_active" size="sm" color="teal" label="Visible"
              :disable="acting === d.id" @update:model-value="toggle(d, 'active')"
            />
          </div>
        </div>
      </article>
    </div>

    <div v-if="meta.last_page > 1" class="ap-pager">
      <q-btn flat round dense icon="chevron_left" :disable="page <= 1" @click="go(page - 1)" />
      <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <q-btn flat round dense icon="chevron_right" :disable="page >= meta.last_page" @click="go(page + 1)" />
    </div>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useQuasar } from 'quasar'
import { api } from 'src/boot/axios'

const $q = useQuasar()

const destinations = ref([])
const meta = ref({ current_page: 1, last_page: 1 })
const loading = ref(true)
const acting = ref(null)
const search = ref('')
const page = ref(1)

const load = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/admin/destinations', {
      params: { search: search.value || undefined, page: page.value },
    })
    destinations.value = data.data
    meta.value = data.meta
  } finally {
    loading.value = false
  }
}

const reload = () => { page.value = 1; load() }
const go = (p) => { page.value = p; load() }

const toggle = async (d, which) => {
  acting.value = d.id
  try {
    const { data } = await api.post(`/api/v1/admin/destinations/${d.id}/${which}`)
    if (which === 'feature') d.is_featured = data.is_featured
    else d.is_active = data.is_active
  } catch {
    $q.notify({ color: 'negative', message: 'Could not update that destination.', position: 'top' })
  } finally {
    acting.value = null
  }
}

onMounted(load)
</script>

<style scoped>
@import 'src/css/admin.scss';

.ds-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
@media (max-width: 900px) { .ds-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 560px) { .ds-grid { grid-template-columns: 1fr; } }

.ds-card {
  background: #fff; border: 1px solid #e7e4f0; border-radius: 15px; overflow: hidden;
  transition: opacity 0.15s ease;
}
.ds-card--hidden { opacity: 0.62; }

.ds-cover { position: relative; height: 118px; background: #ede9fb; }
.ds-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
.ds-cover-empty { height: 100%; display: grid; place-items: center; color: #b4a7de; }
.ds-featured {
  position: absolute; top: 8px; left: 8px;
  display: inline-flex; align-items: center; gap: 3px;
  padding: 3px 8px; border-radius: 999px; color: #fff;
  background: linear-gradient(135deg, #f59e0b, #d97706);
  font-size: 10px; font-weight: 700; letter-spacing: 0.03em;
}
.ds-hidden-tag {
  position: absolute; top: 8px; right: 8px;
  padding: 3px 8px; border-radius: 999px; background: rgba(30, 21, 51, 0.85); color: #fff;
  font-size: 10px; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase;
}

.ds-body { padding: 12px 14px 14px; }
.ds-name { font-size: 14.5px; font-weight: 700; color: #241636; }
.ds-meta { font-size: 11.5px; color: #8b7ea6; margin-top: 2px; }
.ds-toggles { display: flex; gap: 14px; margin-top: 10px; }
</style>
