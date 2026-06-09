<template>
  <q-page padding>
    <div class="row items-center q-mb-lg">
      <div class="text-h4 text-weight-bold">Explore Pakistan</div>
    </div>

    <!-- Filters Input Area -->
    <div class="row q-col-gutter-md q-mb-lg">
      <div class="col-12 col-md-4">
        <q-input
          v-model="search"
          outlined
          dense
          debounce="400"
          placeholder="Search destinations..."
          clearable
          @update:model-value="onSearchUpdate"
        >
          <template v-slot:append>
            <q-icon name="search" />
          </template>
        </q-input>
      </div>
      <div class="col-12 col-sm-6 col-md-4">
        <q-select
          v-model="province"
          :options="provinceOptions"
          outlined
          dense
          label="Province"
          clearable
          @update:model-value="val => destinationStore.setFilter('province', val)"
        />
      </div>
      <div class="col-12 col-sm-6 col-md-4">
        <q-select
          v-model="type"
          :options="typeOptions"
          outlined
          dense
          label="Type"
          clearable
          @update:model-value="val => destinationStore.setFilter('type', val)"
        />
      </div>
    </div>
    
    <div class="row q-mb-md">
       <q-btn flat color="primary" label="Clear Filters" @click="clearFilters" v-if="hasFilters" />
    </div>

    <!-- Loading Skeletons -->
    <div v-if="destinationStore.loading" class="row q-col-gutter-md">
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

    <!-- Empty State -->
    <div v-else-if="destinationStore.destinations.length === 0" class="text-center q-py-xl">
      <q-icon name="travel_explore" size="64px" color="grey-5" />
      <div class="text-h6 text-grey-7 q-mt-md">No destinations found</div>
      <div class="text-grey-6">Try adjusting your filters</div>
    </div>

    <!-- Grid -->
    <div v-else class="row q-col-gutter-md">
      <div
        v-for="dest in destinationStore.destinations"
        :key="dest.id"
        class="col-12 col-sm-6 col-md-4"
      >
        <q-card class="cursor-pointer q-hoverable card-hover" @click="goToDetails(dest.slug)">
          <q-img
            :src="dest.cover_image || 'https://via.placeholder.com/600x400?text=No+Image'"
            ratio="1.7778"
            class="rounded-borders bg-grey-3"
          >
            <div class="absolute-top-right bg-transparent q-pa-sm">
              <q-badge color="primary" class="q-mr-sm shadow-1 q-pa-sm text-subtitle2">{{ dest.province }}</q-badge>
              <q-badge color="secondary" class="shadow-1 q-pa-sm text-subtitle2 capitalize">{{ dest.type }}</q-badge>
            </div>
          </q-img>

          <q-card-section>
            <div class="text-h6 text-weight-bold ellipsis" :title="dest.name">{{ dest.name }}</div>
            <div class="text-caption text-grey-8 ellipsis-2-lines q-mt-xs" :title="dest.description" style="min-height: 40px">
              {{ dest.description }}
            </div>
          </q-card-section>

          <q-card-section class="q-pt-none row items-center justify-between">
            <div class="row items-center text-grey-8">
              <q-icon name="calendar_today" size="sm" class="q-mr-xs" />
              <span class="text-caption text-weight-medium">{{ dest.best_season }}</span>
            </div>
            <q-btn flat color="primary" label="View Details" :to="`/destinations/${dest.slug}`" />
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="!destinationStore.loading && destinationStore.pagination.lastPage > 1" class="row justify-center q-mt-xl q-mb-lg">
      <q-pagination
        v-model="currentPage"
        :max="destinationStore.pagination.lastPage"
        color="primary"
        @update:model-value="onPageChange"
        boundary-links
      />
    </div>
  </q-page>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useDestinationStore } from 'src/stores/destinationStore'

const router = useRouter()
const destinationStore = useDestinationStore()

const search = ref('')
const province = ref(null)
const type = ref(null)
const currentPage = ref(1)

const provinceOptions = ['Punjab', 'Sindh', 'KPK', 'Balochistan', 'Gilgit-Baltistan', 'AJK', 'Islamabad']
const typeOptions = ['mountains', 'historical', 'beach', 'desert', 'cultural', 'lakes', 'forests']

const hasFilters = computed(() => !!search.value || !!province.value || !!type.value)

onMounted(() => {
  // Sync local ref logic with current store logic
  search.value = destinationStore.filters.search
  province.value = destinationStore.filters.province
  type.value = destinationStore.filters.type
  currentPage.value = destinationStore.pagination.page || 1

  // Start initialization fetch if empty or we want to guarantee freshness 
  destinationStore.fetchDestinations(currentPage.value)
})

watch(() => destinationStore.pagination.page, (newVal) => {
  currentPage.value = newVal
})

const onSearchUpdate = (val) => {
  destinationStore.setFilter('search', val)
}

const clearFilters = () => {
  search.value = ''
  province.value = null
  type.value = null
  destinationStore.clearFilters()
}

const onPageChange = (val) => {
  destinationStore.fetchDestinations(val)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const goToDetails = (slug) => {
  router.push(`/destinations/${slug}`)
}
</script>

<style scoped>
.capitalize {
  text-transform: capitalize;
}
.card-hover {
  transition: transform 0.2s, box-shadow 0.2s;
}
.card-hover:hover {
  transform: translateY(-4px);
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
</style>
