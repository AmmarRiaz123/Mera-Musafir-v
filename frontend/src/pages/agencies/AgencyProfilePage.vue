<template>
  <q-page>
    <!-- Loading -->
    <div v-if="store.loading" class="flex flex-center q-pa-xl">
      <q-spinner-dots color="deep-purple" size="3em" />
    </div>

    <template v-else-if="store.currentAgency">
      <!-- Cover + header -->
      <div class="ag-cover">
        <img v-if="agency.cover_image" :src="agency.cover_image" alt="" />
        <div v-else class="ag-cover-empty"><q-icon name="landscape" size="72px" /></div>
      </div>

      <div class="q-px-lg">
        <!-- Only the logo overlaps the cover. Everything else starts below it,
             otherwise the name is drawn over the photo and can't be read. -->
        <div class="ag-header">
          <q-avatar size="86px" class="ag-logo">
            <img v-if="agency.logo" :src="agency.logo" />
            <q-icon v-else name="business" size="40px" color="deep-purple" />
          </q-avatar>

          <div class="ag-id">
            <div class="ag-name-line">
              <span class="ag-name">{{ agency.business_name }}</span>
              <q-icon v-if="agency.is_verified" name="verified" color="primary" size="20px">
                <q-tooltip>Verified agency</q-tooltip>
              </q-icon>
            </div>
            <div class="ag-meta">
              <q-badge :color="tierColor(agency.tier)" :label="agency.tier.toUpperCase()" />
              <span>{{ plural(agency.follower_count, 'follower') }}</span>
              <span class="ag-dot">·</span>
              <span>{{ plural(agency.packages_count ?? 0, 'package') }}</span>
            </div>
          </div>

          <div class="ag-actions">
            <q-btn
              v-if="isOwner"
              outline rounded no-caps
              color="deep-purple"
              icon="dashboard"
              label="Dashboard"
              :to="`/agencies/${agency.slug}/dashboard`"
            />
            <q-btn
              v-if="!isOwner && authStore.isLoggedIn"
              :unelevated="agency.is_following"
              :outline="!agency.is_following"
              rounded no-caps
              :color="agency.is_following ? 'grey-7' : 'deep-purple'"
              :icon="agency.is_following ? 'check' : 'person_add'"
              :label="agency.is_following ? 'Following' : 'Follow'"
              :loading="followLoading"
              @click="toggleFollow"
            />
          </div>
        </div>

        <!-- Description -->
        <p v-if="agency.description" class="ag-desc">{{ agency.description }}</p>

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
                <div class="row items-start justify-between no-wrap">
                  <div class="text-weight-bold ellipsis">{{ pkg.title }}</div>
                  <q-btn
                    flat round dense
                    icon="flag"
                    color="grey-5"
                    size="xs"
                    @click.stop="openReport(pkg)"
                  >
                    <q-tooltip>Report package</q-tooltip>
                  </q-btn>
                </div>
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

        <ReportDialog
          v-if="reportTarget"
          v-model="reportDialog"
          :reported-id="reportTarget.id"
          reported-type="package"
        />
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
import ReportDialog from 'src/components/ReportDialog.vue'

const route = useRoute()
const $q = useQuasar()
const store = useAgencyStore()
const authStore = useAuthStore()

const slug = route.params.slug
const followLoading = ref(false)
const packages = ref([])
const loadingPackages = ref(false)
const reportDialog = ref(false)
const reportTarget = ref(null)

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

const openReport = (pkg) => {
  reportTarget.value = pkg
  reportDialog.value = true
}

// "1 followers" reads like a bug even when the number is right.
const plural = (n, word) => `${n ?? 0} ${word}${Number(n) === 1 ? '' : 's'}`

const tierColor = (t) => ({ basic: 'grey-6', pro: 'blue-7', premium: 'deep-purple' }[t] ?? 'grey')
const fmtDate = (d) => d ? new Date(d + 'T00:00:00').toLocaleDateString('en-PK', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
</script>

<style scoped>
.ag-cover { height: 210px; background: #ede7f6; overflow: hidden; }
.ag-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
.ag-cover-empty {
  height: 100%; display: grid; place-items: center; color: #b39ddb;
}

.ag-header {
  display: flex; align-items: flex-end; gap: 16px;
  padding-top: 12px; margin-bottom: 14px; flex-wrap: wrap;
}
.ag-logo {
  margin-top: -56px; flex-shrink: 0;
  background: #fff; border: 4px solid #fff;
  box-shadow: 0 2px 10px rgba(43, 27, 51, 0.16);
}
.ag-id { flex: 1; min-width: 200px; }
.ag-name-line { display: flex; align-items: center; gap: 6px; }
.ag-name {
  font-size: 24px; font-weight: 700; line-height: 1.2;
  background: linear-gradient(135deg, #4a148c, #7b1fa2);
  -webkit-background-clip: text; background-clip: text; color: transparent;
}
.ag-meta {
  display: flex; align-items: center; gap: 7px; flex-wrap: wrap;
  margin-top: 5px; font-size: 12.5px; color: #7a6a82;
}
.ag-dot { opacity: 0.55; }
.ag-actions { display: flex; gap: 8px; align-items: center; }

.ag-desc { font-size: 14px; color: #4a3d52; max-width: 70ch; margin: 0 0 14px; }

.card-hover { transition: transform 0.15s; }
.card-hover:hover { transform: translateY(-3px); }
</style>
