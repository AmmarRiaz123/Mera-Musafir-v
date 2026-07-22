<template>
  <q-page padding>
    <div class="row items-center justify-between q-mb-lg">
      <div>
        <div>
      <span class="page-eyebrow"><q-icon name="card_travel" size="12px" />Discover</span>
      <h1 class="page-title">Travel Packages</h1>
    </div>
        <div class="text-caption text-grey-6">Curated trips by verified agencies across Pakistan</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="row q-col-gutter-md q-mb-lg">
      <div class="col-12 col-md-3">
        <q-input
          v-model="filters.search"
          outlined dense debounce="400"
          placeholder="Search packages..."
          clearable
          @update:model-value="load(1)"
        >
          <template v-slot:append><q-icon name="search" /></template>
        </q-input>
      </div>
      <div class="col-6 col-md-2">
        <q-select
          v-model="filters.type"
          :options="typeOptions"
          option-value="value"
          option-label="label"
          emit-value map-options
          outlined dense
          label="Type"
          clearable
          @update:model-value="load(1)"
        />
      </div>
      <div class="col-6 col-md-2">
        <q-input
          v-model.number="filters.max_price"
          outlined dense type="number"
          label="Max Price (PKR)"
          clearable
          debounce="600"
          @update:model-value="load(1)"
        />
      </div>
      <div class="col-6 col-md-2">
        <q-input v-model="filters.start_date" label="From Date" outlined dense>
          <template v-slot:append>
            <q-icon name="event" class="cursor-pointer">
              <q-popup-proxy>
                <q-date v-model="filters.start_date" mask="YYYY-MM-DD" minimal @update:model-value="load(1)">
                  <div class="row items-center justify-end">
                    <q-btn v-close-popup label="Close" flat color="primary" />
                  </div>
                </q-date>
              </q-popup-proxy>
            </q-icon>
          </template>
        </q-input>
      </div>
      <div class="col-6 col-md-2 flex items-center">
        <q-btn flat color="grey-7" label="Clear" icon="clear_all" @click="clearFilters" />
      </div>
    </div>

    <!-- Skeleton -->
    <div v-if="store.packagesLoading" class="row q-col-gutter-md">
      <div v-for="n in 6" :key="n" class="col-12 col-sm-6 col-md-4">
        <q-card flat bordered>
          <q-skeleton height="180px" square />
          <q-card-section>
            <q-skeleton type="text" class="text-h6" />
            <q-skeleton type="text" />
            <q-skeleton type="text" width="50%" />
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Empty -->
    <div v-else-if="store.packages.length === 0" class="text-center q-py-xl">
      <q-icon name="card_travel" size="64px" color="grey-5" />
      <div class="text-h6 text-grey-7 q-mt-md">No packages found</div>
      <div class="text-grey-6">Try adjusting your filters</div>
    </div>

    <!-- Package cards -->
    <div v-else class="row q-col-gutter-md">
      <div v-for="pkg in store.packages" :key="pkg.id" class="col-12 col-sm-6 col-md-4">
        <q-card class="cursor-pointer card-hover" flat bordered @click="$router.push(`/packages/${pkg.slug}`)">
          <q-img
            :src="pkg.cover_image || 'https://via.placeholder.com/600x400?text=Package'"
            ratio="1.7778"
            class="bg-grey-3"
          >
            <div class="absolute-top-right q-pa-sm row q-gutter-xs">
              <q-badge color="deep-purple" class="shadow-1 q-pa-xs text-caption capitalize">
                {{ pkg.type?.replace('_', ' ') }}
              </q-badge>
            </div>
            <div v-if="pkg.is_full" class="absolute-bottom bg-negative text-white text-center text-caption q-pa-xs">
              FULLY BOOKED
            </div>
          </q-img>

          <q-card-section>
            <div class="text-subtitle1 text-weight-bold ellipsis">{{ pkg.title }}</div>
            <div class="row items-center text-caption text-grey-7 q-mt-xs q-gutter-xs">
              <q-icon name="business" size="xs" />
              <span>{{ pkg.agency?.business_name }}</span>
              <q-icon v-if="pkg.agency?.is_verified" name="verified" color="primary" size="10px" />
            </div>
            <div class="row items-center text-caption text-grey-6 q-mt-xs">
              <q-icon name="place" size="xs" class="q-mr-xs" />
              {{ pkg.destination?.name }}
            </div>
          </q-card-section>

          <q-card-section class="q-pt-none">
            <div class="row items-center justify-between q-mb-sm">
              <div class="text-h6 text-weight-bold text-deep-purple">{{ pkg.formatted_price }}</div>
              <div class="text-caption text-grey-6">per person</div>
            </div>

            <div class="row items-center justify-between text-caption text-grey-7 q-mb-sm">
              <div class="row items-center">
                <q-icon name="calendar_today" size="xs" class="q-mr-xs" />
                {{ fmtDate(pkg.start_date) }}
              </div>
              <div>{{ pkg.duration_days }}D</div>
              <div class="row items-center" :class="pkg.spots_left < 5 ? 'text-negative' : ''">
                <q-icon name="event_seat" size="xs" class="q-mr-xs" />
                {{ pkg.spots_left }} left
              </div>
            </div>

            <q-linear-progress
              :value="pkg.booked_count / pkg.max_capacity"
              :color="pkg.is_full ? 'negative' : 'deep-purple'"
              rounded size="5px"
            />
          </q-card-section>

          <q-card-actions class="q-pt-none">
            <q-space />
            <q-btn flat color="deep-purple" label="View" dense :to="`/packages/${pkg.slug}`" />
          </q-card-actions>
        </q-card>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="!store.packagesLoading && store.packagesPagination.lastPage > 1" class="row justify-center q-mt-xl">
      <q-pagination
        v-model="currentPage"
        :max="store.packagesPagination.lastPage"
        color="deep-purple"
        @update:model-value="load"
        boundary-links
      />
    </div>
  </q-page>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useAgencyStore } from 'src/stores/agencyStore'

const store = useAgencyStore()
const currentPage = ref(1)
const filters = reactive({ search: '', type: null, max_price: null, start_date: '' })

const typeOptions = [
  { label: 'Day Trip', value: 'day_trip' },
  { label: 'Weekend', value: 'weekend' },
  { label: 'Extended', value: 'extended' },
  { label: 'Custom', value: 'custom' },
]

onMounted(() => load(1))

const load = (page = 1) => {
  currentPage.value = page
  const f = {}
  if (filters.search)     f.search     = filters.search
  if (filters.type)       f.type       = filters.type
  if (filters.max_price)  f.max_price  = filters.max_price
  if (filters.start_date) f.start_date = filters.start_date
  store.fetchPackages(page, f)
}

const clearFilters = () => {
  Object.assign(filters, { search: '', type: null, max_price: null, start_date: '' })
  load(1)
}

const fmtDate = (d) => d ? new Date(d + 'T00:00:00').toLocaleDateString('en-PK', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
</script>

<style scoped>
.capitalize { text-transform: capitalize; }
.card-hover { transition: transform 0.2s, box-shadow 0.2s; }
.card-hover:hover { transform: translateY(-4px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
</style>
