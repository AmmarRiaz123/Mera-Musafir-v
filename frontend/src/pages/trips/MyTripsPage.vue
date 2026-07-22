<template>
  <q-page padding>
    <div class="row items-center justify-between q-mb-lg">
      <div>
        <span class="page-eyebrow"><q-icon name="luggage" size="12px" />Trips</span>
        <h1 class="page-title">My Trips</h1>
      </div>
      <q-btn color="primary" icon="add" label="Create Trip" to="/trips/create" unelevated rounded />
    </div>

    <q-tabs
      v-model="activeTab"
      color="primary"
      indicator-color="primary"
      align="left"
      class="q-mb-lg"
    >
      <q-tab name="created" icon="star" label="Created by Me" />
      <q-tab name="joined" icon="group" label="Trips I Joined" />
      <q-tab name="packages" icon="card_travel" label="My Packages" />
    </q-tabs>

    <div v-if="tripStore.loading" class="row q-col-gutter-md">
      <div v-for="n in 4" :key="n" class="col-12 col-sm-6 col-md-4">
        <q-card flat bordered>
          <q-skeleton height="180px" square />
          <q-card-section>
            <q-skeleton type="text" class="text-h6" />
            <q-skeleton type="text" />
          </q-card-section>
        </q-card>
      </div>
    </div>

    <q-tab-panels v-else v-model="activeTab" animated>
      <!-- Created by me -->
      <q-tab-panel name="created" class="q-pa-none">
        <div v-if="!tripStore.myTrips.created.length" class="text-center q-py-xl">
          <q-icon name="add_road" size="64px" color="grey-5" />
          <div class="text-h6 text-grey-7 q-mt-md">No trips created yet</div>
          <div class="text-grey-6 q-mb-lg">Start by creating your first trip</div>
          <q-btn color="primary" label="Create a Trip" to="/trips/create" unelevated rounded />
        </div>
        <div v-else class="row q-col-gutter-md">
          <div v-for="trip in tripStore.myTrips.created" :key="trip.id" class="col-12 col-sm-6 col-md-4">
            <q-card class="cursor-pointer card-hover" flat bordered @click="$router.push(`/trips/${trip.id}`)">
              <q-img
                :src="trip.cover_image || 'https://via.placeholder.com/600x350?text=No+Image'"
                ratio="1.7778"
                class="bg-grey-3"
              >
                <div class="absolute-top-right q-pa-sm">
                  <q-badge color="primary" class="shadow-1 capitalize q-pa-xs">{{ trip.type }}</q-badge>
                </div>
              </q-img>
              <q-card-section>
                <div class="text-h6 text-weight-bold ellipsis">{{ trip.title }}</div>
                <div class="row items-center text-grey-7 text-caption q-mt-xs">
                  <q-icon name="place" size="xs" class="q-mr-xs" />
                  {{ trip.destination?.name || '—' }}
                </div>
              </q-card-section>
              <q-card-section class="q-pt-none">
                <div class="row items-center justify-between text-caption text-grey-8 q-mb-xs">
                  <span>
                    <q-icon name="calendar_today" size="xs" class="q-mr-xs" />
                    {{ formatDate(trip.start_date) }}
                  </span>
                  <span>
                    <q-icon name="group" size="xs" class="q-mr-xs" />
                    {{ trip.current_count }}/{{ trip.max_travelers }}
                  </span>
                </div>
                <q-linear-progress
                  :value="trip.current_count / trip.max_travelers"
                  :color="trip.is_full ? 'negative' : 'primary'"
                  rounded
                  size="6px"
                />
                <div class="row justify-end q-mt-sm">
                  <q-btn flat color="primary" label="View Trip" dense :to="`/trips/${trip.id}`" />
                </div>
              </q-card-section>
            </q-card>
          </div>
        </div>
      </q-tab-panel>

      <!-- Joined trips -->
      <q-tab-panel name="joined" class="q-pa-none">
        <div v-if="!tripStore.myTrips.joined.length" class="text-center q-py-xl">
          <q-icon name="explore" size="64px" color="grey-5" />
          <div class="text-h6 text-grey-7 q-mt-md">You haven't joined any trips yet</div>
          <div class="text-grey-6 q-mb-lg">Browse trips and join one that excites you</div>
          <q-btn color="primary" label="Browse Trips" to="/trips" unelevated rounded />
        </div>
        <div v-else class="row q-col-gutter-md">
          <div v-for="trip in tripStore.myTrips.joined" :key="trip.id" class="col-12 col-sm-6 col-md-4">
            <q-card class="cursor-pointer card-hover" flat bordered @click="$router.push(`/trips/${trip.id}`)">
              <q-img
                :src="trip.cover_image || 'https://via.placeholder.com/600x350?text=No+Image'"
                ratio="1.7778"
                class="bg-grey-3"
              >
                <div class="absolute-top-right q-pa-sm">
                  <q-badge color="secondary" class="shadow-1 capitalize q-pa-xs">{{ trip.type }}</q-badge>
                </div>
              </q-img>
              <q-card-section>
                <div class="text-h6 text-weight-bold ellipsis">{{ trip.title }}</div>
                <div class="row items-center text-grey-7 text-caption q-mt-xs">
                  <q-icon name="place" size="xs" class="q-mr-xs" />
                  {{ trip.destination?.name || '—' }}
                </div>
              </q-card-section>
              <q-card-section class="q-pt-none">
                <div class="row items-center justify-between text-caption text-grey-8 q-mb-xs">
                  <span>
                    <q-icon name="calendar_today" size="xs" class="q-mr-xs" />
                    {{ formatDate(trip.start_date) }}
                  </span>
                  <span>
                    <q-icon name="group" size="xs" class="q-mr-xs" />
                    {{ trip.current_count }}/{{ trip.max_travelers }}
                  </span>
                </div>
                <q-linear-progress
                  :value="trip.current_count / trip.max_travelers"
                  :color="trip.is_full ? 'negative' : 'primary'"
                  rounded
                  size="6px"
                />
                <div class="row justify-end q-mt-sm">
                  <q-btn flat color="primary" label="View Trip" dense :to="`/trips/${trip.id}`" />
                </div>
              </q-card-section>
            </q-card>
          </div>
        </div>
      </q-tab-panel>

      <!-- Packages I've booked -->
      <q-tab-panel name="packages" class="q-pa-none">
        <div v-if="bookingsLoading" class="column q-gutter-md">
          <q-card v-for="n in 3" :key="n" flat bordered class="q-pa-md">
            <q-skeleton type="text" width="45%" />
            <q-skeleton type="text" width="30%" />
          </q-card>
        </div>

        <div v-else-if="!bookings.length" class="text-center q-py-xl">
          <q-icon name="card_travel" size="64px" color="grey-5" />
          <div class="text-h6 text-grey-7 q-mt-md">No packages booked yet</div>
          <div class="text-body2 text-grey-6 q-mt-xs">
            Book a curated package from an agency and it'll show up here.
          </div>
          <q-btn
            class="q-mt-md" color="primary" unelevated rounded
            icon="explore" label="Browse Packages" to="/packages"
          />
        </div>

        <div v-else class="column q-gutter-md">
          <q-card v-for="b in bookings" :key="b.id" flat bordered class="booking-row">
            <div class="row items-center no-wrap q-pa-md q-gutter-md">
              <q-avatar rounded size="60px" class="bg-grey-3">
                <img v-if="b.package?.cover_image" :src="b.package.cover_image" />
                <q-icon v-else name="landscape" color="grey-5" />
              </q-avatar>

              <div class="col" style="min-width: 0">
                <div
                  class="text-subtitle1 text-weight-bold cursor-pointer ellipsis"
                  @click="b.package?.slug && $router.push(`/packages/${b.package.slug}`)"
                >
                  {{ b.package?.title || 'Package' }}
                </div>
                <div class="text-caption text-grey-7 q-mt-xs">
                  <q-icon name="place" size="xs" /> {{ b.package?.destination?.name || '—' }}
                  <span v-if="b.package?.start_date"> · {{ formatDate(b.package.start_date) }}</span>
                </div>
                <div class="text-caption text-grey-7 q-mt-xs">
                  {{ b.travelers_count }} {{ b.travelers_count === 1 ? 'traveler' : 'travelers' }}
                  · <span class="text-weight-medium text-deep-purple">PKR {{ fmtNum(b.total_amount) }}</span>
                </div>
              </div>

              <div class="column items-end q-gutter-sm">
                <q-badge :color="bookingColor(b.status)" class="q-px-sm q-py-xs capitalize">
                  {{ statusLabel(b) }}
                </q-badge>

                <!-- Approved and unpaid is the one state that needs an action. -->
                <template v-if="b.awaiting_payment">
                  <q-btn
                    unelevated dense no-caps size="sm" rounded
                    color="deep-purple" icon="lock" label="Pay now"
                    :to="`/checkout?type=booking&id=${b.id}`"
                  />
                  <span v-if="b.payment_due_at" class="text-caption" :class="dueClass(b)">
                    {{ dueLabel(b) }}
                  </span>
                </template>

                <q-btn
                  v-else-if="b.status === 'confirmed' && b.package?.trip_id"
                  unelevated dense no-caps size="sm"
                  color="deep-purple" icon="forum" label="Group chat"
                  :to="`/trips/${b.package.trip_id}/chat`"
                />
                <span v-else-if="b.status === 'confirmed'" class="text-caption text-grey-6">
                  Group chat soon
                </span>
                <span v-else-if="b.status === 'pending'" class="text-caption text-grey-6">
                  Waiting on the agency
                </span>
              </div>
            </div>
          </q-card>
        </div>
      </q-tab-panel>
    </q-tab-panels>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useTripStore } from 'src/stores/tripStore'
import { api } from 'src/boot/axios'

const route = useRoute()
const tripStore = useTripStore()

// Deep-linkable, so the sidebar can point straight at the packages tab.
const activeTab = ref(['created', 'joined', 'packages'].includes(route.query.tab) ? route.query.tab : 'created')

const bookings = ref([])
const bookingsLoading = ref(true)

const formatDate = (dateStr) => {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-PK', { day: 'numeric', month: 'short', year: 'numeric' })
}

const fmtNum = (n) => Number(n || 0).toLocaleString()

const bookingColor = (s) =>
  ({ pending: 'orange', approved: 'deep-purple', confirmed: 'positive', cancelled: 'negative', completed: 'blue' }[s] ?? 'grey')

// "Approved" alone reads like it's done, when the traveller still owes money.
const statusLabel = (b) => (b.awaiting_payment ? 'Payment due' : b.status)

const hoursLeft = (b) =>
  b.payment_due_at
    ? (new Date(b.payment_due_at.replace(' ', 'T')) - Date.now()) / 36e5
    : null

const dueLabel = (b) => {
  const h = hoursLeft(b)
  if (h === null) return ''
  if (h <= 0) return 'Window closed'
  if (h < 1) return `${Math.ceil(h * 60)} min left`
  if (h < 24) return `${Math.floor(h)}h left to pay`
  return `${Math.floor(h / 24)}d left to pay`
}

const dueClass = (b) => ((hoursLeft(b) ?? 99) < 12 ? 'text-negative' : 'text-grey-6')

const loadBookings = async () => {
  bookingsLoading.value = true
  try {
    const r = await api.get('/api/v1/bookings/my')
    bookings.value = r.data.data || []
  } catch {
    bookings.value = []
  } finally {
    bookingsLoading.value = false
  }
}

onMounted(async () => {
  await Promise.all([tripStore.fetchMyTrips(), loadBookings()])
})
</script>

<style scoped>
.capitalize { text-transform: capitalize; }
.card-hover { transition: transform 0.2s, box-shadow 0.2s; }
.card-hover:hover { transform: translateY(-4px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
.booking-row { border-radius: 12px; transition: box-shadow 0.2s, transform 0.2s; }
.booking-row:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(43,27,51,0.09); }
</style>
