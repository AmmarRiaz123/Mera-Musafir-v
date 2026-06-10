<template>
  <q-page padding>
    <div class="row items-center justify-between q-mb-lg">
      <div class="text-h4 text-weight-bold">My Trips</div>
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
    </q-tab-panels>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useTripStore } from 'src/stores/tripStore'

const tripStore = useTripStore()
const activeTab = ref('created')

const formatDate = (dateStr) => {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-PK', { day: 'numeric', month: 'short', year: 'numeric' })
}

onMounted(async () => {
  await tripStore.fetchMyTrips()
})
</script>

<style scoped>
.capitalize { text-transform: capitalize; }
.card-hover { transition: transform 0.2s, box-shadow 0.2s; }
.card-hover:hover { transform: translateY(-4px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
</style>
