<template>
  <q-page class="admin-page">
    <header class="ap-head">
      <div>
        <h1 class="ap-title">Users</h1>
        <p class="ap-sub">Search, filter and suspend accounts.</p>
      </div>
    </header>

    <div class="ap-filters">
      <q-input
        v-model="search"
        class="ap-search" outlined dense rounded clearable
        placeholder="Search by name or email…" debounce="350"
        @update:model-value="reload"
      >
        <template #prepend><q-icon name="search" /></template>
      </q-input>

      <div class="ap-seg">
        <button
          v-for="t in typeTabs" :key="t.value" type="button"
          class="ap-seg-btn" :class="{ 'ap-seg-btn--active': type === t.value }"
          @click="setType(t.value)"
        >{{ t.label }}</button>
      </div>

      <div class="ap-seg">
        <button
          v-for="s in statusTabs" :key="s.value" type="button"
          class="ap-seg-btn" :class="{ 'ap-seg-btn--active': status === s.value }"
          @click="setStatus(s.value)"
        >{{ s.label }}</button>
      </div>
    </div>

    <div v-if="loading" class="ap-loading"><q-spinner-dots color="deep-purple" size="30px" /></div>

    <div v-else-if="!users.length" class="ap-card">
      <div class="ap-empty">
        <q-icon name="person_search" size="36px" />
        <div class="ap-empty-title">No users match</div>
      </div>
    </div>

    <div v-else class="ap-card">
      <div v-for="u in users" :key="u.id" class="ap-row">
        <q-avatar size="40px" class="au-avatar">
          <img v-if="u.avatar" :src="u.avatar" />
          <span v-else>{{ u.name?.[0]?.toUpperCase() }}</span>
        </q-avatar>

        <div class="au-main">
          <div class="au-name-line">
            <span class="au-name">{{ u.name }}</span>
            <q-icon v-if="u.is_verified" name="verified" size="14px" color="deep-purple" />
            <span class="au-type" :class="`au-type--${u.type}`">{{ u.type }}</span>
            <span v-if="u.is_suspended" class="au-susp">Suspended</span>
          </div>
          <div class="au-meta">
            {{ u.email }}
            <span class="au-dot">·</span>{{ u.followers }} follower{{ u.followers === 1 ? '' : 's' }}
            <span class="au-dot">·</span>joined {{ u.joined }}
          </div>
        </div>

        <q-btn
          flat round dense icon="open_in_new" color="grey-6" size="sm"
          :to="`/profile/${u.id}`" target="_blank"
        >
          <q-tooltip>View profile</q-tooltip>
        </q-btn>
        <q-btn
          :outline="!u.is_suspended" :unelevated="u.is_suspended"
          :color="u.is_suspended ? 'positive' : 'negative'"
          no-caps rounded dense size="sm" class="au-action"
          :label="u.is_suspended ? 'Reinstate' : 'Suspend'"
          :loading="acting === u.id"
          @click="toggleSuspend(u)"
        />
      </div>
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

const typeTabs = [
  { value: '', label: 'All' },
  { value: 'traveler', label: 'Travellers' },
  { value: 'agency', label: 'Agencies' },
]
const statusTabs = [
  { value: '', label: 'Any' },
  { value: 'active', label: 'Active' },
  { value: 'suspended', label: 'Suspended' },
]

const users = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const loading = ref(true)
const acting = ref(null)
const search = ref('')
const type = ref('')
const status = ref('')
const page = ref(1)

const load = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/admin/users', {
      params: { search: search.value || undefined, type: type.value || undefined, status: status.value || undefined, page: page.value },
    })
    users.value = data.data
    meta.value = data.meta
  } finally {
    loading.value = false
  }
}

const reload = () => { page.value = 1; load() }
const setType = (v) => { type.value = v; reload() }
const setStatus = (v) => { status.value = v; reload() }
const go = (p) => { page.value = p; load() }

const toggleSuspend = async (u) => {
  acting.value = u.id
  try {
    const { data } = await api.post(`/api/v1/admin/users/${u.id}/suspend`)
    u.is_suspended = data.is_suspended
    $q.notify({ color: 'positive', icon: 'check_circle', message: data.message, position: 'top' })
  } catch (err) {
    $q.notify({ color: 'negative', message: err.response?.data?.message || 'Could not update that user.', position: 'top' })
  } finally {
    acting.value = null
  }
}

onMounted(load)
</script>

<style scoped>
@import 'src/css/admin.scss';

.au-avatar {
  flex-shrink: 0;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  color: #fff; font-weight: 700; font-size: 14px;
}
.au-main { flex: 1; min-width: 0; }
.au-name-line { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
.au-name { font-size: 14px; font-weight: 600; color: #241636; }
.au-type {
  padding: 1px 7px; border-radius: 999px; font-size: 9.5px; font-weight: 700;
  letter-spacing: 0.04em; text-transform: uppercase;
}
.au-type--traveler { background: #ede9fb; color: #6d28d9; }
.au-type--agency { background: #fef3c7; color: #b45309; }
.au-type--admin { background: #1e1533; color: #c4b5fd; }
.au-susp {
  padding: 1px 7px; border-radius: 999px; background: #fdecec; color: #dc2626;
  font-size: 9.5px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
}
.au-meta { font-size: 11.5px; color: #8b7ea6; margin-top: 2px; }
.au-dot { opacity: 0.5; margin: 0 4px; }
.au-action { min-width: 92px; }
</style>
