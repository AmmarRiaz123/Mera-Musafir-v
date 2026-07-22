<template>
  <q-page padding>
    <div class="row items-center justify-between q-mb-lg">
      <div>
        <div>
      <span class="page-eyebrow"><q-icon name="business" size="12px" />Discover</span>
      <h1 class="page-title">Travel Agencies</h1>
    </div>
        <div class="text-caption text-grey-6">Discover verified agencies offering curated Pakistan trips</div>
      </div>
    </div>

    <!-- Search -->
    <div class="row q-mb-lg">
      <div class="col-12 col-md-5">
        <q-input
          v-model="search"
          outlined
          dense
          debounce="400"
          placeholder="Search agencies..."
          clearable
          @update:model-value="onSearch"
        >
          <template v-slot:append><q-icon name="search" /></template>
        </q-input>
      </div>
    </div>

    <!-- Skeleton loading -->
    <div v-if="store.loading" class="row q-col-gutter-md">
      <div v-for="n in 6" :key="n" class="col-12 col-sm-6 col-md-4">
        <q-card flat bordered>
          <q-skeleton height="120px" square />
          <q-card-section>
            <q-skeleton type="text" class="text-h6" />
            <q-skeleton type="text" width="60%" />
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Empty -->
    <div v-else-if="store.agencies.length === 0" class="text-center q-py-xl">
      <q-icon name="business" size="64px" color="grey-5" />
      <div class="text-h6 text-grey-7 q-mt-md">No agencies found</div>
    </div>

    <!-- Agency cards -->
    <div v-else class="row q-col-gutter-md">
      <div v-for="agency in store.agencies" :key="agency.id" class="col-12 col-sm-6 col-md-4">
        <q-card class="cursor-pointer card-hover" flat bordered @click="$router.push(`/agencies/${agency.slug}`)">
          <!-- Cover / logo area -->
          <div class="relative-position bg-grey-2" style="height: 100px; overflow: hidden">
            <img
              v-if="agency.cover_image"
              :src="agency.cover_image"
              style="width:100%; height:100%; object-fit:cover"
            />
            <div v-else class="absolute-full flex flex-center">
              <q-icon name="business" size="48px" color="grey-4" />
            </div>
            <q-avatar
              v-if="agency.logo"
              size="48px"
              class="absolute"
              style="bottom:-20px; left:16px; border: 3px solid white"
            >
              <img :src="agency.logo" />
            </q-avatar>
          </div>

          <q-card-section :class="agency.logo ? 'q-pt-xl' : ''">
            <div class="row items-center q-gutter-xs q-mb-xs">
              <span class="text-subtitle1 text-weight-bold">{{ agency.business_name }}</span>
              <q-icon v-if="agency.is_verified" name="verified" color="primary" size="16px">
                <q-tooltip>Verified Agency</q-tooltip>
              </q-icon>
            </div>

            <div class="row items-center q-gutter-xs q-mb-sm">
              <q-badge
                :color="tierColor(agency.tier)"
                :label="agency.tier.toUpperCase()"
                class="text-caption"
              />
            </div>

            <div class="row items-center q-gutter-md text-caption text-grey-7">
              <div class="row items-center q-gutter-xs">
                <q-icon name="group" size="xs" />
                <span>{{ agency.follower_count }} followers</span>
              </div>
              <div class="row items-center q-gutter-xs">
                <q-icon name="card_travel" size="xs" />
                <span>{{ agency.packages_count ?? 0 }} packages</span>
              </div>
            </div>
          </q-card-section>

          <q-card-actions class="q-pt-none">
            <q-space />
            <q-btn flat color="primary" label="View Profile" :to="`/agencies/${agency.slug}`" dense />
          </q-card-actions>
        </q-card>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="!store.loading && store.pagination.lastPage > 1" class="row justify-center q-mt-xl">
      <q-pagination
        v-model="currentPage"
        :max="store.pagination.lastPage"
        color="primary"
        @update:model-value="load"
        boundary-links
      />
    </div>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAgencyStore } from 'src/stores/agencyStore'

const store = useAgencyStore()
const search = ref('')
const currentPage = ref(1)

onMounted(() => load(1))

const load = (page = currentPage.value) => {
  currentPage.value = page
  store.fetchAgencies(page, search.value)
}

const onSearch = () => load(1)

const tierColor = (tier) => ({ basic: 'grey-6', pro: 'blue-7', premium: 'deep-purple' }[tier] ?? 'grey')
</script>

<style scoped>
.card-hover { transition: transform 0.2s, box-shadow 0.2s; }
.card-hover:hover { transform: translateY(-4px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
</style>
