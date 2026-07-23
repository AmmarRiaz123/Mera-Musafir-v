<template>
  <q-page class="admin-page">
    <header class="ap-head">
      <div>
        <h1 class="ap-title">Agencies</h1>
        <p class="ap-sub">Verify agencies before they get the trusted badge.</p>
      </div>
    </header>

    <div class="ap-filters">
      <div class="ap-seg">
        <button
          v-for="s in tabs" :key="s.value" type="button"
          class="ap-seg-btn" :class="{ 'ap-seg-btn--active': status === s.value }"
          @click="setStatus(s.value)"
        >{{ s.label }}</button>
      </div>
    </div>

    <div v-if="loading" class="ap-loading"><q-spinner-dots color="deep-purple" size="30px" /></div>

    <div v-else-if="!agencies.length" class="ap-card">
      <div class="ap-empty">
        <q-icon name="verified" size="36px" />
        <div class="ap-empty-title">{{ status === 'pending' ? 'Nothing awaiting review' : 'No agencies here' }}</div>
      </div>
    </div>

    <div v-else class="ag-grid">
      <article v-for="a in agencies" :key="a.id" class="ag-card">
        <div class="ag-top">
          <q-avatar rounded size="46px" class="ag-logo">
            <img v-if="a.logo" :src="a.logo" />
            <q-icon v-else name="business" size="22px" color="deep-purple" />
          </q-avatar>
          <div class="ag-id">
            <div class="ag-name-line">
              <span class="ag-name">{{ a.business_name }}</span>
              <q-icon v-if="a.is_verified" name="verified" size="15px" color="deep-purple" />
            </div>
            <span class="ag-tier">{{ a.tier }} · {{ a.packages_count }} package{{ a.packages_count === 1 ? '' : 's' }}</span>
          </div>
        </div>

        <div class="ag-facts">
          <div v-if="a.license_number" class="ag-fact">
            <q-icon name="badge" size="14px" /><span>Licence {{ a.license_number }}</span>
          </div>
          <div v-if="a.owner" class="ag-fact">
            <q-icon name="person" size="14px" /><span>{{ a.owner.name }} · {{ a.owner.email }}</span>
          </div>
          <div v-if="a.contact_phone" class="ag-fact">
            <q-icon name="phone" size="14px" /><span>{{ a.contact_phone }}</span>
          </div>
        </div>

        <div class="ag-actions">
          <q-btn
            flat no-caps dense color="grey-7" size="sm"
            icon="storefront" label="Storefront"
            :to="`/agencies/${a.slug}`" target="_blank"
          />
          <q-btn
            :unelevated="!a.is_verified" :outline="a.is_verified"
            :color="a.is_verified ? 'grey-7' : 'deep-purple'"
            no-caps rounded dense size="sm"
            :icon="a.is_verified ? 'remove_moderator' : 'verified'"
            :label="a.is_verified ? 'Unverify' : 'Verify'"
            :loading="acting === a.id"
            @click="toggleVerify(a)"
          />
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

const emit = defineEmits(['queues'])
const $q = useQuasar()

const tabs = [
  { value: 'pending', label: 'Awaiting review' },
  { value: 'verified', label: 'Verified' },
  { value: 'all', label: 'All' },
]

const agencies = ref([])
const meta = ref({ current_page: 1, last_page: 1 })
const loading = ref(true)
const acting = ref(null)
const status = ref('pending')
const page = ref(1)

const load = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/admin/agencies', {
      params: { status: status.value, page: page.value },
    })
    agencies.value = data.data
    meta.value = data.meta
  } finally {
    loading.value = false
  }
}

const setStatus = (v) => { status.value = v; page.value = 1; load() }
const go = (p) => { page.value = p; load() }

const toggleVerify = async (a) => {
  acting.value = a.id
  try {
    const { data } = await api.post(`/api/v1/admin/agencies/${a.id}/verify`)
    a.is_verified = data.is_verified
    $q.notify({ color: 'positive', icon: 'check_circle', message: data.message, position: 'top' })
    // The pending queue shrank — refresh the nav badge, and drop the row if
    // this tab only shows unverified.
    refreshBadge()
    if (status.value === 'pending' && a.is_verified) {
      agencies.value = agencies.value.filter((x) => x.id !== a.id)
    }
  } catch (err) {
    $q.notify({ color: 'negative', message: err.response?.data?.message || 'Could not update that agency.', position: 'top' })
  } finally {
    acting.value = null
  }
}

const refreshBadge = async () => {
  try {
    const { data } = await api.get('/api/v1/admin/dashboard')
    emit('queues', data.data.queues)
  } catch { /* badge stays as-is */ }
}

onMounted(load)
</script>

<style scoped>
@import 'src/css/admin.scss';

.ag-grid {
  display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px;
}
@media (max-width: 760px) { .ag-grid { grid-template-columns: 1fr; } }

.ag-card {
  background: #fff; border: 1px solid #e7e4f0; border-radius: 15px; padding: 16px;
  display: flex; flex-direction: column;
}
.ag-top { display: flex; align-items: center; gap: 12px; }
.ag-logo { flex-shrink: 0; background: #efe4f6; }
.ag-id { flex: 1; min-width: 0; }
.ag-name-line { display: flex; align-items: center; gap: 6px; }
.ag-name { font-size: 15px; font-weight: 700; color: #241636; }
.ag-tier { font-size: 11.5px; color: #8b7ea6; text-transform: capitalize; }

.ag-facts {
  margin: 12px 0; padding: 11px 12px;
  border-radius: 11px; background: #faf9fd; border: 1px solid #f0edf7;
  display: flex; flex-direction: column; gap: 6px;
}
.ag-fact {
  display: flex; align-items: center; gap: 7px;
  font-size: 12px; color: #5a4d6e;
}
.ag-fact .q-icon { color: #9b8aa5; flex-shrink: 0; }
.ag-fact span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.ag-actions { display: flex; justify-content: space-between; align-items: center; margin-top: auto; }
</style>
