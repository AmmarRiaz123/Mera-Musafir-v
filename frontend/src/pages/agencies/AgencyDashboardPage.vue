<template>
  <q-page class="console-page">
    <div class="console-shell">
      <!-- ── Identity header ─────────────────────────────────────── -->
      <header class="console-head">
        <q-avatar size="52px" class="agency-mark">
          <img v-if="agency?.logo" :src="agency.logo" />
          <span v-else>{{ agency?.business_name?.[0]?.toUpperCase() || 'A' }}</span>
        </q-avatar>

        <div class="head-text">
          <h1 class="head-title">{{ agency?.business_name || 'Agency Dashboard' }}</h1>
          <div class="head-meta">
            <span class="chip" :class="`chip--${agency?.tier}`">{{ agency?.tier?.toUpperCase() }}</span>
            <span v-if="agency?.is_verified" class="chip chip--verified">
              <q-icon name="verified" size="12px" /> VERIFIED
            </span>
            <router-link v-if="agency" :to="`/agencies/${agency.slug}`" class="storefront-link">
              View storefront <q-icon name="open_in_new" size="12px" />
            </router-link>
          </div>
        </div>

        <q-btn
          unelevated
          rounded
          no-caps
          color="primary"
          icon="add"
          label="New Package"
          to="/packages/create"
        />
      </header>

      <!-- Only shown while actually awaiting verification -->
      <q-banner
        v-if="agency && !agency.is_verified && !bannerDismissed"
        rounded
        class="verify-banner"
      >
        <template v-slot:avatar><q-icon name="hourglass_top" color="amber-8" /></template>
        Your agency is live. A verification badge will be awarded after admin review.
        <template v-slot:action>
          <q-btn flat dense no-caps label="Dismiss" @click="bannerDismissed = true" />
        </template>
      </q-banner>

      <!-- ── Section nav ─────────────────────────────────────────── -->
      <nav class="section-nav">
        <button
          v-for="s in sections"
          :key="s.value"
          type="button"
          class="section-btn"
          :class="{ 'section-btn--active': section === s.value }"
          @click="goTo(s.value)"
        >
          <q-icon :name="s.icon" size="17px" />
          <span>{{ s.label }}</span>
          <span v-if="s.value === 'bookings' && pendingCount" class="nav-count">{{ pendingCount }}</span>
        </button>
      </nav>

      <!-- ══ OVERVIEW ═══════════════════════════════════════════════ -->
      <section v-if="section === 'overview'">
        <div class="kpi-grid">
          <article v-for="k in kpis" :key="k.label" class="kpi" :class="{ 'kpi--alert': k.alert }">
            <div class="kpi-top">
              <q-icon :name="k.icon" size="18px" />
              <span>{{ k.label }}</span>
            </div>
            <div class="kpi-value">{{ k.value }}</div>
            <div class="kpi-sub">{{ k.sub }}</div>
          </article>
        </div>

        <!-- Action queue -->
        <div class="panel">
          <div class="panel-head">
            <div class="panel-title">
              <q-icon name="pending_actions" size="18px" />
              Needs your attention
            </div>
            <span v-if="pendingList.length" class="panel-badge">{{ pendingList.length }}</span>
          </div>

          <div v-if="!pendingList.length" class="empty-inline">
            <q-icon name="task_alt" size="28px" />
            <div>You're all caught up — no bookings waiting on you.</div>
          </div>

          <div v-else class="queue">
            <div v-for="b in pendingList" :key="b.id" class="queue-row">
              <q-avatar size="36px" class="traveler-mark">
                {{ b.user?.name?.[0]?.toUpperCase() }}
              </q-avatar>
              <div class="queue-main">
                <div class="queue-name">{{ b.user?.name }}</div>
                <div class="queue-sub">
                  {{ b.package?.title }} · {{ b.travelers_count }}
                  {{ b.travelers_count === 1 ? 'traveler' : 'travelers' }}
                </div>
              </div>
              <div class="queue-amount">PKR {{ fmt(b.total_amount) }}</div>
              <div class="queue-actions">
                <q-btn
                  unelevated dense no-caps size="sm" color="positive" label="Approve"
                  :loading="confirmingId === b.id" @click="doConfirm(b)"
                />
                <q-btn
                  flat dense no-caps size="sm" color="grey-7" label="Decline"
                  :loading="cancellingId === b.id" @click="doCancel(b)"
                />
              </div>
            </div>
          </div>
        </div>

        <div class="two-col">
          <!-- Upcoming departures -->
          <div class="panel">
            <div class="panel-head">
              <div class="panel-title"><q-icon name="flight_takeoff" size="18px" />Upcoming departures</div>
            </div>
            <div v-if="!upcoming.length" class="empty-inline">
              <q-icon name="event_available" size="28px" />
              <div>No upcoming departures scheduled.</div>
            </div>
            <div v-else class="dep-list">
              <div v-for="p in upcoming" :key="p.id" class="dep-row">
                <div class="dep-date">
                  <span class="dep-day">{{ dayOf(p.start_date) }}</span>
                  <span class="dep-mon">{{ monthOf(p.start_date) }}</span>
                </div>
                <div class="dep-main">
                  <div class="dep-title">{{ p.title }}</div>
                  <div class="occupancy">
                    <div class="occupancy-bar">
                      <div class="occupancy-fill" :style="{ width: pct(p) + '%' }" />
                    </div>
                    <span class="occupancy-text">{{ p.booked_count }}/{{ p.max_capacity }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Recent activity -->
          <div class="panel">
            <div class="panel-head">
              <div class="panel-title"><q-icon name="history" size="18px" />Recent activity</div>
            </div>
            <div v-if="!recent.length" class="empty-inline">
              <q-icon name="inbox" size="28px" />
              <div>No bookings yet.</div>
            </div>
            <div v-else class="act-list">
              <div v-for="b in recent" :key="b.id" class="act-row">
                <span class="dot" :class="`dot--${b.status}`" />
                <div class="act-main">
                  <div class="act-name">{{ b.user?.name }}</div>
                  <div class="act-sub">{{ b.package?.title }}</div>
                </div>
                <div class="act-right">
                  <div class="act-amount">PKR {{ fmt(b.total_amount) }}</div>
                  <div class="act-date">{{ fmtDate(b.created_at) }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ══ PACKAGES ═══════════════════════════════════════════════ -->
      <section v-else-if="section === 'packages'">
        <div v-if="store.loading" class="loading-pad"><q-spinner-dots color="primary" size="2.5em" /></div>

        <div v-else-if="!store.myPackages.length" class="empty-block">
          <q-icon name="inventory_2" size="46px" />
          <div class="empty-title">No packages yet</div>
          <p class="empty-text">Packages are what travelers browse and book. Create your first one to start taking bookings.</p>
          <q-btn unelevated rounded no-caps color="primary" icon="add" label="Create first package" to="/packages/create" />
        </div>

        <div v-else class="pkg-grid">
          <article v-for="p in store.myPackages" :key="p.id" class="pkg-card">
            <div class="pkg-cover" :style="coverStyle(p)">
              <span v-if="!p.cover_image" class="pkg-initial">{{ p.title?.[0]?.toUpperCase() }}</span>
              <span class="pkg-status" :class="`pkg-status--${p.status}`">{{ p.status }}</span>
              <q-btn round dense flat class="pkg-menu" icon="more_vert" color="white">
                <q-menu anchor="bottom right" self="top right">
                  <q-list style="min-width: 170px">
                    <q-item clickable v-close-popup :to="`/packages/${p.slug}`">
                      <q-item-section avatar><q-icon name="visibility" size="xs" /></q-item-section>
                      <q-item-section>View public page</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="openEditPackage(p)">
                      <q-item-section avatar><q-icon name="edit" size="xs" /></q-item-section>
                      <q-item-section>Edit</q-item-section>
                    </q-item>
                    <q-separator />
                    <q-item clickable v-close-popup @click="confirmDelete(p)">
                      <q-item-section avatar><q-icon name="delete" size="xs" color="negative" /></q-item-section>
                      <q-item-section class="text-negative">Delete</q-item-section>
                    </q-item>
                  </q-list>
                </q-menu>
              </q-btn>
            </div>

            <div class="pkg-body">
              <div class="pkg-title">{{ p.title }}</div>
              <div class="pkg-meta">
                <q-icon name="place" size="13px" />{{ p.destination?.name || '—' }}
                <span class="sep">·</span>{{ fmtDate(p.start_date) }}
              </div>

              <div class="occupancy q-mt-sm">
                <div class="occupancy-bar">
                  <div class="occupancy-fill" :style="{ width: pct(p) + '%' }" />
                </div>
                <span class="occupancy-text">{{ p.booked_count }}/{{ p.max_capacity }}</span>
              </div>

              <div class="pkg-foot">
                <span class="pkg-price">{{ p.formatted_price }}</span>
                <span class="pkg-views"><q-icon name="visibility" size="13px" /> {{ p.views_count ?? 0 }}</span>
              </div>
            </div>
          </article>
        </div>
      </section>

      <!-- ══ BOOKINGS ═══════════════════════════════════════════════ -->
      <section v-else-if="section === 'bookings'">
        <div class="filter-row">
          <button
            v-for="f in bookingFilters"
            :key="f.value"
            type="button"
            class="filter-btn"
            :class="{ 'filter-btn--active': bookingFilter === f.value }"
            @click="setFilter(f.value)"
          >
            {{ f.label }}
            <span v-if="counts[f.value]" class="filter-count">{{ counts[f.value] }}</span>
          </button>
        </div>

        <div v-if="store.loading" class="loading-pad"><q-spinner-dots color="primary" size="2.5em" /></div>

        <div v-else-if="!store.bookings.length" class="empty-block">
          <q-icon name="book_online" size="46px" />
          <div class="empty-title">No {{ bookingFilter === 'all' ? '' : bookingFilter }} bookings</div>
          <p class="empty-text">
            Bookings appear here as travellers request your packages. Approve one and they can pay —
            the group chat opens once payment clears.
          </p>
        </div>

        <div v-else class="booking-table">
          <div class="bt-head">
            <span>Traveler</span><span>Package</span><span class="ta-c">Travelers</span>
            <span class="ta-r">Amount</span><span>Status</span><span class="ta-r">Actions</span>
          </div>

          <div v-for="b in store.bookings" :key="b.id" class="bt-row">
            <div class="bt-traveler">
              <q-avatar size="34px" class="traveler-mark">{{ b.user?.name?.[0]?.toUpperCase() }}</q-avatar>
              <div style="min-width: 0">
                <div class="bt-name">{{ b.user?.name }}</div>
                <div class="bt-contact">{{ b.user?.email }}</div>
              </div>
            </div>

            <div class="bt-pkg">
              <div class="ellipsis">{{ b.package?.title }}</div>
              <div class="bt-date">{{ fmtDate(b.created_at) }}</div>
            </div>

            <div class="ta-c bt-num">{{ b.travelers_count }}</div>
            <div class="ta-r bt-amount">PKR {{ fmt(b.total_amount) }}</div>

            <div>
              <span class="status-pill" :class="`status-pill--${b.status}`">{{ b.status }}</span>
              <div v-if="b.notes" class="bt-note" :title="b.notes">
                <q-icon name="notes" size="12px" /> {{ b.notes }}
              </div>
            </div>

            <div class="bt-actions">
              <q-btn
                v-if="b.status === 'pending'"
                unelevated dense no-caps size="sm" color="positive" label="Approve"
                :loading="confirmingId === b.id" @click="doConfirm(b)"
              />
              <q-btn
                v-if="b.status === 'confirmed' && b.package?.trip_id"
                outline dense no-caps size="sm" color="primary" icon="forum" label="Group"
                :to="`/trips/${b.package.trip_id}/chat`"
              />
              <q-btn
                v-if="b.status !== 'cancelled' && b.status !== 'completed'"
                flat dense no-caps size="sm" color="grey-7" label="Cancel"
                :loading="cancellingId === b.id" @click="doCancel(b)"
              />
            </div>
          </div>
        </div>
      </section>

      <!-- ══ ANALYTICS ══════════════════════════════════════════════ -->
      <section v-else-if="section === 'analytics'">
        <div v-if="agency?.tier === 'basic'" class="upgrade-card">
          <q-icon name="insights" size="42px" />
          <div class="empty-title">Analytics is a Pro feature</div>
          <p class="empty-text">
            Upgrade to Pro to see revenue trends, conversion rates and per-package performance.
          </p>
          <q-btn unelevated rounded no-caps color="primary" icon="upgrade" label="Upgrade plan" disable />
          <div class="hint">Payments arrive in Phase 10</div>
        </div>

        <template v-else>
          <div v-if="analyticsLoading" class="loading-pad"><q-spinner-dots color="primary" size="2.5em" /></div>

          <template v-else-if="store.analytics">
            <div class="kpi-grid">
              <article v-for="k in analyticsKpis" :key="k.label" class="kpi">
                <div class="kpi-top"><q-icon :name="k.icon" size="18px" /><span>{{ k.label }}</span></div>
                <div class="kpi-value">{{ k.value }}</div>
                <div class="kpi-sub">{{ k.sub }}</div>
              </article>
            </div>

            <!-- Revenue by package: a real comparison, unlike the old
                 "this month vs all time" ratio, which was meaningless. -->
            <div class="panel">
              <div class="panel-head">
                <div class="panel-title"><q-icon name="leaderboard" size="18px" />Revenue by package</div>
              </div>
              <div v-if="!topPackages.length" class="empty-inline">
                <q-icon name="bar_chart" size="28px" />
                <div>No confirmed revenue yet.</div>
              </div>
              <div v-else class="bars">
                <div v-for="p in topPackages" :key="p.id" class="bar-row">
                  <div class="bar-label ellipsis">{{ p.title }}</div>
                  <div class="bar-track">
                    <div class="bar-fill" :style="{ width: barPct(p.revenue) + '%' }" />
                  </div>
                  <div class="bar-value">PKR {{ fmt(p.revenue) }}</div>
                </div>
              </div>
            </div>

            <div class="panel">
              <div class="panel-head">
                <div class="panel-title"><q-icon name="receipt_long" size="18px" />Latest bookings</div>
              </div>
              <div v-if="!store.analytics.recent_bookings?.length" class="empty-inline">
                <q-icon name="inbox" size="28px" /><div>No bookings yet.</div>
              </div>
              <div v-else class="act-list">
                <div v-for="b in store.analytics.recent_bookings" :key="b.id" class="act-row">
                  <span class="dot" :class="`dot--${b.status}`" />
                  <div class="act-main">
                    <div class="act-name">{{ b.traveler }}</div>
                    <div class="act-sub">{{ b.package_title }}</div>
                  </div>
                  <div class="act-right">
                    <div class="act-amount">PKR {{ fmt(b.total_amount) }}</div>
                    <div class="act-date">{{ b.status }}</div>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </template>
      </section>

      <!-- ══ SETTINGS ═══════════════════════════════════════════════ -->
      <section v-else-if="section === 'settings'">
        <div class="panel">
          <div class="panel-head">
            <div class="panel-title"><q-icon name="palette" size="18px" />Branding</div>
          </div>

          <div class="brand-row">
            <div class="brand-field">
              <span class="field-label">Logo</span>
              <ImageUpload v-model="settings.logo" type="agency_logo" round icon="add_photo_alternate" label="Logo" />
              <span class="field-hint">Square works best</span>
            </div>

            <div class="brand-field brand-field--grow">
              <span class="field-label">Storefront cover</span>
              <ImageUpload v-model="settings.cover_image" type="agency_logo" label="Add a cover photo" class="full-width" />
              <span class="field-hint">Shown across the top of your public page</span>
            </div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head">
            <div class="panel-title"><q-icon name="storefront" size="18px" />Business details</div>
          </div>

          <div class="row q-col-gutter-md">
            <div class="col-12">
              <q-input v-model="settings.business_name" label="Business name" outlined no-error-icon maxlength="150" />
            </div>
            <div class="col-12">
              <q-input
                v-model="settings.description" label="About your agency" outlined no-error-icon
                type="textarea" rows="3"
                hint="Travellers read this before booking — say what makes your trips different."
              />
            </div>
            <div class="col-12 col-sm-6">
              <q-input v-model="settings.contact_phone" label="Contact phone" outlined no-error-icon maxlength="20">
                <template v-slot:prepend><q-icon name="call" size="18px" color="primary" /></template>
              </q-input>
            </div>
            <div class="col-12 col-sm-6">
              <q-input v-model="settings.contact_email" label="Contact email" outlined no-error-icon type="email">
                <template v-slot:prepend><q-icon name="mail" size="18px" color="primary" /></template>
              </q-input>
            </div>
          </div>

          <div class="settings-actions">
            <q-btn flat no-caps color="grey-7" label="Reset" :disable="savingSettings" @click="resetSettings" />
            <q-btn
              unelevated rounded no-caps color="primary" icon="check" label="Save changes"
              :loading="savingSettings" @click="saveSettings"
            />
          </div>
        </div>
      </section>
    </div>

    <!-- Edit package dialog -->
    <q-dialog v-model="showEditDialog" persistent>
      <q-card style="min-width: 360px; max-width: 520px; border-radius: 14px">
        <q-card-section><div class="text-h6">Edit package</div></q-card-section>
        <q-card-section class="column q-gutter-md q-pt-none">
          <q-input v-model="editForm.title" label="Title" outlined dense no-error-icon />
          <q-input v-model.number="editForm.price_per_person" type="number" label="Price per person" outlined dense prefix="PKR" />
          <q-input v-model.number="editForm.max_capacity" type="number" label="Max capacity" outlined dense />
          <q-select v-model="editForm.status" :options="['draft', 'published', 'closed', 'archived']" label="Status" outlined dense />
          <div>
            <div class="field-label q-mb-xs">Cover photo</div>
            <ImageUpload v-model="editForm.cover_image" type="package_cover" label="Change cover" class="full-width" />
          </div>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat no-caps label="Cancel" v-close-popup />
          <q-btn unelevated no-caps color="primary" label="Save" :loading="editSubmitting" @click="saveEdit" />
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
import ImageUpload from 'components/ImageUpload.vue'

const route = useRoute()
const router = useRouter()
const $q = useQuasar()
const store = useAgencyStore()

const sections = [
  { value: 'overview',  label: 'Overview',  icon: 'dashboard' },
  { value: 'packages',  label: 'Packages',  icon: 'inventory_2' },
  { value: 'bookings',  label: 'Bookings',  icon: 'book_online' },
  { value: 'analytics', label: 'Analytics', icon: 'insights' },
  { value: 'settings',  label: 'Settings',  icon: 'settings' },
]

const bookingFilters = [
  { value: 'all',       label: 'All' },
  { value: 'pending',   label: 'Pending' },
  { value: 'approved', label: 'Awaiting payment' },
  { value: 'confirmed', label: 'Paid' },
  { value: 'cancelled', label: 'Cancelled' },
]

const section = ref(route.query.section || 'overview')
const bookingFilter = ref('all')
const bannerDismissed = ref(false)
const analyticsLoading = ref(false)
const confirmingId = ref(null)
const cancellingId = ref(null)
const allBookings = ref([])

const savingSettings = ref(false)
const settings = reactive({
  business_name: '',
  description: '',
  contact_phone: '',
  contact_email: '',
  logo: null,
  cover_image: null,
})

const showEditDialog = ref(false)
const editSubmitting = ref(false)
const editingPkg = ref(null)
const editForm = reactive({ title: '', price_per_person: null, max_capacity: null, status: '', cover_image: null })

const agency = computed(() => store.myAgency)

// Overview KPIs are derived from bookings + packages so they work on every
// tier — the analytics endpoint is Pro-only.
const confirmed = computed(() => allBookings.value.filter((b) => b.status === 'confirmed'))
const pendingList = computed(() => allBookings.value.filter((b) => b.status === 'pending'))
const pendingCount = computed(() => pendingList.value.length)
const recent = computed(() => allBookings.value.slice(0, 5))

const revenue = computed(() => confirmed.value.reduce((s, b) => s + Number(b.total_amount || 0), 0))
const travelers = computed(() => confirmed.value.reduce((s, b) => s + Number(b.travelers_count || 0), 0))
const views = computed(() => store.myPackages.reduce((s, p) => s + Number(p.views_count || 0), 0))
const occupancy = computed(() => {
  const cap = store.myPackages.reduce((s, p) => s + Number(p.max_capacity || 0), 0)
  const booked = store.myPackages.reduce((s, p) => s + Number(p.booked_count || 0), 0)
  return cap ? Math.round((booked / cap) * 100) : 0
})

const counts = computed(() => ({
  all: allBookings.value.length,
  pending: pendingCount.value,
  confirmed: confirmed.value.length,
  cancelled: allBookings.value.filter((b) => b.status === 'cancelled').length,
}))

const kpis = computed(() => [
  {
    label: 'Confirmed revenue',
    icon: 'payments',
    value: `PKR ${fmt(revenue.value)}`,
    sub: `${confirmed.value.length} confirmed booking${confirmed.value.length === 1 ? '' : 's'}`,
  },
  {
    label: 'Awaiting you',
    icon: 'pending_actions',
    value: pendingCount.value,
    sub: pendingCount.value ? 'Needs confirmation' : 'All caught up',
    alert: pendingCount.value > 0,
  },
  {
    label: 'Travelers booked',
    icon: 'groups',
    value: travelers.value,
    sub: `${occupancy.value}% of capacity filled`,
  },
  {
    label: 'Package views',
    icon: 'visibility',
    value: fmt(views.value),
    sub: `${store.myPackages.length} package${store.myPackages.length === 1 ? '' : 's'} listed`,
  },
])

const analyticsKpis = computed(() => {
  const a = store.analytics || {}
  // Clamped: bookings can be created without a page view (e.g. via a direct
  // link), which would otherwise render a nonsensical >100% conversion.
  const conv =
    a.total_views > 0
      ? Math.min(100, (a.total_bookings / a.total_views) * 100).toFixed(1)
      : '0.0'
  return [
    { label: 'Total revenue',  icon: 'payments',        value: `PKR ${fmt(a.total_revenue)}`,      sub: 'From confirmed bookings' },
    { label: 'This month',     icon: 'trending_up',     value: `PKR ${fmt(a.revenue_this_month)}`, sub: `${a.bookings_this_month || 0} confirmed this month` },
    { label: 'Total bookings', icon: 'book_online',     value: a.total_bookings || 0,              sub: 'All statuses' },
    { label: 'View → booking', icon: 'conversion_path', value: `${conv}%`,                          sub: `${fmt(a.total_views)} views` },
  ]
})

const topPackages = computed(() =>
  (store.analytics?.top_packages || []).filter((p) => Number(p.revenue) > 0),
)

const barPct = (v) => {
  const max = Math.max(...topPackages.value.map((p) => Number(p.revenue) || 0), 1)
  return Math.max(4, Math.round((Number(v || 0) / max) * 100))
}

const upcoming = computed(() =>
  [...store.myPackages]
    .filter((p) => p.start_date && new Date(p.start_date) >= new Date(new Date().toDateString()))
    .sort((a, b) => new Date(a.start_date) - new Date(b.start_date))
    .slice(0, 4),
)

const pct = (p) => {
  const cap = Number(p.max_capacity || 0)
  return cap ? Math.min(100, Math.round((Number(p.booked_count || 0) / cap) * 100)) : 0
}

const coverStyle = (p) =>
  p.cover_image
    ? { backgroundImage: `url(${p.cover_image})`, backgroundSize: 'cover', backgroundPosition: 'center' }
    : {}

const goTo = (value) => {
  section.value = value
  router.replace({ query: { ...route.query, section: value } })
}

const setFilter = (value) => {
  bookingFilter.value = value
  loadBookings()
}

// Unfiltered copy powering the overview KPIs and the nav badge.
const loadAllBookings = async () => {
  if (!store.myAgency?.slug) return
  try {
    const r = await store.fetchAgencyBookings(store.myAgency.slug, {})
    allBookings.value = r?.data || store.bookings || []
    store.pendingBookings = allBookings.value.filter((b) => b.status === 'pending').length
  } catch {
    allBookings.value = []
  }
}

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

onMounted(async () => {
  if (!store.myAgency) await store.fetchMyAgency()

  // Only the owner may see this console.
  if (!store.myAgency || store.myAgency.slug !== route.params.slug) {
    router.replace(`/agencies/${route.params.slug}`)
    return
  }

  resetSettings()

  await store.fetchMyPackages()
  await loadAllBookings()
  loadBookings()

  if (store.myAgency.tier !== 'basic') loadAnalytics()
})

watch(
  () => route.query.section,
  (s) => { if (s && s !== section.value) section.value = s },
)

const refreshAll = async () => {
  await loadAllBookings()
  await loadBookings()
  await store.fetchMyPackages()
}

const doConfirm = async (booking) => {
  confirmingId.value = booking.id
  try {
    await store.confirmBooking(booking.package?.slug, booking.id)
    $q.notify({
      color: 'positive', icon: 'check_circle', position: 'top',
      message: 'Approved — the traveller has 48 hours to pay.',
    })
    await refreshAll()
  } catch (err) {
    $q.notify({ color: 'negative', message: err.response?.data?.message || 'Could not approve that booking', icon: 'error', position: 'top' })
  } finally {
    confirmingId.value = null
  }
}

const doCancel = async (booking) => {
  cancellingId.value = booking.id
  try {
    await store.cancelBooking(booking.package?.slug, booking.id)
    $q.notify({ color: 'info', message: 'Booking cancelled', position: 'top' })
    await refreshAll()
  } catch (err) {
    $q.notify({ color: 'negative', message: err.response?.data?.message || 'Failed to cancel', icon: 'error', position: 'top' })
  } finally {
    cancellingId.value = null
  }
}

const resetSettings = () => {
  const a = store.myAgency || {}
  Object.assign(settings, {
    business_name: a.business_name || '',
    description:   a.description || '',
    contact_phone: a.contact_phone || '',
    contact_email: a.contact_email || '',
    logo:          a.logo || null,
    cover_image:   a.cover_image || null,
  })
}

const saveSettings = async () => {
  savingSettings.value = true
  try {
    await store.updateAgency(store.myAgency.slug, { ...settings })
    $q.notify({ color: 'positive', icon: 'check_circle', message: 'Agency updated', position: 'top' })
  } catch (err) {
    $q.notify({
      color: 'negative',
      icon: 'error',
      position: 'top',
      message: err.response?.data?.message || 'Could not save changes',
    })
  } finally {
    savingSettings.value = false
  }
}

const openEditPackage = (pkg) => {
  editingPkg.value = pkg
  Object.assign(editForm, {
    title: pkg.title,
    price_per_person: pkg.price_per_person,
    max_capacity: pkg.max_capacity,
    status: pkg.status,
    cover_image: pkg.cover_image || null,
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
    $q.notify({ color: 'positive', message: 'Package updated', icon: 'check', position: 'top' })
  } catch (err) {
    $q.notify({ color: 'negative', message: err.response?.data?.message || 'Failed', icon: 'error', position: 'top' })
  } finally {
    editSubmitting.value = false
  }
}

const confirmDelete = (pkg) => {
  $q.dialog({
    title: 'Delete package?',
    message: `"${pkg.title}" will be removed permanently. This cannot be undone.`,
    cancel: true,
    persistent: true,
    ok: { label: 'Delete', color: 'negative', unelevated: true, noCaps: true },
  }).onOk(async () => {
    try {
      await store.deletePackage(pkg.slug)
      $q.notify({ color: 'positive', message: 'Package deleted', icon: 'check', position: 'top' })
    } catch {
      $q.notify({ color: 'negative', message: 'Failed to delete', icon: 'error', position: 'top' })
    }
  })
}

const fmt = (n) => Number(n || 0).toLocaleString()
const fmtDate = (d) => {
  if (!d) return '—'
  const s = typeof d === 'string' && d.length <= 10 ? d + 'T00:00:00' : d
  return new Date(s).toLocaleDateString('en-PK', { day: 'numeric', month: 'short', year: 'numeric' })
}
const dayOf = (d) => (d ? new Date(d + 'T00:00:00').getDate() : '—')
const monthOf = (d) =>
  d ? new Date(d + 'T00:00:00').toLocaleDateString('en-PK', { month: 'short' }).toUpperCase() : ''
</script>

<style scoped>
.console-page { background: #faf8fc; padding: 24px 16px 64px; }
.console-shell { max-width: 1120px; margin: 0 auto; }

/* ── Header ─────────────────────────────────────────── */
.console-head { display: flex; align-items: center; gap: 14px; margin-bottom: 18px; }
.agency-mark {
  background: linear-gradient(135deg, #6a1b6a 0%, #4a148c 100%);
  color: #fff; font-weight: 700; font-size: 20px; flex-shrink: 0;
}
.head-text { flex: 1; min-width: 0; }
.head-title { margin: 0; font-size: 27px; font-weight: 600; letter-spacing: -0.02em; background: linear-gradient(95deg, #3d1152 0%, #6b2d5e 45%, #8e3d8a 100%);
  -webkit-background-clip: text; background-clip: text; color: transparent; line-height: 1.15; }
.head-meta { display: flex; align-items: center; gap: 8px; margin-top: 6px; flex-wrap: wrap; }

.chip {
  display: inline-flex; align-items: center; gap: 3px;
  padding: 2px 8px; border-radius: 999px;
  font-size: 10.5px; font-weight: 700; letter-spacing: 0.05em;
  background: #ede7f0; color: #6b5a75;
}
.chip--pro { background: #e3f2fd; color: #1565c0; }
.chip--premium { background: #f3e5f5; color: #6a1b9a; }
.chip--verified { background: #e8f5e9; color: #2e7d32; }

.storefront-link {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 12.5px; color: var(--q-primary); text-decoration: none; font-weight: 500;
}
.storefront-link:hover { text-decoration: underline; }

.verify-banner {
  background: #fff8e1; color: #8d6e00; border: 1px solid #ffe4a1;
  margin-bottom: 18px; font-size: 13.5px;
}

/* ── Section nav ────────────────────────────────────── */
.section-nav {
  display: flex; gap: 4px; margin-bottom: 20px;
  background: #f1ecf5; padding: 4px; border-radius: 11px; width: fit-content; flex-wrap: wrap;
}
.section-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 8px 15px; border: 0; border-radius: 8px; background: transparent;
  font: inherit; font-size: 13.5px; font-weight: 500; color: #6b5a75; cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}
.section-btn:hover { color: #2b1b33; }
.section-btn--active { background: #fff; color: var(--q-primary); font-weight: 600; box-shadow: 0 1px 3px rgba(43,27,51,0.1); }
.nav-count {
  min-width: 18px; padding: 0 5px; border-radius: 999px;
  background: #ef6c00; color: #fff; font-size: 10.5px; font-weight: 700; line-height: 18px; text-align: center;
}

/* ── KPIs ───────────────────────────────────────────── */
.kpi-grid {
  display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
  gap: 14px; margin-bottom: 18px;
}
.kpi { background: #fff; border: 1px solid #ece6f0; border-radius: 13px; padding: 16px 18px; }
.kpi--alert { border-color: #ffcc80; background: #fffaf2; }
.kpi-top { display: flex; align-items: center; gap: 7px; font-size: 12.5px; color: #7a6a82; font-weight: 500; }
.kpi-top .q-icon { color: var(--q-primary); }
.kpi--alert .kpi-top .q-icon { color: #ef6c00; }
.kpi-value { font-size: 25px; font-weight: 700; color: #2b1b33; margin-top: 8px; letter-spacing: -0.02em; }
.kpi-sub { font-size: 12px; color: #9b8aa5; margin-top: 3px; }

/* ── Panels ─────────────────────────────────────────── */
.panel {
  background: #fff; border: 1px solid #ece6f0; border-radius: 13px;
  padding: 16px 18px; margin-bottom: 18px;
}
.panel-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.panel-title { display: flex; align-items: center; gap: 8px; font-size: 14.5px; font-weight: 600; color: #2b1b33; }
.panel-title .q-icon { color: var(--q-primary); }
.panel-badge { background: #fff3e0; color: #ef6c00; font-size: 11.5px; font-weight: 700; padding: 2px 9px; border-radius: 999px; }

.two-col { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; }

.empty-inline {
  display: flex; flex-direction: column; align-items: center; gap: 6px;
  padding: 26px 10px; color: #b0a3b8; font-size: 13px; text-align: center;
}

/* ── Action queue ───────────────────────────────────── */
.queue { display: flex; flex-direction: column; }
.queue-row { display: flex; align-items: center; gap: 12px; padding: 11px 0; border-top: 1px solid #f4eff7; }
.queue-row:first-child { border-top: 0; }
.traveler-mark { background: #f0e6f5; color: #6a1b6a; font-weight: 700; }
.queue-main { flex: 1; min-width: 0; }
.queue-name { font-size: 13.5px; font-weight: 600; color: #2b1b33; }
.queue-sub { font-size: 12px; color: #8a7a92; }
.queue-amount { font-size: 13.5px; font-weight: 600; color: #2b1b33; white-space: nowrap; }
.queue-actions { display: flex; gap: 6px; }

/* ── Departures / occupancy ─────────────────────────── */
.dep-list, .act-list { display: flex; flex-direction: column; }
.dep-row { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-top: 1px solid #f4eff7; }
.dep-row:first-child { border-top: 0; }
.dep-date {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  width: 44px; height: 44px; border-radius: 10px; background: #f5eef8; flex-shrink: 0;
}
.dep-day { font-size: 16px; font-weight: 700; color: #6a1b6a; line-height: 1; }
.dep-mon { font-size: 9.5px; font-weight: 600; color: #9b7aa5; letter-spacing: 0.05em; }
.dep-main { flex: 1; min-width: 0; }
.dep-title { font-size: 13.5px; font-weight: 600; color: #2b1b33; margin-bottom: 5px; }

.occupancy { display: flex; align-items: center; gap: 8px; }
.occupancy-bar { flex: 1; height: 6px; border-radius: 999px; background: #f0eaf4; overflow: hidden; }
.occupancy-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #7b1fa2, #4a148c); }
.occupancy-text { font-size: 11.5px; color: #8a7a92; font-weight: 500; white-space: nowrap; }

/* ── Activity ───────────────────────────────────────── */
.act-row { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-top: 1px solid #f4eff7; }
.act-row:first-child { border-top: 0; }
.dot { width: 8px; height: 8px; border-radius: 50%; background: #bdbdbd; flex-shrink: 0; }
.dot--pending { background: #ff9800; }
.dot--confirmed { background: #2e7d32; }
.dot--cancelled { background: #c62828; }
.dot--completed { background: #1565c0; }
.act-main { flex: 1; min-width: 0; }
.act-name { font-size: 13px; font-weight: 600; color: #2b1b33; }
.act-sub { font-size: 11.5px; color: #8a7a92; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.act-right { text-align: right; }
.act-amount { font-size: 12.5px; font-weight: 600; color: #2b1b33; }
.act-date { font-size: 11px; color: #9b8aa5; text-transform: capitalize; }

/* ── Packages ───────────────────────────────────────── */
.pkg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; }
.pkg-card {
  background: #fff; border: 1px solid #ece6f0; border-radius: 13px; overflow: hidden;
  transition: box-shadow 0.16s ease, transform 0.16s ease;
}
.pkg-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(43,27,51,0.09); }
.pkg-cover {
  position: relative; height: 108px;
  background: linear-gradient(135deg, #7b1fa2 0%, #4a148c 100%);
  display: flex; align-items: center; justify-content: center;
}
.pkg-initial { font-size: 34px; font-weight: 700; color: rgba(255,255,255,0.55); }
.pkg-status {
  position: absolute; top: 9px; left: 9px; padding: 2px 8px; border-radius: 999px;
  font-size: 10px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
  background: rgba(255,255,255,0.92); color: #6b5a75;
}
.pkg-status--published { color: #2e7d32; }
.pkg-status--draft { color: #757575; }
.pkg-status--closed { color: #ef6c00; }
.pkg-status--archived { color: #c62828; }
.pkg-menu { position: absolute; top: 4px; right: 4px; }
.pkg-body { padding: 13px 15px 15px; }
.pkg-title { font-size: 14px; font-weight: 600; color: #2b1b33; line-height: 1.3; }
.pkg-meta { display: flex; align-items: center; gap: 4px; font-size: 12px; color: #8a7a92; margin-top: 4px; }
.pkg-meta .sep { margin: 0 2px; }
.pkg-foot { display: flex; align-items: center; justify-content: space-between; margin-top: 11px; }
.pkg-price { font-size: 14px; font-weight: 700; color: var(--q-primary); }
.pkg-views { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: #9b8aa5; }

/* ── Bookings table ─────────────────────────────────── */
.filter-row { display: flex; gap: 7px; margin-bottom: 16px; flex-wrap: wrap; }
.filter-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 14px; border: 1px solid #e5dced; border-radius: 999px; background: #fff;
  font: inherit; font-size: 13px; color: #6b5a75; cursor: pointer; transition: all 0.15s ease;
}
.filter-btn:hover { border-color: #c9b3d6; }
.filter-btn--active { background: var(--q-primary); border-color: var(--q-primary); color: #fff; font-weight: 500; }
.filter-count {
  background: rgba(0,0,0,0.08); border-radius: 999px; padding: 0 6px; font-size: 11px; font-weight: 600;
}
.filter-btn--active .filter-count { background: rgba(255,255,255,0.25); }

.booking-table { background: #fff; border: 1px solid #ece6f0; border-radius: 13px; overflow: hidden; }
.bt-head, .bt-row {
  display: grid;
  grid-template-columns: 1.7fr 1.5fr 0.6fr 1fr 1fr 1.4fr;
  gap: 12px; align-items: center; padding: 12px 16px;
}
.bt-head {
  background: #faf7fc; font-size: 11px; font-weight: 700; letter-spacing: 0.05em;
  text-transform: uppercase; color: #9b8aa5; border-bottom: 1px solid #f0eaf4;
}
.bt-row { border-bottom: 1px solid #f4eff7; }
.bt-row:last-child { border-bottom: 0; }
.ta-c { text-align: center; }
.ta-r { text-align: right; }
.bt-traveler { display: flex; align-items: center; gap: 10px; min-width: 0; }
.bt-name { font-size: 13.5px; font-weight: 600; color: #2b1b33; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bt-contact { font-size: 11.5px; color: #9b8aa5; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bt-pkg { font-size: 13px; color: #4a3d52; min-width: 0; }
.bt-date { font-size: 11.5px; color: #9b8aa5; }
.bt-num { font-size: 13.5px; font-weight: 600; color: #2b1b33; }
.bt-amount { font-size: 13.5px; font-weight: 700; color: #2b1b33; white-space: nowrap; }
.bt-note {
  font-size: 11px; color: #9b8aa5; margin-top: 4px;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 150px;
}
.bt-actions { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap; }

.status-pill {
  display: inline-block; padding: 3px 10px; border-radius: 999px;
  font-size: 11px; font-weight: 600; text-transform: capitalize;
  background: #eee; color: #616161;
}
.status-pill--pending { background: #fff3e0; color: #ef6c00; }
.status-pill--confirmed { background: #e8f5e9; color: #2e7d32; }
.status-pill--cancelled { background: #ffebee; color: #c62828; }
.status-pill--completed { background: #e3f2fd; color: #1565c0; }

/* ── Analytics bars ─────────────────────────────────── */
.bars { display: flex; flex-direction: column; gap: 12px; }
.bar-row { display: grid; grid-template-columns: 1.2fr 2fr auto; gap: 12px; align-items: center; }
.bar-label { font-size: 13px; color: #4a3d52; }
.bar-track { height: 9px; border-radius: 999px; background: #f0eaf4; overflow: hidden; }
.bar-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #7b1fa2, #4a148c); }
.bar-value { font-size: 12.5px; font-weight: 600; color: #2b1b33; white-space: nowrap; }

/* ── Empty / loading ────────────────────────────────── */
.loading-pad { display: flex; justify-content: center; padding: 56px 0; }

/* ── Settings ───────────────────────────────────────── */
.brand-row { display: flex; gap: 24px; flex-wrap: wrap; align-items: flex-start; }
.brand-field { display: flex; flex-direction: column; gap: 7px; }
.brand-field--grow { flex: 1; min-width: 260px; }
.field-label { font-size: 12.5px; font-weight: 600; color: #4a3d52; }
.field-hint { font-size: 11.5px; color: #9b8aa5; }

.settings-actions {
  display: flex; justify-content: flex-end; gap: 10px;
  margin-top: 20px; padding-top: 16px; border-top: 1px solid #f0eaf4;
}
.empty-block, .upgrade-card {
  display: flex; flex-direction: column; align-items: center; text-align: center;
  background: #fff; border: 1px solid #ece6f0; border-radius: 13px; padding: 48px 24px;
  color: #b0a3b8;
}
.empty-title { font-size: 16px; font-weight: 600; color: #2b1b33; margin-top: 12px; }
.empty-text { font-size: 13px; color: #8a7a92; max-width: 380px; margin: 6px 0 16px; line-height: 1.5; }
.hint { font-size: 11.5px; color: #b0a3b8; margin-top: 10px; }

@media (max-width: 900px) {
  .bt-head { display: none; }
  .bt-row { grid-template-columns: 1fr 1fr; gap: 8px; }
  .bt-actions { justify-content: flex-start; grid-column: 1 / -1; }
}
@media (max-width: 599px) {
  .console-page { padding: 16px 12px 48px; }
  .head-title { font-size: 22px; }
  .console-head { flex-wrap: wrap; }
}
</style>
