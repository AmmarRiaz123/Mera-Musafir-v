<template>
  <q-page padding>
    <!-- Hero -->
    <div class="q-mb-xl">
      <div class="text-h4 text-weight-bold q-mb-xs">
        Welcome back, {{ authStore.user?.name?.split(' ')[0] }} 👋
      </div>
      <div class="text-body2 text-grey-6 q-mb-md">Find your next adventure in Pakistan</div>
      <q-input
        v-model="searchQuery"
        outlined
        rounded
        placeholder="Search trips, destinations..."
        class="search-bar"
        style="max-width: 520px"
        @keyup.enter="goSearch"
      >
        <template v-slot:prepend><q-icon name="search" /></template>
        <template v-slot:append>
          <q-btn flat round dense icon="arrow_forward" color="deep-purple" @click="goSearch" />
        </template>
      </q-input>
    </div>

    <!-- Quick stats -->
    <div class="row q-col-gutter-md q-mb-xl">
      <div v-for="stat in stats" :key="stat.label" class="col-4">
        <q-card flat bordered class="text-center q-pa-md">
          <q-icon :name="stat.icon" color="deep-purple" size="1.8em" class="q-mb-xs" />
          <div class="text-h5 text-weight-bold text-deep-purple">{{ stat.value }}</div>
          <div class="text-caption text-grey-6">{{ stat.label }}</div>
        </q-card>
      </div>
    </div>

    <!-- Suggested trips -->
    <div class="q-mb-xl">
      <div class="row items-center justify-between q-mb-md">
        <div>
          <div class="text-h6 text-weight-bold">Trips Picked for You</div>
          <div class="text-caption text-grey-6">Based on your preferences and travel history</div>
        </div>
        <q-btn flat color="deep-purple" label="See all" icon-right="arrow_forward" to="/trips" />
      </div>

      <!-- Loading skeletons -->
      <div v-if="matchStore.loading" class="row no-wrap overflow-hidden q-gutter-md">
        <q-card
          v-for="n in 4" :key="n"
          flat bordered
          style="min-width: 260px; max-width: 260px"
        >
          <q-skeleton height="150px" square />
          <q-card-section class="q-gutter-xs">
            <q-skeleton type="text" width="80%" />
            <q-skeleton type="text" width="60%" />
            <q-skeleton type="text" width="40%" />
          </q-card-section>
        </q-card>
      </div>

      <!-- Empty state -->
      <div v-else-if="matchStore.suggestedTrips.length === 0" class="text-center q-py-xl bg-grey-1 rounded-borders">
        <q-icon name="explore" size="4em" color="grey-4" />
        <div class="text-h6 text-grey-5 q-mt-md">Complete your profile preferences</div>
        <div class="text-body2 text-grey-5 q-mb-md">Tell us your travel style to get personalized suggestions</div>
        <q-btn unelevated rounded color="deep-purple" label="Update Preferences" to="/profile" />
      </div>

      <!-- Horizontal scroll trip cards -->
      <div v-else class="row no-wrap q-gutter-md" style="overflow-x: auto; padding-bottom: 8px">
        <q-card
          v-for="item in matchStore.suggestedTrips"
          :key="item.trip.id"
          flat bordered
          class="cursor-pointer trip-card"
          style="min-width: 260px; max-width: 260px"
          @click="$router.push(`/trips/${item.trip.id}`)"
        >
          <!-- Cover image -->
          <div style="height: 150px; overflow: hidden; position: relative" class="bg-grey-3">
            <img
              v-if="item.trip.cover_image"
              :src="item.trip.cover_image"
              style="width:100%; height:100%; object-fit:cover"
            />
            <div v-else class="absolute-full flex flex-center">
              <q-icon name="landscape" size="3em" color="grey-4" />
            </div>
            <!-- Score badge -->
            <q-badge
              :color="scoreBadgeColor(item.score_label)"
              class="absolute-top-right q-ma-xs q-pa-xs"
              style="font-size: 11px"
            >
              {{ item.score }}% · {{ item.score_label }}
            </q-badge>
          </div>

          <q-card-section class="q-pa-sm">
            <div class="text-weight-bold ellipsis">{{ item.trip.title }}</div>
            <div class="row items-center text-caption text-grey-6 q-mt-xs">
              <q-icon name="place" size="xs" class="q-mr-xs" />
              <span class="ellipsis">{{ item.trip.destination?.name }}</span>
            </div>
            <div class="row items-center text-caption text-grey-6 q-mt-xs">
              <q-icon name="calendar_today" size="xs" class="q-mr-xs" />
              <span>{{ fmtDate(item.trip.start_date) }}</span>
            </div>
            <div class="row items-center justify-between q-mt-sm">
              <div class="row items-center q-gutter-xs">
                <q-avatar size="20px">
                  <img v-if="item.trip.creator?.avatar" :src="item.trip.creator.avatar" />
                  <q-icon v-else name="person" size="xs" />
                </q-avatar>
                <span class="text-caption text-grey-7 ellipsis" style="max-width:90px">{{ item.trip.creator?.name }}</span>
              </div>
              <span class="text-caption text-deep-purple text-weight-bold">
                {{ item.trip.spots_left }} left
              </span>
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Featured destinations -->
    <div>
      <div class="row items-center justify-between q-mb-md">
        <div class="text-h6 text-weight-bold">Browse Destinations</div>
        <q-btn flat color="deep-purple" label="See all" icon-right="arrow_forward" to="/destinations" />
      </div>

      <div v-if="featuredLoading" class="row no-wrap overflow-hidden q-gutter-md">
        <q-card v-for="n in 5" :key="n" flat bordered style="min-width: 180px; max-width: 180px">
          <q-skeleton height="120px" square />
          <q-card-section><q-skeleton type="text" width="70%" /></q-card-section>
        </q-card>
      </div>

      <div v-else class="row no-wrap q-gutter-md" style="overflow-x: auto; padding-bottom: 8px">
        <q-card
          v-for="dest in featuredDestinations"
          :key="dest.id"
          flat bordered
          class="cursor-pointer dest-card"
          style="min-width: 180px; max-width: 180px"
          @click="$router.push(`/destinations/${dest.slug}`)"
        >
          <div style="height: 120px; overflow: hidden" class="bg-grey-3">
            <img
              v-if="dest.cover_image"
              :src="dest.cover_image"
              style="width:100%; height:100%; object-fit:cover"
            />
            <div v-else class="absolute-full flex flex-center">
              <q-icon name="landscape" size="2em" color="grey-4" />
            </div>
          </div>
          <q-card-section class="q-pa-sm">
            <div class="text-weight-bold text-body2 ellipsis">{{ dest.name }}</div>
            <div class="text-caption text-grey-6">{{ dest.province }}</div>
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from 'src/stores/authStore'
import { useTripStore } from 'src/stores/tripStore'
import { useMatchStore } from 'src/stores/matchStore'
import { api } from 'src/boot/axios'

const router = useRouter()
const authStore = useAuthStore()
const tripStore = useTripStore()
const matchStore = useMatchStore()

const searchQuery = ref('')
const featuredDestinations = ref([])
const featuredLoading = ref(false)

const stats = computed(() => {
  const created = tripStore.myTrips.created ?? []
  const joined  = tripStore.myTrips.joined ?? []
  const allTrips = [...created, ...joined]

  const activeCount = created.filter((t) => ['planning', 'active'].includes(t.status)).length
  const uniqueDests  = new Set(allTrips.map((t) => t.destination?.id).filter(Boolean)).size

  return [
    { label: 'Active Trips',         icon: 'hiking',          value: activeCount },
    { label: 'Destinations Explored', icon: 'travel_explore',  value: uniqueDests },
    { label: 'Trips Joined',          icon: 'group',           value: joined.length },
  ]
})

onMounted(async () => {
  // Parallel fetches
  await Promise.all([
    tripStore.fetchMyTrips(),
    matchStore.fetchSuggestedTrips(),
    loadFeaturedDestinations(),
  ])
})

const loadFeaturedDestinations = async () => {
  featuredLoading.value = true
  try {
    const r = await api.get('/api/v1/destinations', { params: { featured: 1, per_page: 10 } })
    featuredDestinations.value = r.data.data
  } finally {
    featuredLoading.value = false
  }
}

const goSearch = () => {
  if (searchQuery.value.trim()) {
    router.push(`/trips?search=${encodeURIComponent(searchQuery.value.trim())}`)
  } else {
    router.push('/trips')
  }
}

const scoreBadgeColor = (label) => ({
  'Great match':    'positive',
  'Good match':     'blue',
  'Worth checking': 'grey-6',
  'Suggested':      'grey-5',
}[label] ?? 'grey-5')

const fmtDate = (d) => {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PK', { day: 'numeric', month: 'short' })
}
</script>

<style scoped>
.trip-card { transition: transform 0.15s, box-shadow 0.15s; }
.trip-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.1); }
.dest-card { transition: transform 0.15s; }
.dest-card:hover { transform: translateY(-2px); }
.search-bar :deep(.q-field__control) { background: white; }
</style>
