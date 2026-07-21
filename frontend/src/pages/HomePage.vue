<template>
  <q-page class="home-page">
    <div class="home-shell">
      <!-- Hero -->
      <section class="hero">
        <div class="hero-copy">
          <h1 class="hero-title">
            {{ greeting }}, {{ authStore.user?.name?.split(' ')[0] }} 👋
          </h1>
          <p class="hero-sub">Where are we heading next?</p>
        </div>

        <q-input
          v-model="searchQuery"
          outlined
          rounded
          dense
          placeholder="Search trips, destinations..."
          class="hero-search"
          @keyup.enter="goSearch"
        >
          <template v-slot:prepend><q-icon name="search" /></template>
          <template v-slot:append>
            <q-btn round unelevated dense icon="arrow_forward" color="primary" @click="goSearch" />
          </template>
        </q-input>
      </section>

      <!-- Next departure -->
      <section v-if="nextTrip" class="next-trip" @click="$router.push(`/trips/${nextTrip.id}`)">
        <div class="next-countdown">
          <span class="next-num">{{ daysToNext }}</span>
          <span class="next-unit">{{ daysToNext === 1 ? 'day' : 'days' }}</span>
        </div>
        <div class="next-main">
          <div class="next-label">Next departure</div>
          <div class="next-title">{{ nextTrip.title }}</div>
          <div class="next-meta">
            <q-icon name="place" size="13px" />{{ nextTrip.destination?.name }}
            <span class="dot-sep">·</span>{{ fmtLong(nextTrip.start_date) }}
          </div>
        </div>
        <q-icon name="chevron_right" size="22px" class="next-arrow" />
      </section>

      <!-- Quick stats -->
      <section class="stat-row">
        <button
          v-for="stat in stats"
          :key="stat.label"
          type="button"
          class="stat"
          @click="$router.push(stat.to)"
        >
          <q-icon :name="stat.icon" size="19px" />
          <div class="stat-text">
            <span class="stat-value">{{ stat.value }}</span>
            <span class="stat-label">{{ stat.label }}</span>
          </div>
        </button>
      </section>

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

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Good morning'
  if (h < 18) return 'Good afternoon'
  return 'Good evening'
})

const created = computed(() => tripStore.myTrips.created ?? [])
const joined  = computed(() => tripStore.myTrips.joined ?? [])

const startOfToday = () => new Date(new Date().toDateString())

// Trips that haven't departed yet, soonest first.
const upcoming = computed(() =>
  [...created.value, ...joined.value]
    .filter((t) => t.start_date && new Date(t.start_date) >= startOfToday())
    .filter((t) => ['planning', 'active'].includes(t.status))
    .sort((a, b) => new Date(a.start_date) - new Date(b.start_date)),
)

const nextTrip = computed(() => upcoming.value[0] ?? null)

const daysToNext = computed(() => {
  if (!nextTrip.value) return 0
  const diff = Math.round((new Date(nextTrip.value.start_date) - startOfToday()) / 86400000)
  return Math.max(0, diff)
})

// Only counts we can actually stand behind — the old cards claimed
// "Destinations Explored" for trips that hadn't happened yet, and labelled
// trips-you-created as "Active Trips".
const stats = computed(() => [
  { label: 'Upcoming', icon: 'luggage', value: upcoming.value.length, to: '/my-trips' },
  { label: 'Hosting',  icon: 'flag',    value: created.value.length,  to: '/my-trips' },
  { label: 'Joined',   icon: 'group',   value: joined.value.length,   to: '/my-trips' },
])

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

const fmtLong = (d) => {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PK', {
    weekday: 'short', day: 'numeric', month: 'long',
  })
}
</script>

<style scoped>
.home-page { background: #faf8fc; padding: 20px 16px 64px; }
.home-shell { max-width: 1080px; margin: 0 auto; }

/* ── Hero ───────────────────────────────────────────── */
.hero {
  position: relative;
  overflow: hidden;
  border-radius: 18px;
  padding: 28px 28px 24px;
  margin-bottom: 16px;
  background: linear-gradient(135deg, #5c1a5c 0%, #4a148c 55%, #6a1b9a 100%);
  color: #fff;
}
.hero::after {
  content: '';
  position: absolute;
  right: -60px;
  top: -70px;
  width: 240px;
  height: 240px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.07);
}
.hero-copy { position: relative; z-index: 1; }
.hero-title {
  margin: 0;
  font-size: 28px;
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1.2;
}
.hero-sub { margin: 6px 0 18px; font-size: 14px; opacity: 0.82; }
.hero-search { position: relative; z-index: 1; max-width: 560px; }
.hero-search :deep(.q-field__control) { background: #fff; border-radius: 999px; padding-right: 6px; }
.hero-search :deep(.q-field__control:before) { border: none; }

/* ── Next departure ─────────────────────────────────── */
.next-trip {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px 18px;
  margin-bottom: 16px;
  border-radius: 14px;
  background: #fff;
  border: 1px solid #ece6f0;
  border-left: 3px solid var(--q-primary);
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.next-trip:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(43, 27, 51, 0.09); }
.next-countdown {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-width: 58px;
  height: 58px;
  border-radius: 12px;
  background: linear-gradient(135deg, #f3e9f8, #ede7f6);
  flex-shrink: 0;
}
.next-num { font-size: 21px; font-weight: 700; color: #4a148c; line-height: 1; }
.next-unit { font-size: 10px; font-weight: 600; color: #8a6a9a; letter-spacing: 0.04em; }
.next-main { flex: 1; min-width: 0; }
.next-label {
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: #9b8aa5;
}
.next-title {
  font-size: 15px;
  font-weight: 600;
  color: #2b1b33;
  margin-top: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.next-meta {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12.5px;
  color: #7a6a82;
  margin-top: 3px;
}
.dot-sep { margin: 0 3px; }
.next-arrow { color: #c3b3cc; flex-shrink: 0; }

/* ── Stats ──────────────────────────────────────────── */
.stat-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-bottom: 30px;
}
.stat {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 13px;
  border: 1px solid #ece6f0;
  background: #fff;
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}
.stat:hover { transform: translateY(-2px); border-color: #d9c7e4; box-shadow: 0 6px 18px rgba(43, 27, 51, 0.08); }
.stat .q-icon { color: var(--q-primary); }
.stat-text { display: flex; flex-direction: column; line-height: 1.15; }
.stat-value { font-size: 21px; font-weight: 700; color: #2b1b33; }
.stat-label { font-size: 12px; color: #8a7a92; margin-top: 2px; }

.trip-card { transition: transform 0.15s, box-shadow 0.15s; }
.trip-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.1); }
.dest-card { transition: transform 0.15s; }
.dest-card:hover { transform: translateY(-2px); }

@media (max-width: 599px) {
  .home-page { padding: 14px 12px 48px; }
  .hero { padding: 22px 20px 20px; border-radius: 14px; }
  .hero-title { font-size: 22px; }
  .stat-row { gap: 8px; }
  .stat { flex-direction: column; align-items: flex-start; gap: 6px; padding: 12px; }
}
</style>
