<template>
  <q-page>
    <!-- Loading -->
    <div v-if="store.loading" class="flex flex-center q-pa-xl">
      <q-spinner-dots color="deep-purple" size="3em" />
    </div>

    <template v-else-if="store.currentAgency">
      <!-- Cover + header -->
      <div class="relative-position" style="height: 200px; background: #ede7f6; overflow: hidden">
        <img
          v-if="agency.cover_image"
          :src="agency.cover_image"
          style="width:100%; height:100%; object-fit:cover"
        />
        <div v-else class="absolute-full flex flex-center">
          <q-icon name="landscape" size="80px" color="deep-purple-2" />
        </div>
      </div>

      <div class="q-px-lg" style="margin-top: -40px">
        <div class="row items-end justify-between q-mb-md">
          <div class="row items-end q-gutter-md">
            <!-- Logo -->
            <q-avatar size="80px" color="white" style="border: 4px solid white; box-shadow: 0 2px 8px rgba(0,0,0,.15)">
              <img v-if="agency.logo" :src="agency.logo" />
              <q-icon v-else name="business" size="40px" color="deep-purple" />
            </q-avatar>

            <div class="q-pb-xs">
              <div class="row items-center q-gutter-xs">
                <span class="text-h5 text-weight-bold">{{ agency.business_name }}</span>
                <q-icon v-if="agency.is_verified" name="verified" color="primary" size="22px">
                  <q-tooltip>Verified Agency</q-tooltip>
                </q-icon>
              </div>
              <div class="row items-center q-gutter-xs q-mt-xs">
                <q-badge :color="tierColor(agency.tier)" :label="agency.tier.toUpperCase()" />
                <span class="text-caption text-grey-6">{{ agency.follower_count }} followers</span>
                <span class="text-caption text-grey-6">·</span>
                <span class="text-caption text-grey-6">{{ agency.packages_count ?? 0 }} packages</span>
              </div>
            </div>
          </div>

          <!-- Action buttons -->
          <div class="row q-gutter-sm q-mt-md">
            <q-btn
              v-if="isOwner"
              outline
              color="deep-purple"
              icon="dashboard"
              label="Dashboard"
              :to="`/agencies/${agency.slug}/dashboard`"
            />
            <q-btn
              v-if="!isOwner && authStore.isLoggedIn"
              :unelevated="agency.is_following"
              :outline="!agency.is_following"
              :color="agency.is_following ? 'grey' : 'deep-purple'"
              :icon="agency.is_following ? 'person_remove' : 'person_add'"
              :label="agency.is_following ? 'Following' : 'Follow'"
              :loading="followLoading"
              @click="toggleFollow"
            />
          </div>
        </div>

        <!-- Description -->
        <p v-if="agency.description" class="text-body2 text-grey-8 q-mt-md">{{ agency.description }}</p>

        <!-- Contact chips -->
        <div class="row q-gutter-sm q-mb-lg">
          <q-chip v-if="agency.contact_phone" icon="phone" size="sm" color="grey-2" text-color="grey-8">
            {{ agency.contact_phone }}
          </q-chip>
          <q-chip v-if="agency.contact_email" icon="email" size="sm" color="grey-2" text-color="grey-8">
            {{ agency.contact_email }}
          </q-chip>
        </div>

        <!-- Packages -->
        <div class="text-h6 text-weight-bold q-mb-md">Packages</div>

        <div v-if="loadingPackages" class="row q-col-gutter-md">
          <div v-for="n in 3" :key="n" class="col-12 col-sm-6 col-md-4">
            <q-card flat bordered><q-skeleton height="150px" square /></q-card>
          </div>
        </div>

        <div v-else-if="packages.length === 0" class="text-center q-py-lg text-grey-5">
          <q-icon name="card_travel" size="3em" />
          <div class="q-mt-sm">No published packages yet</div>
        </div>

        <div v-else class="row q-col-gutter-md q-mb-xl">
          <div v-for="pkg in packages" :key="pkg.id" class="col-12 col-sm-6 col-md-4">
            <q-card flat bordered class="cursor-pointer card-hover" @click="$router.push(`/packages/${pkg.slug}`)">
              <q-img
                :src="pkg.cover_image || 'https://via.placeholder.com/400x220?text=Package'"
                ratio="1.7778"
                class="bg-grey-3"
              />
              <q-card-section>
                <div class="text-weight-bold ellipsis">{{ pkg.title }}</div>
                <div class="row items-center justify-between q-mt-xs">
                  <span class="text-deep-purple text-weight-bold">{{ pkg.formatted_price }}</span>
                  <span class="text-caption text-grey-6">{{ pkg.duration_days }}D · {{ pkg.spots_left }} left</span>
                </div>
                <div class="text-caption text-grey-6 q-mt-xs">
                  <q-icon name="calendar_today" size="10px" class="q-mr-xs" />
                  {{ fmtDate(pkg.start_date) }}
                </div>
              </q-card-section>
            </q-card>
          </div>
        </div>
      </div>
    </template>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAgencyStore } from 'src/stores/agencyStore'
import { useAuthStore } from 'src/stores/authStore'
import { api } from 'src/boot/axios'

const route = useRoute()
const $q = useQuasar()
const store = useAgencyStore()
const authStore = useAuthStore()

const slug = route.params.slug
const followLoading = ref(false)
const packages = ref([])
const loadingPackages = ref(false)

const agency = computed(() => store.currentAgency ?? {})
const isOwner = computed(() => authStore.user?.id && agency.value.user?.id === authStore.user.id)

onMounted(async () => {
  await store.fetchAgency(slug)
  loadPackages()
})

const loadPackages = async () => {
  loadingPackages.value = true
  try {
    const r = await api.get('/api/v1/packages', {
      params: { agency_id: agency.value.id, status: 'published' }
    })
    packages.value = r.data.data
  } finally {
    loadingPackages.value = false
  }
}

const toggleFollow = async () => {
  followLoading.value = true
  try {
    await store.followAgency(slug)
  } catch {
    $q.notify({ color: 'negative', message: 'Failed to update follow status', icon: 'error' })
  } finally {
    followLoading.value = false
  }
}

const tierColor = (t) => ({ basic: 'grey-6', pro: 'blue-7', premium: 'deep-purple' }[t] ?? 'grey')
const fmtDate = (d) => d ? new Date(d + 'T00:00:00').toLocaleDateString('en-PK', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
</script>

<style scoped>
.card-hover { transition: transform 0.15s; }
.card-hover:hover { transform: translateY(-3px); }
</style>
