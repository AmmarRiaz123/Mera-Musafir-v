<template>
  <q-layout view="lHh Lpr lFf">
    <q-header class="app-header">
      <q-toolbar class="app-toolbar">
        <q-btn flat dense round icon="menu" aria-label="Menu" @click="toggleDrawer" />

        <router-link :to="homeLink" class="brand-link q-ml-xs">
          <AppLogo :size="30" :word-size="18" light />
        </router-link>

        <q-space />

        <!-- Agencies create packages, not traveller trips. -->
        <q-btn
          v-if="myAgency"
          flat dense round to="/packages/create" icon="add_circle_outline" aria-label="New Package"
        >
          <q-tooltip>New Package</q-tooltip>
        </q-btn>
        <q-btn
          v-else
          flat dense round to="/trips/create" icon="add_circle_outline" aria-label="Create Trip"
        >
          <q-tooltip>Create Trip</q-tooltip>
        </q-btn>

        <q-btn flat dense round icon="notifications" aria-label="Notifications" class="q-ml-xs">
          <q-badge v-if="notificationStore.count > 0" color="deep-purple" floating>
            {{ notificationStore.count }}
          </q-badge>
          <q-tooltip>Message requests</q-tooltip>

          <q-menu anchor="bottom right" self="top right" style="min-width: 320px; max-width: 360px">
            <div class="text-subtitle2 text-weight-bold q-px-md q-pt-md q-pb-sm text-grey-9">
              Message Requests
            </div>
            <q-separator />

            <div
              v-if="notificationStore.requests.length === 0"
              class="text-center q-py-xl text-grey-5"
            >
              <q-icon name="notifications_none" size="2.5em" />
              <div class="text-caption q-mt-xs">No new requests</div>
            </div>

            <q-list v-else separator>
              <q-item v-for="req in notificationStore.requests" :key="req.id">
                <q-item-section avatar>
                  <q-avatar size="40px" color="grey-3" text-color="grey-8">
                    <img v-if="req.requester.avatar" :src="req.requester.avatar" />
                    <span v-else class="text-weight-bold">
                      {{ req.requester.name?.[0]?.toUpperCase() }}
                    </span>
                  </q-avatar>
                </q-item-section>
                <q-item-section>
                  <q-item-label class="text-weight-medium">
                    {{ req.requester.name }}
                    <q-icon
                      v-if="req.requester.is_verified"
                      name="verified"
                      color="deep-purple"
                      size="16px"
                    />
                  </q-item-label>
                  <q-item-label caption>wants to message you</q-item-label>
                  <q-item-label
                    v-if="req.message"
                    class="text-body2 text-grey-8 q-mt-xs"
                    style="white-space: normal"
                  >
                    “{{ req.message }}”
                  </q-item-label>
                  <q-item-label caption class="mr-accept-note">
                    Accepting opens the chat and follows them back.
                  </q-item-label>
                  <div class="q-mt-xs q-gutter-x-sm">
                    <q-btn
                      dense
                      unelevated
                      size="sm"
                      color="deep-purple"
                      label="Accept"
                      :loading="acting === req.id"
                      @click="acceptRequest(req)"
                    />
                    <q-btn
                      dense
                      flat
                      size="sm"
                      color="grey-7"
                      label="Ignore"
                      :loading="acting === req.id"
                      @click="ignoreRequest(req)"
                    />
                  </div>
                </q-item-section>
              </q-item>
            </q-list>

            <q-separator />
            <q-item clickable v-close-popup to="/privacy" dense class="text-grey-7">
              <q-item-section class="text-caption">Manage who can message you</q-item-section>
              <q-item-section side><q-icon name="chevron_right" size="18px" /></q-item-section>
            </q-item>
          </q-menu>
        </q-btn>

        <q-btn flat dense round :to="`/profile`" class="q-ml-xs">
          <q-avatar size="32px">
            <img v-if="authStore.user?.avatar" :src="authStore.user.avatar" />
            <q-icon v-else name="person" />
          </q-avatar>
        </q-btn>
      </q-toolbar>
    </q-header>

    <q-drawer v-model="drawerOpen" bordered :width="248" :breakpoint="1023" class="app-drawer">
      <!-- Identity, not the app name again — the brand already sits in the header. -->
      <router-link
        v-if="myAgency"
        :to="`/agencies/${myAgency.slug}`"
        class="identity"
      >
        <q-avatar size="40px" class="identity-avatar identity-avatar--agency">
          <img v-if="myAgency.logo" :src="myAgency.logo" />
          <span v-else>{{ myAgency.business_name?.[0]?.toUpperCase() }}</span>
        </q-avatar>
        <div class="identity-text">
          <span class="identity-name">{{ myAgency.business_name }}</span>
          <span class="identity-meta">
            <span class="tier-chip">{{ myAgency.tier?.toUpperCase() }}</span>
            <q-icon v-if="myAgency.is_verified" name="verified" size="13px" color="teal-4" />
          </span>
        </div>
      </router-link>

      <router-link v-else to="/profile" class="identity">
        <q-avatar size="40px" class="identity-avatar">
          <img v-if="authStore.user?.avatar" :src="authStore.user.avatar" />
          <span v-else>{{ authStore.user?.name?.[0]?.toUpperCase() }}</span>
        </q-avatar>
        <div class="identity-text">
          <span class="identity-name">{{ authStore.user?.name }}</span>
          <span class="identity-sub">View profile</span>
        </div>
        <q-icon name="chevron_right" size="17px" class="identity-arrow" />
      </router-link>

      <q-list padding class="nav-list">
        <!-- ── Agency console ───────────────────────────────────── -->
        <template v-if="myAgency">
          <q-item-label header class="text-caption text-grey-6">MY AGENCY</q-item-label>

          <q-item clickable v-ripple :to="agencyLink('overview')">
            <q-item-section avatar><q-icon name="dashboard" /></q-item-section>
            <q-item-section>Dashboard</q-item-section>
          </q-item>

          <q-item clickable v-ripple :to="agencyLink('packages')">
            <q-item-section avatar><q-icon name="inventory_2" /></q-item-section>
            <q-item-section>Packages</q-item-section>
          </q-item>

          <q-item clickable v-ripple :to="agencyLink('bookings')">
            <q-item-section avatar><q-icon name="book_online" /></q-item-section>
            <q-item-section>Bookings</q-item-section>
            <q-item-section side v-if="agencyStore.pendingBookings > 0">
              <q-badge color="orange" :label="agencyStore.pendingBookings" rounded />
            </q-item-section>
          </q-item>

          <q-item clickable v-ripple :to="agencyLink('analytics')">
            <q-item-section avatar><q-icon name="insights" /></q-item-section>
            <q-item-section>Analytics</q-item-section>
          </q-item>

          <q-item clickable v-ripple to="/subscription">
            <q-item-section avatar><q-icon name="workspace_premium" /></q-item-section>
            <q-item-section>Plan &amp; billing</q-item-section>
            <q-item-section side v-if="myAgency?.tier === 'basic'">
              <q-badge color="deep-purple" label="Upgrade" rounded />
            </q-item-section>
          </q-item>

          <q-item clickable v-ripple to="/packages/create">
            <q-item-section avatar><q-icon name="add_box" /></q-item-section>
            <q-item-section>New Package</q-item-section>
          </q-item>

          <q-item clickable v-ripple :to="`/agencies/${myAgency.slug}`">
            <q-item-section avatar><q-icon name="storefront" /></q-item-section>
            <q-item-section>My Storefront</q-item-section>
            <q-item-section side><q-icon name="open_in_new" size="14px" color="grey-5" /></q-item-section>
          </q-item>

          <q-item clickable v-ripple to="/community">
            <q-item-section avatar><q-icon name="forum" /></q-item-section>
            <q-item-section>Community</q-item-section>
          </q-item>

          <q-separator class="q-my-sm" />
        </template>

        <!-- Traveller nav — hidden for agency accounts, which can't join or
             create traveller trips and don't book packages. -->
        <template v-if="!myAgency">
          <q-item clickable v-ripple to="/" exact>
            <q-item-section avatar><q-icon name="home" /></q-item-section>
            <q-item-section>Home</q-item-section>
          </q-item>

          <q-item clickable v-ripple to="/destinations">
            <q-item-section avatar><q-icon name="travel_explore" /></q-item-section>
            <q-item-section>Explore</q-item-section>
          </q-item>

          <q-separator class="q-my-sm" />

          <q-item-label header class="text-caption text-grey-6">TRIPS</q-item-label>

          <q-item clickable v-ripple to="/trips">
            <q-item-section avatar><q-icon name="hiking" /></q-item-section>
            <q-item-section>Browse Trips</q-item-section>
          </q-item>

          <q-item clickable v-ripple to="/trips/create">
            <q-item-section avatar><q-icon name="add_circle_outline" /></q-item-section>
            <q-item-section>Create Trip</q-item-section>
          </q-item>

          <q-item clickable v-ripple to="/my-trips">
            <q-item-section avatar><q-icon name="luggage" /></q-item-section>
            <q-item-section>My Trips</q-item-section>
          </q-item>

          <q-separator class="q-my-sm" />

          <q-item-label header class="text-caption text-grey-6">DISCOVER</q-item-label>

          <q-item clickable v-ripple to="/packages">
            <q-item-section avatar><q-icon name="card_travel" /></q-item-section>
            <q-item-section>Packages</q-item-section>
          </q-item>

          <q-item clickable v-ripple to="/agencies">
            <q-item-section avatar><q-icon name="business" /></q-item-section>
            <q-item-section>Agencies</q-item-section>
          </q-item>

          <q-separator class="q-my-sm" />

          <q-item-label header class="text-caption text-grey-6">SOCIAL</q-item-label>

          <q-item clickable v-ripple to="/community">
            <q-item-section avatar><q-icon name="forum" /></q-item-section>
            <q-item-section>Community</q-item-section>
          </q-item>

          <q-item clickable v-ripple to="/people">
            <q-item-section avatar><q-icon name="people" /></q-item-section>
            <q-item-section>People</q-item-section>
          </q-item>
        </template>

        <q-item-label v-if="myAgency" header class="text-caption text-grey-6">ACCOUNT</q-item-label>

        <q-item clickable v-ripple to="/messages">
          <q-item-section avatar><q-icon name="chat" /></q-item-section>
          <q-item-section>Messages</q-item-section>
          <q-item-section side v-if="unreadCount > 0">
            <q-badge color="deep-purple" :label="unreadCount" rounded />
          </q-item-section>
        </q-item>

        <q-separator v-if="!myAgency" class="q-my-sm" />

        <q-item-label v-if="!myAgency" header class="text-caption text-grey-6">ACCOUNT</q-item-label>

        <q-item clickable v-ripple to="/profile">
          <q-item-section avatar><q-icon name="person" /></q-item-section>
          <q-item-section>Profile</q-item-section>
        </q-item>

        <!-- Traveller-only: agencies sell packages, they don't book them.
             Points at the My Trips tab so bookings live in exactly one place. -->
        <q-item v-if="!myAgency" clickable v-ripple to="/my-trips?tab=packages">
          <q-item-section avatar><q-icon name="confirmation_number" /></q-item-section>
          <q-item-section>My Bookings</q-item-section>
        </q-item>

        <q-item clickable v-ripple to="/transactions">
          <q-item-section avatar><q-icon name="receipt_long" /></q-item-section>
          <q-item-section>Transactions</q-item-section>
        </q-item>

        <q-item clickable v-ripple @click="handleLogout">
          <q-item-section avatar><q-icon name="logout" color="negative" /></q-item-section>
          <q-item-section><span class="text-negative">Logout</span></q-item-section>
        </q-item>
      </q-list>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>

    <!-- Send a message request to a user who limits who can DM them -->
    <q-dialog
      class="request-dialog"
      transition-show="jump-up"
      transition-hide="jump-down"
      :model-value="!!notificationStore.promptUser"
      @update:model-value="(v) => { if (!v) notificationStore.cancelPrompt() }"
    >
      <q-card class="mr-card">
        <header class="mr-head">
          <q-avatar size="46px" class="mr-avatar">
            <img v-if="notificationStore.promptUser?.avatar" :src="notificationStore.promptUser.avatar" />
            <span v-else>{{ notificationStore.promptUser?.name?.[0]?.toUpperCase() }}</span>
          </q-avatar>
          <div class="mr-head-text">
            <div class="mr-title">Knock first</div>
            <div class="mr-sub">
              {{ firstName }} keeps their inbox quiet. Send a note and they can let you in.
            </div>
          </div>
          <q-btn flat round dense size="sm" icon="close" color="grey-7" @click="notificationStore.cancelPrompt()" />
        </header>

        <div class="mr-body">
          <div class="mr-wrap">
            <q-input
              v-model="requestBody"
              class="mr-input"
              type="textarea"
              borderless
              autogrow
              :rows="3"
              :maxlength="MESSAGE_MAX"
              :placeholder="`Hi ${firstName} — say why you're reaching out. A real reason goes a long way.`"
              autofocus
              @paste="onRequestPaste"
            />
          </div>

          <transition name="limit-fade">
            <div v-if="requestAtLimit" class="limit-banner row items-center no-wrap q-mt-sm">
              <q-icon name="info" size="16px" class="q-mr-xs" />
              <span>
                You've reached the <strong>{{ MESSAGE_MAX.toLocaleString() }}</strong>-character
                limit.
              </span>
            </div>
          </transition>

          <p class="mr-follow-note">
            <q-icon name="person_add_alt" size="13px" />
            Sending this also follows {{ firstName }}, so they can write back once
            they accept.
          </p>

          <div class="mr-foot">
            <span class="mr-note">
              <q-icon name="lock_outline" size="13px" />
              They see this before deciding. Nothing sends until they accept.
            </span>
            <span class="mr-count">{{ requestBody.length }}/{{ MESSAGE_MAX.toLocaleString() }}</span>
          </div>
        </div>

        <footer class="mr-actions">
          <q-btn flat no-caps color="grey-7" label="Not now" @click="notificationStore.cancelPrompt()" />
          <q-btn
            unelevated rounded no-caps
            color="deep-purple"
            icon="send"
            label="Send request"
            :loading="sendingRequest"
            :disable="!requestBody.trim()"
            @click="submitRequest"
          />
        </footer>
      </q-card>
    </q-dialog>
  </q-layout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from 'src/stores/authStore'
import { useSocialStore } from 'src/stores/socialStore'
import { useNotificationStore } from 'src/stores/notificationStore'
import { useAgencyStore } from 'src/stores/agencyStore'
import { MESSAGE_MAX, clampMessage, overflowFromPaste, notifyTrimmed } from 'src/utils/messageLimit'
import AppLogo from 'components/AppLogo.vue'

const $q = useQuasar()
const router = useRouter()
const authStore = useAuthStore()
const socialStore = useSocialStore()
const notificationStore = useNotificationStore()
const agencyStore = useAgencyStore()

const myAgency = computed(() => agencyStore.myAgency)
const agencyLink = (section) => `/agencies/${myAgency.value.slug}/dashboard?section=${section}`

// An agency's "home" is their console — the traveller feed is meaningless to them.
const homeLink = computed(() =>
  myAgency.value ? `/agencies/${myAgency.value.slug}/dashboard?section=overview` : '/',
)

// Collapsible on every screen size, and the choice sticks between visits.
const NAV_KEY = 'nav.open'
const savedNav = localStorage.getItem(NAV_KEY)
const drawerOpen = ref(savedNav === null ? $q.screen.gt.sm : savedNav === 'true')
const acting = ref(null)
const requestBody = ref('')
const firstName = computed(
  () => notificationStore.promptUser?.name?.split(' ')[0] ?? 'They',
)
const sendingRequest = ref(false)

const unreadCount = computed(() => socialStore.totalUnread)
const requestAtLimit = computed(() => requestBody.value.length >= MESSAGE_MAX)

const onRequestPaste = (e) => {
  const pasted = e.clipboardData?.getData('text') ?? ''
  const el = e.target
  const selectionLength = (el?.selectionEnd ?? 0) - (el?.selectionStart ?? 0)
  const removed = overflowFromPaste(pasted, requestBody.value.length, selectionLength)
  if (removed > 0) notifyTrimmed($q, removed)
}

watch(requestBody, (val) => {
  if (val && val.length > MESSAGE_MAX) requestBody.value = clampMessage(val)
})

onMounted(async () => {
  if (!authStore.user) return

  socialStore.fetchConversations()
  notificationStore.fetchRequests()

  // Agency owners get a business console in the drawer instead of the
  // traveler-only nav, so resolve their agency up front.
  if (authStore.user.type === 'agency') {
    const agency = await agencyStore.fetchMyAgency()
    if (agency?.slug) agencyStore.fetchPendingCount(agency.slug)
  }
})

const acceptRequest = async (req) => {
  acting.value = req.id
  try {
    const conversationId = await notificationStore.accept(req)
    await socialStore.fetchConversations()
    router.push(`/messages/${conversationId}`)
  } catch (e) {
    console.error('acceptRequest failed', e)
    // A 404 means the request was already handled/removed — just refresh.
    if (e.response?.status === 404) {
      await notificationStore.fetchRequests()
    }
    const detail = e.response
      ? `${e.response.status}: ${e.response.data?.message || 'server error'}`
      : `network/JS error: ${e.message}`
    $q.notify({
      type: 'negative',
      message: `Could not accept — ${detail}`,
      position: 'top',
      timeout: 8000,
    })
  } finally {
    acting.value = null
  }
}

const submitRequest = async () => {
  sendingRequest.value = true
  try {
    await notificationStore.sendRequest(requestBody.value)
    requestBody.value = ''
    $q.notify({ type: 'info', message: 'Message request sent', position: 'top' })
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to send request', position: 'top' })
  } finally {
    sendingRequest.value = false
  }
}

const ignoreRequest = async (req) => {
  acting.value = req.id
  try {
    await notificationStore.dismiss(req.id)
  } catch (e) {
    console.error('ignoreRequest failed', e)
    $q.notify({
      type: 'negative',
      message: e.response?.data?.message || 'Could not dismiss the request',
      position: 'top',
    })
  } finally {
    acting.value = null
  }
}

const toggleDrawer = () => {
  drawerOpen.value = !drawerOpen.value
  localStorage.setItem(NAV_KEY, String(drawerOpen.value))
}

const handleLogout = async () => {
  await authStore.logout()
  router.push('/login')
}
</script>

<style scoped>
/* ── App bar ────────────────────────────────────────── */
.app-header {
  background: linear-gradient(100deg, #4a148c 0%, #6a1b6a 100%);
  color: #fff;
  box-shadow: 0 1px 0 rgba(255, 255, 255, 0.08), 0 2px 12px rgba(43, 27, 51, 0.18);
}
.app-toolbar { min-height: 58px; padding-inline: 8px; }
.brand-link { text-decoration: none; display: inline-flex; align-items: center; }

/* ── Drawer identity ────────────────────────────────── */
.app-drawer { background: #fcfafd; }

.identity {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 14px 16px;
  text-decoration: none;
  color: inherit;
  border-bottom: 1px solid #efe9f3;
  transition: background 0.15s ease;
}
.identity:hover { background: #f5eef8; }

.identity-avatar {
  background: linear-gradient(135deg, #7b1fa2, #4a148c);
  color: #fff;
  font-weight: 700;
  font-size: 16px;
  flex-shrink: 0;
}
.identity-avatar--agency { border-radius: 10px; }

.identity-text { display: flex; flex-direction: column; min-width: 0; flex: 1; gap: 2px; }
.identity-name {
  font-size: 13.5px;
  font-weight: 600;
  color: #2b1b33;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.identity-sub { font-size: 11.5px; color: #9b8aa5; }
.identity-meta { display: flex; align-items: center; gap: 5px; }
.identity-arrow { color: #c3b3cc; flex-shrink: 0; }

.tier-chip {
  display: inline-block;
  padding: 1px 6px;
  border-radius: 4px;
  background: #ede0f4;
  color: #6a3d7d;
  font-size: 9.5px;
  font-weight: 700;
  letter-spacing: 0.06em;
}

/* ── Nav items ──────────────────────────────────────── */
.nav-list { padding-top: 6px; }
.nav-list :deep(.q-item__label--header) {
  padding: 14px 16px 6px;
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: 0.09em;
  color: #a596ad;
}
.nav-list :deep(.q-item) {
  margin: 1px 8px;
  padding: 8px 10px;
  min-height: 40px;
  border-radius: 9px;
  font-size: 13.5px;
  color: #4a3d52;
}
.nav-list :deep(.q-item__section--avatar) { min-width: 34px; padding-right: 0; }
.nav-list :deep(.q-item .q-icon) { font-size: 20px; color: #8a7a92; }
.nav-list :deep(.q-item:hover) { background: #f3ecf7; }

/* Active route gets a tinted pill instead of only coloured text. */
.nav-list :deep(.q-router-link--exact-active),
.nav-list :deep(.q-item.q-router-link--active) {
  background: #f0e4f7;
  color: var(--q-primary);
  font-weight: 600;
}
.nav-list :deep(.q-router-link--exact-active .q-icon),
.nav-list :deep(.q-item.q-router-link--active .q-icon) { color: var(--q-primary); }

/* Shown only once the character cap is actually reached. */
.limit-banner {
  padding: 8px 12px;
  border-radius: 6px;
  background: linear-gradient(90deg, #f3e5f5 0%, #ede7f6 100%);
  color: #5e35b1;
  font-size: 12.5px;
  line-height: 1.35;
}
.limit-banner strong { font-weight: 600; }

/* ── Message request composer ───────────────────────── */
.mr-card { width: 460px; max-width: 94vw; border-radius: 16px; overflow: hidden; }

.mr-head {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 15px 14px 14px;
  border-bottom: 1px solid #f4eff7;
  background: linear-gradient(180deg, #f7f0fb, #fff);
}
.mr-avatar {
  flex-shrink: 0; background: linear-gradient(135deg, #7b1fa2, #4a148c);
  color: #fff; font-weight: 700; font-size: 17px;
  box-shadow: 0 2px 8px rgba(74, 20, 140, 0.22);
}
.mr-head-text { flex: 1; min-width: 0; }
.mr-title {
  font-size: 17px; font-weight: 700; line-height: 1.2;
  background: linear-gradient(135deg, #4a148c, #7b1fa2);
  -webkit-background-clip: text; background-clip: text; color: transparent;
}
.mr-sub { font-size: 12.5px; color: #7a6a82; margin-top: 3px; }

.mr-body { padding: 14px; }
.mr-wrap {
  padding: 4px 12px;
  border: 1px solid #ece6f0; border-radius: 12px; background: #fcfafd;
  transition: border-color 0.15s ease, background 0.15s ease;
}
.mr-wrap:focus-within { border-color: #c9b3d6; background: #fff; }
.mr-input { font-size: 13.5px; }
/* Same autogrow trap as the composer — cap it or Send walks off the screen. */
.mr-input :deep(textarea) { max-height: 26vh; overflow-y: auto; }

.mr-follow-note {
  display: flex; align-items: flex-start; gap: 5px;
  margin: 9px 0 0; padding: 7px 9px;
  border-radius: 9px; background: #f7f3fa; border: 1px solid #f0eaf4;
  font-size: 11.5px; line-height: 1.4; color: #8a7a93;
}
.mr-follow-note .q-icon { margin-top: 1px; flex-shrink: 0; }

.mr-accept-note {
  margin-top: 4px; font-size: 11px; color: #a99bb2; white-space: normal;
}

.mr-foot {
  display: flex; align-items: center; justify-content: space-between; gap: 10px;
  margin-top: 9px;
}
.mr-note {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 11.5px; color: #9b8aa5;
}
.mr-count { font-size: 11px; color: #b0a3b8; flex-shrink: 0; }

.mr-actions {
  display: flex; justify-content: flex-end; gap: 8px;
  padding: 11px 14px 13px;
  border-top: 1px solid #f4eff7; background: #fcfafd;
}

.limit-fade-enter-active,
.limit-fade-leave-active { transition: opacity 0.18s ease, transform 0.18s ease; }
.limit-fade-enter-from,
.limit-fade-leave-to { opacity: 0; transform: translateY(4px); }
</style>
