<template>
  <q-page>
    <div v-if="store.loading" class="flex flex-center q-pa-xl">
      <q-spinner-dots color="deep-purple" size="3em" />
    </div>

    <template v-else-if="pkg">
      <!-- Hero image -->
      <div style="height: 320px; overflow: hidden" class="bg-grey-3 relative-position">
        <img
          v-if="pkg.cover_image"
          :src="pkg.cover_image"
          style="width:100%; height:100%; object-fit:cover"
        />
        <div v-else class="absolute-full flex flex-center">
          <q-icon name="landscape" size="100px" color="grey-4" />
        </div>

        <!-- Status badges -->
        <div class="absolute-top-right q-pa-md row q-gutter-sm">
          <q-badge color="deep-purple" class="q-pa-sm text-caption capitalize">
            {{ pkg.type?.replace('_', ' ') }}
          </q-badge>
          <q-badge v-if="pkg.is_full" color="negative" class="q-pa-sm text-caption">SOLD OUT</q-badge>
        </div>
      </div>

      <div class="q-pa-lg">
        <div class="row q-col-gutter-lg">
          <!-- Main content -->
          <div class="col-12 col-md-8">
            <div class="row items-center justify-between q-mb-xs">
              <div class="text-h4 text-weight-bold">{{ pkg.title }}</div>
              <q-btn
                v-if="authStore.isLoggedIn && !isOwnAgency && !pkg.reported_by_me"
                flat round dense
                icon="flag"
                color="grey-5"
                size="sm"
                @click="reportDialog = true"
              >
                <q-tooltip>Report this package</q-tooltip>
              </q-btn>
              <q-icon
                v-else-if="authStore.isLoggedIn && !isOwnAgency && pkg.reported_by_me"
                name="flag" color="grey-4" size="sm"
              >
                <q-tooltip>Reported — under review</q-tooltip>
              </q-icon>
            </div>

            <!-- Agency badge -->
            <div
              v-if="pkg.agency"
              class="row items-center q-gutter-sm q-mb-md cursor-pointer"
              @click="$router.push(`/agencies/${pkg.agency.slug}`)"
            >
              <q-avatar size="28px">
                <img v-if="pkg.agency.logo" :src="pkg.agency.logo" />
                <q-icon v-else name="business" />
              </q-avatar>
              <span class="text-body2 text-weight-medium">{{ pkg.agency.business_name }}</span>
              <q-icon v-if="pkg.agency.is_verified" name="verified" color="primary" size="16px" />
              <q-badge :color="tierColor(pkg.agency.tier)" :label="pkg.agency.tier?.toUpperCase()" class="text-caption" />
            </div>

            <!-- Quick stats row -->
            <div class="row q-gutter-md q-mb-lg text-body2">
              <div class="row items-center q-gutter-xs text-grey-7">
                <q-icon name="place" color="deep-purple" />
                <span>{{ pkg.destination?.name }}</span>
              </div>
              <div class="row items-center q-gutter-xs text-grey-7">
                <q-icon name="calendar_today" color="deep-purple" />
                <span>{{ fmtDate(pkg.start_date) }} – {{ fmtDate(pkg.end_date) }}</span>
              </div>
              <div class="row items-center q-gutter-xs text-grey-7">
                <q-icon name="schedule" color="deep-purple" />
                <span>{{ pkg.duration_days }} day{{ pkg.duration_days !== 1 ? 's' : '' }}</span>
              </div>
            </div>

            <!-- Capacity bar -->
            <div class="q-mb-lg">
              <div class="row justify-between text-caption text-grey-7 q-mb-xs">
                <span>{{ pkg.booked_count }} / {{ pkg.max_capacity }} spots filled</span>
                <span :class="pkg.spots_left < 5 ? 'text-negative text-weight-bold' : ''">
                  {{ pkg.spots_left }} spot{{ pkg.spots_left !== 1 ? 's' : '' }} remaining
                </span>
              </div>
              <q-linear-progress
                :value="pkg.booked_count / pkg.max_capacity"
                :color="pkg.is_full ? 'negative' : 'deep-purple'"
                rounded size="8px"
              />
            </div>

            <!-- Description -->
            <div class="text-h6 text-weight-bold q-mb-sm">About this Package</div>
            <p class="text-body2 text-grey-8" style="white-space: pre-wrap">{{ pkg.description }}</p>

            <!-- What's Included -->
            <div v-if="pkg.includes?.length" class="q-mt-lg">
              <div class="text-h6 text-weight-bold q-mb-sm">What's Included</div>
              <div class="row q-gutter-sm">
                <q-chip
                  v-for="item in pkg.includes"
                  :key="item"
                  icon="check_circle"
                  color="deep-purple-1"
                  text-color="deep-purple-9"
                  size="sm"
                >
                  {{ item }}
                </q-chip>
              </div>
            </div>

            <!-- Agency section -->
            <div v-if="pkg.agency" class="agency-card q-mt-xl">
              <div class="agency-card-title">About the agency</div>
              <div class="row items-center q-gutter-md">
                <q-avatar size="48px">
                  <img v-if="pkg.agency.logo" :src="pkg.agency.logo" />
                  <q-icon v-else name="business" size="24px" />
                </q-avatar>
                <div class="col">
                  <div class="text-weight-bold">{{ pkg.agency.business_name }}</div>
                  <div class="text-caption text-grey-7">{{ pkg.agency.follower_count }} followers</div>
                </div>
                <q-btn
                  v-if="authStore.isLoggedIn && !isOwnAgency"
                  :outline="!pkg.agency.is_following"
                  :color="pkg.agency.is_following ? 'grey' : 'deep-purple'"
                  :label="pkg.agency.is_following ? 'Following' : 'Follow'"
                  size="sm"
                  @click="toggleFollow"
                />
                <q-btn
                  outline
                  color="deep-purple"
                  label="View Agency"
                  size="sm"
                  :to="`/agencies/${pkg.agency.slug}`"
                />
              </div>
            </div>
          </div>

          <!-- Sticky booking card -->
          <div class="col-12 col-md-4">
            <q-card flat bordered class="sticky booking-card" style="top: 80px">
              <q-card-section class="price-head">
                <div class="price-label">Price per person</div>
                <div class="price-value">{{ pkg.formatted_price }}</div>
              </q-card-section>

              <q-card-section class="column q-gutter-xs">
                <div class="row items-center justify-between text-body2">
                  <span class="text-grey-7">Duration</span>
                  <span class="text-weight-medium">{{ pkg.duration_days }} days</span>
                </div>
                <div class="row items-center justify-between text-body2">
                  <span class="text-grey-7">Departure</span>
                  <span class="text-weight-medium">{{ fmtDate(pkg.start_date) }}</span>
                </div>
                <div class="row items-center justify-between text-body2">
                  <span class="text-grey-7">Return</span>
                  <span class="text-weight-medium">{{ fmtDate(pkg.end_date) }}</span>
                </div>
                <q-separator class="q-my-sm" />
                <div class="row items-center justify-between text-body2">
                  <span class="text-grey-7">Available spots</span>
                  <span class="text-weight-bold" :class="pkg.spots_left < 5 ? 'text-negative' : 'text-positive'">
                    {{ pkg.spots_left }}
                  </span>
                </div>
              </q-card-section>

              <!-- Traveler's booking status -->
              <q-card-section v-if="myBooking" class="q-pt-none column q-gutter-sm">
                <!-- Pending -->
                <div v-if="myBooking.status === 'pending'" class="status-note status-note--pending">
                  <q-icon name="hourglass_top" size="17px" />
                  <div>
                    <strong>Booking pending</strong>
                    <span>Waiting for {{ pkg.agency?.business_name || 'the agency' }} to confirm.</span>
                  </div>
                </div>

                <!-- Confirmed -->
                <template v-else-if="myBooking.status === 'confirmed'">
                  <div class="status-note status-note--confirmed">
                    <q-icon name="check_circle" size="17px" />
                    <div>
                      <strong>Booking confirmed</strong>
                      <span v-if="!pkg.trip_id">Your group chat opens shortly.</span>
                      <span v-else>You're on the list — say hello to your group.</span>
                    </div>
                  </div>
                  <q-btn
                    v-if="pkg.trip_id"
                    unelevated
                    no-caps
                    color="deep-purple"
                    icon="forum"
                    label="Join group chat"
                    class="full-width"
                    :to="`/trips/${pkg.trip_id}/chat`"
                  />
                </template>

                <!-- Cancelled: neutral, not alarming — they can simply book again. -->
                <div v-else-if="myBooking.status === 'cancelled'" class="status-note status-note--muted">
                  <q-icon name="history" size="17px" />
                  <div>
                    <strong>Previous booking cancelled</strong>
                    <span>You can book this package again below.</span>
                  </div>
                </div>

                <!-- Any other status (e.g. completed) -->
                <div v-else class="status-note status-note--muted">
                  <q-icon name="info" size="17px" />
                  <div><strong class="capitalize">{{ myBooking.status }}</strong></div>
                </div>
              </q-card-section>

              <q-card-actions class="q-px-md q-pb-md">
                <q-btn
                  v-if="!hasActiveBooking && !pkg.is_full && pkg.status === 'published' && !isOwnAgency"
                  unelevated
                  color="deep-purple"
                  label="Book Now"
                  class="full-width"
                  @click="showBookDialog = true"
                />
                <q-btn
                  v-else-if="pkg.is_full"
                  disable
                  outline
                  color="negative"
                  label="Fully Booked"
                  class="full-width"
                />
                <q-btn
                  v-else-if="myBooking && myBooking.status !== 'cancelled'"
                  outline
                  color="negative"
                  label="Cancel Booking"
                  class="full-width"
                  @click="doCancelBooking"
                />
              </q-card-actions>
            </q-card>
          </div>
        </div>
      </div>
    </template>

    <!-- Report dialog -->
    <ReportDialog
      v-if="pkg"
      v-model="reportDialog"
      :reported-id="pkg.id"
      reported-type="package"
      @reported="pkg.reported_by_me = true"
    />

    <!-- Book dialog -->
    <q-dialog v-model="showBookDialog" persistent>
      <q-card style="min-width: 360px">
        <q-card-section>
          <div class="text-h6">Book Package</div>
          <div class="text-body2 text-grey-6">{{ pkg?.title }}</div>
        </q-card-section>
        <q-card-section class="column q-gutter-sm">
          <q-input
            v-model.number="bookForm.travelers_count"
            type="number"
            :min="1"
            :max="pkg?.spots_left"
            label="Number of Travelers"
            outlined dense
          />
          <div class="text-subtitle1 text-weight-bold text-deep-purple">
            Total: PKR {{ fmt((pkg?.price_per_person ?? 0) * (bookForm.travelers_count || 0)) }}
          </div>
          <q-input
            v-model="bookForm.notes"
            label="Notes (optional)"
            outlined dense
            type="textarea"
            :rows="2"
            placeholder="Any special requirements or questions..."
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            unelevated color="deep-purple"
            label="Confirm Booking"
            :loading="booking"
            :disable="!bookForm.travelers_count || bookForm.travelers_count < 1"
            @click="submitBooking"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
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

const showBookDialog = ref(false)
const booking = ref(false)
const myBooking = ref(null)
const bookForm = reactive({ travelers_count: 1, notes: '' })
const reportDialog = ref(false)

const pkg = computed(() => store.currentPackage)
const isOwnAgency = computed(() =>
  pkg.value?.agency?.user?.id === authStore.user?.id
)
// A cancelled booking shouldn't block re-booking — only pending/confirmed do.
const hasActiveBooking = computed(() =>
  !!myBooking.value && myBooking.value.status !== 'cancelled'
)

onMounted(async () => {
  await store.fetchPackage(route.params.slug)
  if (authStore.isLoggedIn) fetchMyBooking()
})

const fetchMyBooking = async () => {
  try {
    const r = await api.get('/api/v1/bookings/my')
    // Newest-first from the API — first match for this package is the active one.
    const found = (r.data.data || []).find((b) => b.package?.slug === route.params.slug)
    myBooking.value = found ?? null
  } catch {
    // not logged in or request failed — leave booking unset
  }
}

const submitBooking = async () => {
  if (!bookForm.travelers_count) return
  booking.value = true
  try {
    const r = await store.bookPackage(route.params.slug, {
      travelers_count: bookForm.travelers_count,
      notes: bookForm.notes || null,
    })
    myBooking.value = r.data
    showBookDialog.value = false
    $q.notify({ color: 'positive', message: 'Booking submitted! Awaiting agency confirmation.', icon: 'check' })
  } catch (err) {
    $q.notify({ color: 'negative', message: err.response?.data?.message || 'Booking failed', icon: 'error' })
  } finally {
    booking.value = false
  }
}

const doCancelBooking = async () => {
  if (!myBooking.value) return
  try {
    await store.cancelBooking(route.params.slug, myBooking.value.id)
    myBooking.value = { ...myBooking.value, status: 'cancelled' }
    $q.notify({ color: 'positive', message: 'Booking cancelled', icon: 'check' })
  } catch (err) {
    $q.notify({ color: 'negative', message: err.response?.data?.message || 'Failed', icon: 'error' })
  }
}

const toggleFollow = async () => {
  try {
    await store.followAgency(pkg.value.agency.slug)
  } catch {
    $q.notify({ color: 'negative', message: 'Failed', icon: 'error' })
  }
}

const tierColor = (t) => ({ basic: 'grey-6', pro: 'blue-7', premium: 'deep-purple' }[t] ?? 'grey')
const fmtDate = (d) => d ? new Date(d + 'T00:00:00').toLocaleDateString('en-PK', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
const fmt = (n) => Number(n || 0).toLocaleString()
</script>

<style scoped>
.booking-card { border-radius: 14px; overflow: hidden; border-color: #ece6f0; }

.price-head {
  background: linear-gradient(135deg, #6a1b9a 0%, #4a148c 100%);
  color: #fff;
  padding: 18px 18px 16px;
}
.price-label { font-size: 11.5px; letter-spacing: 0.04em; opacity: 0.82; }
.price-value { font-size: 27px; font-weight: 700; letter-spacing: -0.02em; margin-top: 2px; }

/* Booking state: informative, never alarming — a cancelled booking is just
   history, and "Book Now" sits right underneath it. */
.status-note {
  display: flex;
  align-items: flex-start;
  gap: 9px;
  padding: 10px 12px;
  border-radius: 10px;
  font-size: 12.5px;
  line-height: 1.4;
}
.status-note div { display: flex; flex-direction: column; gap: 1px; }
.status-note strong { font-weight: 600; }
.status-note span { opacity: 0.85; }

.status-note--pending { background: #fff8e1; color: #8d6e00; }
.status-note--pending .q-icon { color: #ef6c00; }

.status-note--confirmed { background: #e8f5e9; color: #1b5e20; }
.status-note--confirmed .q-icon { color: #2e7d32; }

.status-note--muted { background: #f4f0f7; color: #6b5a75; }
.status-note--muted .q-icon { color: #9b8aa5; }

.capitalize { text-transform: capitalize; }

.agency-card {
  padding: 16px 18px;
  border-radius: 13px;
  background: linear-gradient(120deg, #faf5fd 0%, #f4ecf8 100%);
  border: 1px solid #ece0f2;
}
.agency-card-title {
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #9b8aa5;
  margin-bottom: 12px;
}
</style>
