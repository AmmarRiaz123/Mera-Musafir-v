<template>
  <q-page padding>
    <div class="row items-center justify-between q-mb-lg">
      <div>
      <span class="page-eyebrow"><q-icon name="hiking" size="12px" />Trips</span>
      <h1 class="page-title">Explore Trips</h1>
    </div>
      <q-btn color="primary" icon="add" label="Create Trip" to="/trips/create" unelevated rounded />
    </div>

    <!-- Filters -->
    <div class="tp-filters">
      <q-input
        v-model="search"
        class="tp-search" outlined dense rounded clearable
        debounce="350" placeholder="Search trips by name…"
        @update:model-value="val => tripStore.setFilter('search', val || '')"
      >
        <template #prepend><q-icon name="search" color="deep-purple" /></template>
      </q-input>

      <div class="tp-chip-rows">
        <div class="tp-chip-row">
          <span class="tp-chip-label">Type</span>
          <button
            type="button" class="tp-chip" :class="{ 'tp-chip--active': !filterType }"
            @click="setType(null)"
          >All</button>
          <button
            v-for="t in typeOptions" :key="t"
            type="button" class="tp-chip capitalize" :class="{ 'tp-chip--active': filterType === t }"
            @click="setType(t)"
          >{{ t }}</button>
        </div>

        <div class="tp-chip-row">
          <span class="tp-chip-label">Access</span>
          <button
            v-for="opt in visibilityOptions" :key="opt.value ?? 'all'"
            type="button" class="tp-chip"
            :class="{ 'tp-chip--active': filterVisibility === opt.value, 'tp-chip--women': opt.value === 'women_only' }"
            @click="setVisibility(opt.value)"
          >
            <q-icon v-if="opt.icon" :name="opt.icon" size="13px" />{{ opt.label }}
          </button>

          <button v-if="hasFilters" type="button" class="tp-chip tp-chip--clear" @click="clearFilters">
            <q-icon name="close" size="13px" />Clear
          </button>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="tripStore.loading" class="row q-col-gutter-md">
      <div v-for="n in 6" :key="n" class="col-12 col-sm-6 col-md-4">
        <q-card flat bordered>
          <q-skeleton height="200px" square />
          <q-card-section>
            <q-skeleton type="text" class="text-h6" />
            <q-skeleton type="text" />
            <q-skeleton type="text" width="50%" />
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="tripStore.trips.length === 0" class="text-center q-py-xl">
      <q-icon name="hiking" size="64px" color="grey-5" />
      <div class="text-h6 text-grey-7 q-mt-md">No trips found</div>
      <div class="text-grey-6 q-mb-lg">Try adjusting your filters or create the first trip!</div>
      <q-btn color="primary" label="Create a Trip" to="/trips/create" unelevated rounded />
    </div>

    <!-- Trip cards -->
    <div v-else class="row q-col-gutter-md">
      <div
        v-for="trip in tripStore.trips"
        :key="trip.id"
        class="col-12 col-sm-6 col-md-4"
      >
        <q-card class="cursor-pointer q-hoverable card-hover" @click="$router.push(`/trips/${trip.id}`)">
          <q-img
            :src="trip.cover_image || 'https://via.placeholder.com/600x400?text=No+Image'"
            ratio="1.7778"
            class="rounded-borders bg-grey-3"
          >
            <div class="absolute-top-right q-pa-sm row q-gutter-xs">
              <q-badge v-if="trip.visibility === 'women_only'" color="pink-6" class="shadow-1 q-pa-sm text-subtitle2">
                <q-icon name="female" size="xs" class="q-mr-xs" />Women Only
              </q-badge>
              <q-badge color="primary" class="shadow-1 q-pa-sm text-subtitle2 capitalize">{{ trip.type }}</q-badge>
            </div>
            <div v-if="trip.is_full" class="absolute-bottom bg-dark-transparent text-white text-center q-pa-xs text-caption">
              TRIP FULL
            </div>
          </q-img>

          <q-card-section>
            <div class="text-h6 text-weight-bold ellipsis" :title="trip.title">{{ trip.title }}</div>
            <div class="row items-center text-grey-7 q-mt-xs">
              <q-icon name="place" size="xs" class="q-mr-xs" />
              <span class="text-caption ellipsis">{{ trip.destination?.name || '—' }}</span>
            </div>
          </q-card-section>

          <q-card-section class="q-pt-none">
            <div class="row items-center justify-between text-caption text-grey-8 q-mb-sm">
              <div class="row items-center">
                <q-icon name="calendar_today" size="xs" class="q-mr-xs" />
                {{ formatDate(trip.start_date) }} – {{ formatDate(trip.end_date) }}
              </div>
              <div class="row items-center">
                <q-icon name="group" size="xs" class="q-mr-xs" />
                {{ trip.current_count }}/{{ trip.max_travelers }}
              </div>
            </div>

            <q-linear-progress
              :value="trip.current_count / trip.max_travelers"
              :color="trip.is_full ? 'negative' : 'primary'"
              rounded
              class="q-mb-sm"
              size="6px"
            />

            <div v-if="trip.budget_min || trip.budget_max" class="text-caption text-grey-7">
              <q-icon name="payments" size="xs" class="q-mr-xs" />
              PKR {{ formatBudget(trip.budget_min) }}
              <span v-if="trip.budget_max"> – {{ formatBudget(trip.budget_max) }}</span>
            </div>
          </q-card-section>

          <q-card-section class="q-pt-none row items-center justify-between">
            <div class="row items-center text-grey-7" v-if="trip.creator">
              <q-avatar size="24px" class="q-mr-xs">
                <img v-if="trip.creator.avatar" :src="trip.creator.avatar" />
                <q-icon v-else name="person" size="xs" />
              </q-avatar>
              <span class="text-caption">{{ trip.creator.name }}</span>
              <q-icon v-if="trip.creator.is_verified" name="verified" color="deep-purple" size="12px" class="q-ml-xs">
                <q-tooltip>Verified User</q-tooltip>
              </q-icon>
            </div>
            <q-btn flat color="primary" label="View" :to="`/trips/${trip.id}`" dense />
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="!tripStore.loading && tripStore.pagination.lastPage > 1" class="row justify-center q-mt-xl q-mb-lg">
      <q-pagination
        v-model="currentPage"
        :max="tripStore.pagination.lastPage"
        color="primary"
        @update:model-value="onPageChange"
        boundary-links
      />
    </div>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useTripStore } from 'src/stores/tripStore'

const router = useRouter() // eslint-disable-line no-unused-vars
const tripStore = useTripStore()

const search = ref('')
const filterType = ref(null)
const filterVisibility = ref(null)
const currentPage = ref(1)

const typeOptions = ['adventure', 'cultural', 'budget', 'luxury', 'backpacking']
// null = no filter. Only browsable visibilities belong here — invite-only trips
// never appear in browse, so they're not an option.
const visibilityOptions = [
  { label: 'Everyone', value: null },
  { label: 'Public', value: 'public', icon: 'public' },
  { label: 'Women only', value: 'women_only', icon: 'female' },
]

// A chip toggles its filter off if it's already active.
const setType = (t) => {
  filterType.value = filterType.value === t ? null : t
  tripStore.setFilter('type', filterType.value || '')
}
const setVisibility = (v) => {
  filterVisibility.value = v
  tripStore.setFilter('visibility', v || '')
}

const hasFilters = computed(() => !!search.value || !!filterType.value || !!filterVisibility.value)

onMounted(() => {
  search.value = tripStore.filters.search
  currentPage.value = tripStore.pagination.page || 1
  tripStore.fetchTrips(currentPage.value)
})

watch(() => tripStore.pagination.page, (val) => {
  currentPage.value = val
})

const onPageChange = (val) => {
  tripStore.fetchTrips(val)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const clearFilters = () => {
  search.value = ''
  filterType.value = null
  filterVisibility.value = null
  tripStore.clearFilters()
}

const formatDate = (dateStr) => {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-PK', { day: 'numeric', month: 'short', year: 'numeric' })
}

const formatBudget = (amount) => {
  if (!amount) return '—'
  return Number(amount).toLocaleString()
}
</script>

<style scoped>
.capitalize { text-transform: capitalize; }
.card-hover { transition: transform 0.2s, box-shadow 0.2s; }
.card-hover:hover { transform: translateY(-4px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
.bg-dark-transparent { background: rgba(0,0,0,0.55); }

/* ── Filter bar ─────────────────────────────────────── */
.tp-filters {
  display: flex; flex-direction: column; gap: 14px; margin-bottom: 22px;
}
.tp-search {
  max-width: 460px;
}
.tp-search :deep(.q-field__control) {
  background: #fff; border-radius: 999px;
  box-shadow: 0 1px 3px rgba(43, 27, 51, 0.05);
}
.tp-search :deep(.q-field__control):hover:before { border-color: #c9b3d6; }

.tp-chip-rows { display: flex; flex-direction: column; gap: 10px; }
.tp-chip-row { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
.tp-chip-label {
  font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
  color: #a99bb2; margin-right: 4px; min-width: 44px;
}
.tp-chip {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 6px 13px; border-radius: 999px; cursor: pointer;
  border: 1px solid #e5dced; background: #fff;
  font-size: 12.5px; font-weight: 500; color: #6b5a75;
  transition: border-color 0.14s ease, background 0.14s ease, color 0.14s ease;
}
.tp-chip:hover { border-color: #c9b3d6; background: #fcfafd; }
.tp-chip--active {
  background: linear-gradient(135deg, #7b1fa2, #4a148c);
  border-color: transparent; color: #fff; font-weight: 600;
}
.tp-chip--women.tp-chip--active {
  background: linear-gradient(135deg, #d81b60, #ad1457);
}
.tp-chip--clear { color: #9b8aa5; border-style: dashed; }
.tp-chip--clear:hover { color: #c62828; border-color: #e0b9bd; }
</style>
