<template>
  <q-page padding>
    <!-- Header -->
    <div class="row items-center q-mb-md">
      <q-btn flat round dense icon="arrow_back" @click="$router.back()" />
      <div class="col q-ml-sm">
        <div class="text-h5 text-weight-bold">
          {{ store.myAgency?.business_name || 'Agency Dashboard' }}
        </div>
        <div class="row items-center q-gutter-xs q-mt-xs">
          <q-badge
            v-if="store.myAgency"
            :color="tierColor(store.myAgency.tier)"
            :label="store.myAgency.tier?.toUpperCase()"
          />
          <q-badge
            v-if="store.myAgency?.is_verified"
            color="positive"
            label="VERIFIED"
          />
        </div>
      </div>
      <q-btn
        unelevated
        color="deep-purple"
        icon="add"
        label="New Package"
        :to="`/packages/create`"
      />
    </div>

    <q-banner v-if="store.myAgency" rounded class="bg-blue-1 text-blue-9 q-mb-md text-body2">
      <template v-slot:avatar><q-icon name="info" color="blue" /></template>
      Your agency is live. An admin verification badge will be awarded after review.
    </q-banner>

    <q-tabs
      v-model="activeTab"
      color="deep-purple"
      align="left"
      class="q-mb-md"
    >
      <q-tab name="packages" icon="card_travel" label="Packages" />
      <q-tab name="bookings" icon="book_online" label="Bookings" />
      <q-tab name="analytics" icon="bar_chart" label="Analytics" />
    </q-tabs>

    <q-tab-panels v-model="activeTab" animated>
      <!-- ─── PACKAGES TAB ──────────────────────────────────────────────── -->
      <q-tab-panel name="packages" class="q-pa-none">
        <div v-if="store.loading" class="flex flex-center q-pa-xl">
          <q-spinner-dots color="deep-purple" size="3em" />
        </div>

        <div v-else-if="store.myPackages.length === 0" class="text-center q-py-xl">
          <q-icon name="card_travel" size="5em" color="grey-3" />
          <div class="text-h6 text-grey-5 q-mt-md">No packages yet</div>
          <q-btn class="q-mt-md" color="deep-purple" unelevated rounded icon="add" label="Create First Package" to="/packages/create" />
        </div>

        <div v-else>
          <q-list bordered separator class="rounded-borders">
            <q-item v-for="pkg in store.myPackages" :key="pkg.id" class="q-py-sm">
              <q-item-section avatar>
                <q-img
                  :src="pkg.cover_image || 'https://via.placeholder.com/60x60?text=P'"
                  style="width:56px; height:56px; border-radius:6px"
                />
              </q-item-section>
              <q-item-section>
                <q-item-label class="text-weight-bold">{{ pkg.title }}</q-item-label>
                <q-item-label caption class="row items-center q-gutter-xs">
                  <q-badge :color="statusColor(pkg.status)" :label="pkg.status.toUpperCase()" />
                  <span>{{ pkg.destination?.name }}</span>
                  <span>·</span>
                  <span>{{ fmtDate(pkg.start_date) }}</span>
                  <span>·</span>
                  <span>{{ pkg.booked_count ?? 0 }} booked</span>
                </q-item-label>
              </q-item-section>
              <q-item-section side>
                <div class="row q-gutter-xs">
                  <q-btn flat round dense icon="visibility" size="sm" color="grey-7" :to="`/packages/${pkg.slug}`">
                    <q-tooltip>View</q-tooltip>
                  </q-btn>
                  <q-btn flat round dense icon="edit" size="sm" color="deep-purple" @click="openEditPackage(pkg)">
                    <q-tooltip>Edit</q-tooltip>
                  </q-btn>
                  <q-btn flat round dense icon="delete" size="sm" color="negative" @click="confirmDelete(pkg)">
                    <q-tooltip>Delete</q-tooltip>
                  </q-btn>
                </div>
              </q-item-section>
            </q-item>
          </q-list>
        </div>
      </q-tab-panel>

      <!-- ─── BOOKINGS TAB ──────────────────────────────────────────────── -->
      <q-tab-panel name="bookings" class="q-pa-none">
        <div class="row items-center justify-between q-mb-md">
          <div class="text-subtitle1 text-weight-bold">All Bookings</div>
          <q-select
            v-model="bookingFilter"
            :options="['all', 'pending', 'confirmed', 'cancelled', 'completed']"
            outlined dense
            style="min-width: 130px"
            @update:model-value="loadBookings"
          />
        </div>

        <div v-if="store.loading" class="flex flex-center q-pa-xl">
          <q-spinner-dots color="deep-purple" size="3em" />
        </div>

        <div v-else-if="store.bookings.length === 0" class="text-center q-py-xl text-grey-5">
          <q-icon name="book_online" size="4em" />
          <div class="q-mt-sm">No bookings found</div>
        </div>

        <q-list v-else bordered separator class="rounded-borders">
          <q-item v-for="booking in store.bookings" :key="booking.id" class="q-py-sm">
            <q-item-section avatar>
              <q-avatar color="deep-purple-1" text-color="deep-purple">
                {{ booking.user?.name?.[0]?.toUpperCase() }}
              </q-avatar>
            </q-item-section>
            <q-item-section>
              <q-item-label class="text-weight-medium">{{ booking.user?.name }}</q-item-label>
              <q-item-label caption class="row items-center q-gutter-xs">
                <span>{{ booking.package?.title }}</span>
                <span>·</span>
                <span>{{ booking.travelers_count }} traveler(s)</span>
                <span>·</span>
                <span class="text-deep-purple text-weight-medium">PKR {{ fmt(booking.total_amount) }}</span>
              </q-item-label>
              <q-item-label caption class="text-grey-5 q-mt-xs">
                {{ booking.user?.email }}
                <span v-if="booking.user?.phone"> · {{ booking.user.phone }}</span>
              </q-item-label>
              <div v-if="booking.notes" class="text-caption text-grey-6 q-mt-xs">
                <q-icon name="notes" size="xs" class="q-mr-xs" />{{ booking.notes }}
              </div>
            </q-item-section>
            <q-item-section side class="column items-end q-gutter-xs">
              <q-badge :color="bookingStatusColor(booking.status)" :label="booking.status.toUpperCase()" />
              <div class="text-caption text-grey-6">{{ fmtDate(booking.created_at) }}</div>
              <div class="row q-gutter-xs q-mt-xs">
                <q-btn
                  v-if="booking.status === 'pending'"
                  unelevated
                  color="positive"
                  label="Confirm"
                  size="xs"
                  :loading="confirmingId === booking.id"
                  @click="doConfirm(booking)"
                />
                <q-btn
                  v-if="booking.status !== 'cancelled' && booking.status !== 'completed'"
                  outline
                  color="negative"
                  label="Cancel"
                  size="xs"
                  :loading="cancellingId === booking.id"
                  @click="doCancel(booking)"
                />
              </div>
            </q-item-section>
          </q-item>
        </q-list>
      </q-tab-panel>

      <!-- ─── ANALYTICS TAB ────────────────────────────────────────────── -->
      <q-tab-panel name="analytics" class="q-pa-none">
        <!-- Upgrade prompt for basic tier -->
        <div v-if="store.myAgency?.tier === 'basic'" class="text-center q-py-xl">
          <q-icon name="bar_chart" size="5em" color="deep-purple-2" />
          <div class="text-h6 q-mt-md">Analytics requires Pro or Premium tier</div>
          <div class="text-body2 text-grey-6 q-mt-xs">Upgrade your plan to unlock detailed analytics</div>
          <q-btn class="q-mt-lg" color="deep-purple" unelevated rounded label="Upgrade Plan" icon="upgrade" disable />
          <div class="text-caption text-grey-5 q-mt-sm">Payment system coming in Phase 10</div>
        </div>

        <!-- Analytics content for Pro / Premium -->
        <template v-else>
          <div v-if="analyticsLoading" class="flex flex-center q-pa-xl">
            <q-spinner-dots color="deep-purple" size="3em" />
          </div>

          <template v-else-if="store.analytics">
            <!-- Stat cards -->
            <div class="row q-col-gutter-md q-mb-lg">
              <div v-for="stat in statCards" :key="stat.label" class="col-6 col-md-3">
                <q-card flat bordered class="text-center q-pa-md">
                  <q-icon :name="stat.icon" :color="stat.color" size="2em" class="q-mb-sm" />
                  <div class="text-h5 text-weight-bold" :class="`text-${stat.color}`">
                    {{ stat.formatted }}
                  </div>
                  <div class="text-caption text-grey-6 q-mt-xs">{{ stat.label }}</div>
                </q-card>
              </div>
            </div>

            <!-- Revenue progress -->
            <q-card flat bordered class="q-mb-lg q-pa-md">
              <div class="text-subtitle1 text-weight-bold q-mb-md">Monthly Revenue Progress</div>
              <div class="row items-center justify-between q-mb-xs text-caption text-grey-7">
                <span>This Month</span>
                <span class="text-weight-bold text-deep-purple">
                  PKR {{ fmt(store.analytics.revenue_this_month) }}
                </span>
              </div>
              <q-linear-progress
                :value="totalRevenue > 0 ? store.analytics.revenue_this_month / totalRevenue : 0"
                color="deep-purple"
                rounded
                size="12px"
                class="q-mb-xs"
              />
              <div class="text-caption text-grey-5">of PKR {{ fmt(totalRevenue) }} total</div>
            </q-card>

            <!-- Top packages -->
            <div class="row q-col-gutter-md">
              <div class="col-12 col-md-6">
                <q-card flat bordered>
                  <q-card-section>
                    <div class="text-subtitle1 text-weight-bold q-mb-sm">Top Packages</div>
                    <q-list dense separator>
                      <q-item v-for="(p, i) in store.analytics.top_packages" :key="p.id">
                        <q-item-section avatar>
                          <q-avatar size="28px" :color="['deep-purple', 'blue', 'teal'][i]" text-color="white">
                            {{ i + 1 }}
                          </q-avatar>
                        </q-item-section>
                        <q-item-section>
                          <q-item-label class="ellipsis text-weight-medium">{{ p.title }}</q-item-label>
                          <q-item-label caption>{{ p.bookings }} bookings · PKR {{ fmt(p.revenue) }}</q-item-label>
                        </q-item-section>
                      </q-item>
                      <q-item v-if="!store.analytics.top_packages?.length">
                        <q-item-section class="text-grey-5 text-caption text-center">No data yet</q-item-section>
                      </q-item>
                    </q-list>
                  </q-card-section>
                </q-card>
              </div>

              <!-- Recent bookings -->
              <div class="col-12 col-md-6">
                <q-card flat bordered>
                  <q-card-section>
                    <div class="text-subtitle1 text-weight-bold q-mb-sm">Recent Bookings</div>
                    <q-list dense separator>
                      <q-item v-for="b in store.analytics.recent_bookings" :key="b.id">
                        <q-item-section>
                          <q-item-label class="text-weight-medium">{{ b.traveler }}</q-item-label>
                          <q-item-label caption class="ellipsis">{{ b.package_title }}</q-item-label>
                        </q-item-section>
                        <q-item-section side>
                          <q-badge :color="bookingStatusColor(b.status)" :label="b.status" />
                          <div class="text-caption text-grey-6 q-mt-xs text-right">PKR {{ fmt(b.total_amount) }}</div>
                        </q-item-section>
                      </q-item>
                      <q-item v-if="!store.analytics.recent_bookings?.length">
                        <q-item-section class="text-grey-5 text-caption text-center">No bookings yet</q-item-section>
                      </q-item>
                    </q-list>
                  </q-card-section>
                </q-card>
              </div>
            </div>
          </template>
        </template>
      </q-tab-panel>
    </q-tab-panels>

    <!-- Edit Package dialog -->
    <q-dialog v-model="showEditDialog" persistent>
      <q-card style="min-width: 360px; max-width: 520px">
        <q-card-section>
          <div class="text-h6">Edit Package</div>
        </q-card-section>
        <q-card-section class="column q-gutter-sm">
          <q-input v-model="editForm.title" label="Title" outlined dense />
          <q-input v-model.number="editForm.price_per_person" type="number" label="Price per person (PKR)" outlined dense prefix="PKR" />
          <q-input v-model.number="editForm.max_capacity" type="number" label="Max Capacity" outlined dense />
          <q-select
            v-model="editForm.status"
            :options="['draft', 'published', 'closed', 'archived']"
            label="Status"
            outlined dense
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn flat color="deep-purple" label="Save" :loading="editSubmitting" @click="saveEdit" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAgencyStore } from 'src/stores/agencyStore'

const route = useRoute()
const router = useRouter()
const $q = useQuasar()
const store = useAgencyStore()

const activeTab = ref('packages')
const bookingFilter = ref('all')
const confirmingId = ref(null)
const cancellingId = ref(null)
const analyticsLoading = ref(false)
const showEditDialog = ref(false)
const editSubmitting = ref(false)
const editingPkg = ref(null)
const editForm = reactive({ title: '', price_per_person: null, max_capacity: null, status: '' })

const totalRevenue = computed(() => store.analytics?.total_revenue ?? 0)

const statCards = computed(() => {
  if (!store.analytics) return []
  const a = store.analytics
  return [
    { label: 'Total Revenue',       icon: 'payments',    color: 'deep-purple', formatted: `PKR ${fmt(a.total_revenue)}` },
    { label: 'Total Bookings',      icon: 'book_online', color: 'blue-7',      formatted: a.total_bookings },
    { label: 'This Month Revenue',  icon: 'trending_up', color: 'positive',    formatted: `PKR ${fmt(a.revenue_this_month)}` },
    { label: 'This Month Bookings', icon: 'event_note',  color: 'orange',      formatted: a.bookings_this_month },
  ]
})

onMounted(async () => {
  await store.fetchMyAgency()

  // Fix 4: verify the logged-in user owns the agency at this slug
  if (!store.myAgency || store.myAgency.slug !== route.params.slug) {
    router.replace(`/agencies/${route.params.slug}`)
    return
  }

  await store.fetchMyPackages()
  loadBookings()

  // Fix 5: start analytics fetch in background if tier allows
  if (store.myAgency.tier !== 'basic') {
    loadAnalytics()
  }
})

// Reload bookings when tab is activated (catches filter changes too)
watch(activeTab, (tab) => {
  if (tab === 'bookings') loadBookings()
})

const loadBookings = async () => {
  if (!store.myAgency?.slug) return
  const params = bookingFilter.value !== 'all' ? { status: bookingFilter.value } : {}
  await store.fetchAgencyBookings(store.myAgency.slug, params)
}

const loadAnalytics = async () => {
  analyticsLoading.value = true
  try {
    await store.fetchAnalytics(store.myAgency.slug)
  } catch (err) {
    $q.notify({ color: 'negative', message: err.response?.data?.message || 'Failed to load analytics', icon: 'error' })
  } finally {
    analyticsLoading.value = false
  }
}

// Fix 6: refresh booking list after confirm/cancel
const doConfirm = async (booking) => {
  confirmingId.value = booking.id
  try {
    await store.confirmBooking(booking.package?.slug, booking.id)
    $q.notify({ color: 'positive', message: 'Booking confirmed', icon: 'check' })
    await loadBookings()
  } catch {
    $q.notify({ color: 'negative', message: 'Failed to confirm', icon: 'error' })
  } finally {
    confirmingId.value = null
  }
}

const doCancel = async (booking) => {
  cancellingId.value = booking.id
  try {
    await store.cancelBooking(booking.package?.slug, booking.id)
    $q.notify({ color: 'info', message: 'Booking cancelled' })
    await loadBookings()
  } catch {
    $q.notify({ color: 'negative', message: 'Failed to cancel', icon: 'error' })
  } finally {
    cancellingId.value = null
  }
}

const openEditPackage = (pkg) => {
  editingPkg.value = pkg
  Object.assign(editForm, {
    title: pkg.title,
    price_per_person: pkg.price_per_person,
    max_capacity: pkg.max_capacity,
    status: pkg.status,
  })
  showEditDialog.value = true
}

const saveEdit = async () => {
  if (!editingPkg.value) return
  editSubmitting.value = true
  try {
    await store.updatePackage(editingPkg.value.slug, { ...editForm })
    await store.fetchMyPackages()
    showEditDialog.value = false
    $q.notify({ color: 'positive', message: 'Package updated', icon: 'check' })
  } catch (err) {
    $q.notify({ color: 'negative', message: err.response?.data?.message || 'Failed', icon: 'error' })
  } finally {
    editSubmitting.value = false
  }
}

const confirmDelete = (pkg) => {
  $q.dialog({
    title: 'Delete Package?',
    message: `Delete "${pkg.title}"? This cannot be undone.`,
    cancel: true,
    persistent: true,
    color: 'negative',
  }).onOk(async () => {
    try {
      await store.deletePackage(pkg.slug)
      $q.notify({ color: 'positive', message: 'Package deleted', icon: 'check' })
    } catch {
      $q.notify({ color: 'negative', message: 'Failed to delete', icon: 'error' })
    }
  })
}

const tierColor = (t) => ({ basic: 'grey-6', pro: 'blue-7', premium: 'deep-purple' }[t] ?? 'grey')
const statusColor = (s) => ({ draft: 'grey', published: 'positive', closed: 'orange', archived: 'negative' }[s] ?? 'grey')
const bookingStatusColor = (s) => ({ pending: 'orange', confirmed: 'positive', cancelled: 'negative', completed: 'blue' }[s] ?? 'grey')
const fmtDate = (d) => {
  if (!d) return '—'
  const s = typeof d === 'string' && d.length <= 10 ? d + 'T00:00:00' : d
  return new Date(s).toLocaleDateString('en-PK', { day: 'numeric', month: 'short', year: 'numeric' })
}
const fmt = (n) => Number(n || 0).toLocaleString()
</script>
