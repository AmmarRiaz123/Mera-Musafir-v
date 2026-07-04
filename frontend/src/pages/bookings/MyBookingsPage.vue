<template>
  <q-page padding>
    <div class="text-h4 text-weight-bold q-mb-lg">My Bookings</div>

    <!-- Loading -->
    <div v-if="loading" class="column q-gutter-md">
      <q-card v-for="n in 3" :key="n" flat bordered>
        <q-card-section class="row items-center q-gutter-md">
          <q-skeleton type="QAvatar" size="56px" />
          <div class="col">
            <q-skeleton type="text" width="50%" />
            <q-skeleton type="text" width="30%" />
          </div>
          <q-skeleton type="QBtn" />
        </q-card-section>
      </q-card>
    </div>

    <!-- Empty state -->
    <div v-else-if="bookings.length === 0" class="text-center q-py-xl">
      <q-icon name="confirmation_number" size="64px" color="grey-5" />
      <div class="text-h6 text-grey-7 q-mt-md">No bookings yet</div>
      <div class="text-grey-6 q-mb-lg">Browse agency packages and book your next trip!</div>
      <q-btn color="deep-purple" label="Explore Packages" to="/packages" unelevated rounded />
    </div>

    <!-- Bookings list -->
    <div v-else class="column q-gutter-md">
      <q-card v-for="b in bookings" :key="b.id" flat bordered class="booking-card">
        <q-card-section class="row items-center no-wrap q-gutter-md">
          <!-- Package cover -->
          <q-avatar rounded size="56px" class="bg-grey-3">
            <img v-if="b.package?.cover_image" :src="b.package.cover_image" />
            <q-icon v-else name="landscape" color="grey-5" />
          </q-avatar>

          <!-- Details -->
          <div class="col">
            <div
              class="text-subtitle1 text-weight-bold cursor-pointer"
              @click="b.package?.slug && $router.push(`/packages/${b.package.slug}`)"
            >
              {{ b.package?.title || 'Package' }}
            </div>
            <div class="text-caption text-grey-7">
              <q-icon name="place" size="xs" /> {{ b.package?.destination?.name || '—' }}
              <span v-if="b.package?.start_date">
                · {{ fmtDate(b.package.start_date) }}
              </span>
            </div>
            <div class="text-caption text-grey-7 q-mt-xs">
              {{ b.travelers_count }} traveler(s) · PKR {{ fmt(b.total_amount) }}
            </div>
          </div>

          <!-- Status + action -->
          <div class="column items-end q-gutter-xs">
            <q-badge :color="statusColor(b.status)" class="q-pa-sm text-body2 capitalize">
              {{ b.status }}
            </q-badge>
            <q-btn
              v-if="b.status === 'confirmed' && b.package?.trip_id"
              unelevated
              color="deep-purple"
              icon="forum"
              label="Join Group Chat"
              size="sm"
              :to="`/trips/${b.package.trip_id}/chat`"
            />
            <span
              v-else-if="b.status === 'confirmed'"
              class="text-caption text-grey-6"
              style="max-width: 160px; text-align: right"
            >
              Group chat available soon
            </span>
          </div>
        </q-card-section>
      </q-card>
    </div>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { api } from 'src/boot/axios'

const loading = ref(true)
const bookings = ref([])

const load = async () => {
  loading.value = true
  try {
    const r = await api.get('/api/v1/bookings/my')
    bookings.value = r.data.data || []
  } catch {
    bookings.value = []
  } finally {
    loading.value = false
  }
}

const statusColor = (s) =>
  ({ pending: 'grey-6', confirmed: 'positive', cancelled: 'negative', completed: 'blue' }[s] ?? 'grey')

const fmtDate = (d) =>
  d ? new Date(d + 'T00:00:00').toLocaleDateString('en-PK', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'

const fmt = (n) => Number(n || 0).toLocaleString()

onMounted(load)
</script>

<style scoped>
.booking-card { transition: box-shadow 0.2s; }
.booking-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
</style>
