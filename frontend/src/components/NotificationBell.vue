<template>
  <q-btn flat dense round icon="notifications" aria-label="Notifications" class="q-ml-xs" @click="onOpen">
    <q-badge v-if="store.count > 0" class="bell-badge" floating>
      {{ store.count > 99 ? '99+' : store.count }}
    </q-badge>

    <q-menu
      anchor="bottom right" self="top right"
      class="notif-menu" :offset="[0, 8]"
      @before-show="onOpen"
    >
      <div class="nf-card">
        <header class="nf-head">
          <span class="nf-title">Notifications</span>
          <q-btn
            v-if="store.count > 0"
            flat dense no-caps size="sm" class="nf-clear"
            label="Mark all read" @click="markAll"
          />
        </header>

        <div class="nf-body">
          <!-- Message requests keep their inline accept/ignore -->
          <template v-if="store.requests.length">
            <div class="nf-section">Message requests</div>
            <div v-for="req in store.requests" :key="`r${req.id}`" class="nf-req">
              <q-avatar size="38px" class="nf-req-avatar">
                <img v-if="req.requester.avatar" :src="req.requester.avatar" />
                <span v-else>{{ req.requester.name?.[0]?.toUpperCase() }}</span>
              </q-avatar>
              <div class="nf-req-main">
                <div class="nf-req-name">
                  {{ req.requester.name }}
                  <q-icon v-if="req.requester.is_verified" name="verified" size="13px" color="deep-purple" />
                </div>
                <div class="nf-req-sub">wants to message you</div>
                <div v-if="req.message" class="nf-req-msg">“{{ req.message }}”</div>
                <div class="nf-req-actions">
                  <q-btn
                    dense unelevated rounded no-caps size="sm" color="deep-purple"
                    label="Accept" :loading="acting === req.id" @click="accept(req)"
                  />
                  <q-btn
                    dense flat no-caps size="sm" color="grey-7"
                    label="Ignore" :loading="acting === req.id" @click="ignore(req)"
                  />
                </div>
              </div>
            </div>
            <div v-if="store.items.length" class="nf-divider" />
          </template>

          <!-- The feed -->
          <div v-if="store.itemsLoading && !store.items.length" class="nf-state">
            <q-spinner-dots color="primary" size="26px" />
          </div>

          <div v-else-if="!store.items.length && !store.requests.length" class="nf-state">
            <q-icon name="notifications_none" size="34px" />
            <div class="nf-empty-title">You're all caught up</div>
            <div class="nf-empty-text">Follows, comments, messages and bookings show up here.</div>
          </div>

          <button
            v-for="n in store.items"
            :key="n.id"
            type="button"
            class="nf-item"
            :class="[`nf-item--${n.type}`, { 'nf-item--unread': !n.is_read }]"
            @click="open(n)"
          >
            <span class="nf-badge" :class="`nf-badge--${n.type}`">
              <q-icon :name="ICONS[n.type] || 'notifications'" size="16px" />
            </span>

            <q-avatar v-if="n.actor" size="34px" class="nf-avatar">
              <img v-if="n.actor.avatar" :src="n.actor.avatar" />
              <span v-else>{{ n.actor.name?.[0]?.toUpperCase() }}</span>
            </q-avatar>

            <span class="nf-text">
              <span class="nf-item-title">{{ n.title }}</span>
              <span v-if="n.body" class="nf-item-body">{{ n.body }}</span>
              <span class="nf-time">{{ timeAgo(n.created_at) }}</span>
            </span>

            <span v-if="!n.is_read" class="nf-unread-dot" />
          </button>
        </div>
      </div>
    </q-menu>
  </q-btn>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useNotificationStore } from 'src/stores/notificationStore'

const router = useRouter()
const $q = useQuasar()
const store = useNotificationStore()
const acting = ref(null)

// Each type reads at a glance from its icon + accent colour (styles below).
const ICONS = {
  follow: 'person_add',
  comment: 'mode_comment',
  like: 'favorite',
  message: 'forum',
  booking_request: 'luggage',
  booking_approved: 'verified_user',
  booking_paid: 'payments',
  trip_join: 'groups',
}

const onOpen = () => {
  if (!store.items.length || store.count > 0) store.fetchItems()
}

const open = (n) => {
  if (!n.is_read) {
    n.is_read = true
    store._dropUnread(n.category)
    store.markCategoryRead(n.category)
  }
  if (n.link) router.push(n.link)
}

const markAll = () => store.markAllRead()

const accept = async (req) => {
  acting.value = req.id
  try {
    const convId = await store.accept(req)
    router.push(`/messages/${convId}`)
  } catch {
    $q.notify({ color: 'negative', message: 'Could not accept that request.', position: 'top' })
  } finally {
    acting.value = null
  }
}

const ignore = async (req) => {
  acting.value = req.id
  try {
    await store.dismiss(req.id)
  } catch {
    $q.notify({ color: 'negative', message: 'Could not dismiss that.', position: 'top' })
  } finally {
    acting.value = null
  }
}

const timeAgo = (iso) => {
  const s = Math.floor((Date.now() - new Date(iso)) / 1000)
  if (s < 60) return 'just now'
  const m = Math.floor(s / 60)
  if (m < 60) return `${m}m`
  const h = Math.floor(m / 60)
  if (h < 24) return `${h}h`
  const d = Math.floor(h / 24)
  if (d < 7) return `${d}d`
  return new Date(iso).toLocaleDateString('en-PK', { day: 'numeric', month: 'short' })
}
</script>

<style scoped>
.bell-badge {
  background: #e53935; color: #fff; font-weight: 700; font-size: 10px;
  padding: 2px 5px; min-height: 16px;
}

.nf-card { width: 380px; max-width: 92vw; }

.nf-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 13px 14px 11px; border-bottom: 1px solid #f0eaf4;
  background: linear-gradient(180deg, #faf7fc, #fff);
}
.nf-title {
  font-size: 15px; font-weight: 700;
  background: linear-gradient(135deg, #4a148c, #7b1fa2);
  -webkit-background-clip: text; background-clip: text; color: transparent;
}
.nf-clear { color: #9b8aa5; font-size: 12px; }

.nf-body { max-height: 62vh; overflow-y: auto; padding: 6px 0; }

.nf-section {
  padding: 8px 14px 4px; font-size: 10.5px; font-weight: 700;
  letter-spacing: 0.06em; text-transform: uppercase; color: #b0a3b8;
}
.nf-divider { height: 1px; background: #f0eaf4; margin: 6px 0; }

/* ── Message requests ── */
.nf-req { display: flex; gap: 10px; padding: 8px 14px; }
.nf-req-avatar {
  flex-shrink: 0; background: linear-gradient(135deg, #7b1fa2, #4a148c);
  color: #fff; font-weight: 700; font-size: 14px;
}
.nf-req-main { flex: 1; min-width: 0; }
.nf-req-name { font-size: 13px; font-weight: 600; color: #2b1b33; }
.nf-req-sub { font-size: 11.5px; color: #9b8aa5; }
.nf-req-msg {
  font-size: 12.5px; color: #4a3d52; margin-top: 3px;
  background: #f7f3fa; border-radius: 8px; padding: 5px 8px; white-space: normal;
}
.nf-req-actions { display: flex; gap: 6px; margin-top: 7px; }

/* ── Feed items ── */
.nf-item {
  display: flex; align-items: flex-start; gap: 10px; width: 100%; text-align: left;
  padding: 10px 14px; border: 0; background: none; cursor: pointer;
  border-left: 3px solid transparent;
  transition: background 0.13s ease;
}
.nf-item:hover { background: #faf7fc; }
.nf-item--unread { background: #faf6fd; }

.nf-badge {
  display: grid; place-items: center; width: 32px; height: 32px; flex-shrink: 0;
  border-radius: 10px; color: #fff;
}
/* Each type its own colour, so you read the kind before the words. */
.nf-badge--follow           { background: linear-gradient(135deg, #26a69a, #00897b); }
.nf-badge--comment          { background: linear-gradient(135deg, #ffb300, #fb8c00); }
.nf-badge--like             { background: linear-gradient(135deg, #ec407a, #d81b60); }
.nf-badge--message          { background: linear-gradient(135deg, #7b1fa2, #4a148c); }
.nf-badge--booking_request  { background: linear-gradient(135deg, #fb8c00, #ef6c00); }
.nf-badge--booking_approved { background: linear-gradient(135deg, #43a047, #2e7d32); }
.nf-badge--booking_paid     { background: linear-gradient(135deg, #43a047, #1b5e20); }
.nf-badge--trip_join        { background: linear-gradient(135deg, #26c6da, #0097a7); }

.nf-item--follow            { border-left-color: #26a69a; }
.nf-item--comment           { border-left-color: #fb8c00; }
.nf-item--like              { border-left-color: #d81b60; }
.nf-item--message           { border-left-color: #7b1fa2; }
.nf-item--booking_request   { border-left-color: #ef6c00; }
.nf-item--booking_approved,
.nf-item--booking_paid      { border-left-color: #2e7d32; }
.nf-item--trip_join         { border-left-color: #0097a7; }
/* Read items lose the coloured edge — the accent is the "new" signal. */
.nf-item:not(.nf-item--unread) { border-left-color: transparent; }

.nf-avatar {
  flex-shrink: 0; margin-left: -22px; margin-top: 14px;
  background: #efe4f6; color: #6a3f86; font-weight: 700; font-size: 12px;
  border: 2px solid #fff;
}
.nf-text { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.nf-item-title { font-size: 13px; color: #2b1b33; line-height: 1.35; }
.nf-item--unread .nf-item-title { font-weight: 600; }
.nf-item-body {
  font-size: 12px; color: #7a6a82; margin-top: 1px;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.nf-time { font-size: 11px; color: #b0a3b8; margin-top: 2px; }

.nf-unread-dot {
  width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 5px;
  background: #7b1fa2;
}

.nf-state {
  display: flex; flex-direction: column; align-items: center; gap: 6px;
  padding: 40px 24px; text-align: center; color: #b0a3b8;
}
.nf-empty-title { font-size: 13.5px; font-weight: 600; color: #6b5a75; }
.nf-empty-text { font-size: 12px; max-width: 28ch; line-height: 1.45; }
</style>
