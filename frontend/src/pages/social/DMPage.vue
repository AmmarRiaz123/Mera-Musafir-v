<template>
  <q-page class="column" style="height: calc(100vh - 50px)">
    <!-- Header -->
    <div class="row items-center q-pa-md bg-white shadow-1">
      <q-btn flat round dense icon="arrow_back" @click="$router.push('/messages')" class="q-mr-sm" />
      <q-avatar size="40px" class="q-mr-sm cursor-pointer" @click="goToProfile">
        <img v-if="other?.avatar" :src="other.avatar" />
        <q-icon v-else name="person" size="20px" color="grey-5" />
      </q-avatar>
      <div class="cursor-pointer" @click="goToProfile">
        <div class="row items-center q-gutter-xs">
          <span class="text-subtitle1 text-weight-bold">{{ other?.name }}</span>
          <q-icon v-if="other?.is_verified" name="verified" color="deep-purple" size="16px" />
        </div>
      </div>
      <q-space />
      <q-btn
        v-if="!conv?.blocked_by_me && !conv?.blocked_by_them"
        flat round dense icon="card_travel" color="deep-purple"
        @click="inviteDialog = true"
      >
        <q-tooltip>Send Trip Invite</q-tooltip>
      </q-btn>
    </div>

    <!-- Messages area -->
    <div class="col overflow-auto q-pa-md" ref="msgContainer">
      <!-- Wait for auth to load before rendering: alignment depends on it -->
      <div v-if="socialStore.messagesLoading || !authStore.user" class="column items-center q-py-xl">
        <q-spinner size="40px" color="primary" />
      </div>

      <div v-else-if="socialStore.messages.length === 0" class="text-center text-grey-5 q-py-xl">
        Say hello!
      </div>

      <div v-else>
        <div
          v-for="msg in socialStore.messages"
          :key="msg.id"
          class="q-mb-sm"
          :class="isMine(msg) ? 'row justify-end' : 'row justify-start'"
        >
          <!-- Shared community post -->
          <div v-if="msg.type === 'post_share'" class="share-wrap">
            <!-- Blocked the author: don't show what they posted -->
            <div v-if="msg.metadata?.unavailable" class="share-card share-card--gone">
              <q-icon name="visibility_off" size="20px" />
              <div>
                <div class="gone-title">Post unavailable</div>
                <div class="gone-sub">You've blocked the person who posted this.</div>
              </div>
            </div>

            <div v-else class="share-card" @click="openSharedPost(msg)">
              <!-- Big media preview, like a link card -->
              <div v-if="msg.metadata?.media_url" class="sc-hero">
                <img v-if="msg.metadata?.media_type !== 'video'" :src="msg.metadata.media_url" alt="" />
                <div v-else class="sc-video"><q-icon name="play_circle" size="34px" /></div>
                <span v-if="msg.metadata?.media_count > 1" class="sc-count">
                  <q-icon name="collections" size="11px" />{{ msg.metadata.media_count }}
                </span>
              </div>

              <div class="sc-body">
                <div class="sc-label"><q-icon name="forum" size="11px" />Community post</div>

                <div class="sc-author">
                  <q-avatar size="20px" class="sc-avatar">
                    <img v-if="msg.metadata?.author_avatar" :src="msg.metadata.author_avatar" />
                    <span v-else>{{ msg.metadata?.author_name?.[0]?.toUpperCase() }}</span>
                  </q-avatar>
                  <span class="sc-name">{{ msg.metadata?.author_name }}</span>
                </div>

                <div class="sc-excerpt">{{ msg.metadata?.excerpt }}</div>

                <!-- Track preview -->
                <div v-if="msg.metadata?.audio" class="sc-audio">
                  <q-avatar size="24px" rounded class="sc-audio-cover">
                    <img v-if="msg.metadata.audio.cover" :src="msg.metadata.audio.cover" />
                    <q-icon v-else name="music_note" size="13px" />
                  </q-avatar>
                  <span class="sc-audio-text">
                    {{ msg.metadata.audio.title }} · {{ msg.metadata.audio.artist }}
                  </span>
                  <q-icon name="graphic_eq" size="13px" />
                </div>

                <div class="sc-foot">
                  <span v-if="msg.metadata?.destination" class="sc-dest">
                    <q-icon name="place" size="11px" />{{ msg.metadata.destination }}
                  </span>
                  <span class="sc-open">View post<q-icon name="chevron_right" size="14px" /></span>
                </div>
              </div>
            </div>

            <div v-if="msg.body && msg.body !== 'Shared a post'" class="share-note">{{ msg.body }}</div>
            <div class="text-caption text-grey-5 q-mt-xs" :class="isMine(msg) ? 'text-right' : 'text-left'">
              {{ formatTime(msg.created_at) }}
            </div>
          </div>

          <!-- Trip invite card -->
          <div v-else-if="msg.type === 'trip_invite'" class="invite-card" style="max-width: 320px">
            <q-card bordered flat>
              <q-card-section class="q-pb-xs">
                <div class="row items-center q-gutter-xs q-mb-xs">
                  <q-icon name="card_travel" color="deep-purple" />
                  <span class="text-caption text-grey-6">Trip Invite</span>
                  <q-badge
                    v-if="msg.metadata?.visibility === 'women_only'"
                    class="women-only-badge"
                    align="middle"
                  >
                    <q-icon name="female" size="12px" class="q-mr-xs" />Women Only
                  </q-badge>
                </div>
                <div class="text-subtitle2 text-weight-bold">{{ msg.metadata?.trip_title }}</div>
                <div class="text-caption text-grey-6">
                  <q-icon name="place" size="xs" /> {{ msg.metadata?.destination_name }}
                </div>
                <div class="text-caption text-grey-6 q-mt-xs">
                  {{ formatDate(msg.metadata?.start_date) }} – {{ formatDate(msg.metadata?.end_date) }}
                  &nbsp;·&nbsp; {{ msg.metadata?.spots_left }} spots left
                </div>
              </q-card-section>

              <!-- Status indicator -->
              <q-card-section class="q-pt-none" v-if="msg.metadata?.status !== 'pending'">
                <q-badge
                  :color="msg.metadata?.status === 'accepted' ? 'positive' : 'grey'"
                  :label="msg.metadata?.status === 'accepted' ? 'Accepted' : 'Declined'"
                />
              </q-card-section>

              <!-- Not eligible: women-only trip, and this account isn't a woman -->
              <q-card-section
                v-if="!isMine(msg) && msg.metadata?.status === 'pending' && !canJoinInvite(msg)"
                class="q-pt-none"
              >
                <div class="women-only-note">
                  <q-icon name="favorite" size="14px" class="q-mr-xs" />
                  <span>
                    This trip is just for women travellers — you can't join this one, but you can
                    cheer them on.
                  </span>
                </div>
              </q-card-section>

              <!-- Accept / Decline (eligible recipient, pending only) -->
              <q-card-actions
                v-if="!isMine(msg) && msg.metadata?.status === 'pending' && canJoinInvite(msg)"
                align="right"
              >
                <q-btn flat dense color="negative" label="Decline" @click="respond(msg, 'decline')" />
                <q-btn unelevated dense color="primary" label="Accept" @click="respond(msg, 'accept')" />
              </q-card-actions>

              <!-- Ineligible users can still decline to clear it -->
              <q-card-actions
                v-else-if="!isMine(msg) && msg.metadata?.status === 'pending'"
                align="right"
              >
                <q-btn flat dense color="grey-7" label="Dismiss" @click="respond(msg, 'decline')" />
              </q-card-actions>
            </q-card>
            <div class="text-caption text-grey-5 q-mt-xs" :class="isMine(msg) ? 'text-right' : 'text-left'">
              {{ formatTime(msg.created_at) }}
            </div>
          </div>

          <!-- Regular text bubble -->
          <div v-else style="max-width: 70%">
            <div
              class="q-pa-sm q-px-md rounded-borders text-body2"
              :class="isMine(msg)
                ? 'bg-primary text-white'
                : 'bg-grey-2 text-dark'"
              style="border-radius: 18px; word-break: break-word; overflow-wrap: anywhere;"
            >
              {{ msg.body }}
            </div>
            <div class="text-caption text-grey-5 q-mt-xs" :class="isMine(msg) ? 'text-right' : 'text-left'">
              {{ formatTime(msg.created_at) }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- You blocked them -->
    <div v-if="conv?.blocked_by_me" class="dm-blocked-bar row items-center justify-center q-gutter-sm">
      <span>You blocked this user.</span>
      <q-btn flat dense label="Unblock" color="primary" :loading="unblocking" @click="unblockUser" />
    </div>

    <!-- They blocked you -->
    <div v-else-if="conv?.blocked_by_them" class="dm-blocked-bar row items-center justify-center">
      <span>You can't reply to this conversation.</span>
    </div>

    <!-- DM privacy prevents sending (surfaced on send attempt) -->
    <div v-else-if="privacyError" class="dm-blocked-bar row items-center justify-center">
      <span>{{ privacyError }}</span>
    </div>

    <!-- Normal input bar -->
    <div v-else class="bg-white shadow-up-2">
      <transition name="limit-fade">
        <div v-if="atLimit" class="limit-banner row items-center no-wrap">
          <q-icon name="info" size="16px" class="q-mr-xs" />
          <span>
            You've reached the <strong>{{ MESSAGE_MAX.toLocaleString() }}</strong>-character limit.
            Send this and continue in a new message.
          </span>
        </div>
      </transition>

      <div class="row items-center q-pa-sm">
        <q-input
          v-model="newMessage"
          outlined
          dense
          rounded
          class="col q-mr-sm"
          placeholder="Type a message..."
          @keyup.enter="sendMsg"
          @paste="onPaste"
          :maxlength="MESSAGE_MAX"
          autogrow
          :rows="1"
          style="max-height: 120px; overflow-y: auto"
        />
        <q-btn
          round
          unelevated
          color="primary"
          icon="send"
          :loading="sending"
          @click="sendMsg"
          :disable="!newMessage.trim()"
        />
      </div>
    </div>

    <!-- Trip invite dialog -->
    <q-dialog v-model="inviteDialog">
      <q-card style="min-width: 320px">
        <q-card-section>
          <div class="text-h6">Send Trip Invite</div>
        </q-card-section>
        <q-card-section>
          <q-select
            v-model="selectedTrip"
            :options="myTrips"
            option-label="title"
            option-value="id"
            label="Select a trip you're in"
            outlined
            dense
            emit-value
            map-options
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" @click="inviteDialog = false" />
          <q-btn unelevated color="deep-purple" label="Send Invite" :disable="!selectedTrip" @click="sendInvite" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { api } from 'src/boot/axios'
import { useSocialStore } from 'src/stores/socialStore'
import { useSafetyStore } from 'src/stores/safetyStore'
import { useAuthStore } from 'src/stores/authStore'
import { MESSAGE_MAX, clampMessage, overflowFromPaste, notifyTrimmed } from 'src/utils/messageLimit'
import { notifyError } from 'src/utils/notify'

const route = useRoute()
const router = useRouter()
const $q = useQuasar()
const socialStore = useSocialStore()
const safetyStore = useSafetyStore()
const authStore = useAuthStore()

const newMessage = ref('')
const sending = ref(false)
const msgContainer = ref(null)
const inviteDialog = ref(false)
const selectedTrip = ref(null)
const myTrips = ref([])
const privacyError = ref('')
const unblocking = ref(false)

const atLimit = computed(() => newMessage.value.length >= MESSAGE_MAX)

// Women-only trips can only be joined by women — mirrors the backend guard so
// we never show an Accept button that is guaranteed to fail.
const openSharedPost = (msg) => {
  const id = msg.metadata?.post_id
  if (id) router.push(`/community?post=${id}`)
}

const canJoinInvite = (msg) => {
  if (msg.metadata?.visibility !== 'women_only') return true
  return authStore.user?.gender === 'female'
}

// Native maxlength truncates the paste; we only tell the user what was lost.
const onPaste = (e) => {
  const pasted = e.clipboardData?.getData('text') ?? ''
  const el = e.target
  const selectionLength = (el?.selectionEnd ?? 0) - (el?.selectionStart ?? 0)
  const removed = overflowFromPaste(pasted, newMessage.value.length, selectionLength)
  if (removed > 0) notifyTrimmed($q, removed)
}

// Defensive: keep the model within the limit even if set programmatically.
watch(newMessage, (val) => {
  if (val && val.length > MESSAGE_MAX) newMessage.value = clampMessage(val)
})

const conversationId = computed(() => parseInt(route.params.id))
const conv = computed(() => socialStore.activeConversation)
const other = computed(() => socialStore.activeConversation?.other_user)
const currentUserId = computed(() => parseInt(authStore.user?.id))

// Resolve sender identity robustly: HTTP payloads carry is_mine, while
// real-time Echo payloads only carry sender.id. parseInt guards string/int mismatch.
const isMine = (msg) => {
  if (typeof msg.is_mine === 'boolean') return msg.is_mine
  return parseInt(msg.sender?.id) === currentUserId.value
}

const scrollToBottom = () => {
  nextTick(() => {
    if (msgContainer.value) {
      msgContainer.value.scrollTop = msgContainer.value.scrollHeight
    }
  })
}

const sendMsg = async () => {
  if (!newMessage.value.trim() || sending.value) return
  sending.value = true
  const body = newMessage.value.trim()
  newMessage.value = ''
  try {
    await socialStore.sendMessage(conversationId.value, { body, type: 'text' })
    privacyError.value = ''
    scrollToBottom()
  } catch (e) {
    newMessage.value = body
    if (e.response?.status === 403) {
      // Privacy or block — surface the reason and lock the input bar.
      privacyError.value = e.response?.data?.message || 'You can no longer message this user'
      // A block may have happened since load — refresh so the right bar shows.
      socialStore.fetchConversation(conversationId.value)
    } else if (e.response?.status === 422) {
      // Validation (e.g. length) — say exactly what's wrong.
      $q.notify({
        icon: 'rule',
        color: 'amber-8',
        textColor: 'white',
        message: 'Message not sent',
        caption:
          e.response?.data?.errors?.body?.[0] ||
          e.response?.data?.message ||
          `Messages are limited to ${MESSAGE_MAX.toLocaleString()} characters.`,
        position: 'top',
        timeout: 5000,
      })
    } else {
      $q.notify({
        icon: 'wifi_off',
        color: 'negative',
        message: 'Message not sent',
        caption: e.response?.data?.message || 'Check your connection and try again.',
        position: 'top',
        timeout: 5000,
      })
    }
  } finally {
    sending.value = false
  }
}

const unblockUser = async () => {
  if (!other.value?.id) return
  unblocking.value = true
  try {
    await safetyStore.toggleBlock(other.value.id)
    await socialStore.fetchConversation(conversationId.value)
    privacyError.value = ''
    $q.notify({ type: 'positive', message: 'User unblocked', position: 'top' })
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to unblock', position: 'top' })
  } finally {
    unblocking.value = false
  }
}

const respond = async (msg, action) => {
  try {
    const res = await socialStore.respondToInvite(conversationId.value, msg.id, action)
    $q.notify({ type: 'positive', message: res.message, position: 'top' })
  } catch (e) {
    notifyError($q, e, 'Could not respond to the invite')
  }
}

const sendInvite = async () => {
  if (!selectedTrip.value) return
  inviteDialog.value = false
  try {
    await socialStore.sendMessage(conversationId.value, {
      type: 'trip_invite',
      trip_id: selectedTrip.value,
    })
    $q.notify({ type: 'positive', message: 'Trip invite sent!', position: 'top' })
    scrollToBottom()
  } catch (e) {
    notifyError($q, e, 'Failed to send invite')
  }
}

const goToProfile = () => {
  if (other.value?.id) router.push(`/profile/${other.value.id}`)
}

const formatTime = (ts) => {
  if (!ts) return ''
  const d = new Date(ts)
  return d.toLocaleTimeString('en-PK', { hour: '2-digit', minute: '2-digit' })
}

const formatDate = (dateStr) => {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('en-PK', { day: 'numeric', month: 'short' })
}

watch(() => socialStore.messages.length, () => scrollToBottom())

onMounted(async () => {
  await socialStore.fetchConversation(conversationId.value)
  socialStore.subscribeToConversation(conversationId.value)
  scrollToBottom()

  // Trips the user can invite someone to. /trips/my returns
  // { created: [...], joined: [...] } — both qualify, since the creator is
  // stored as a 'joined' host member, which is what the invite endpoint checks.
  try {
    const res = await api.get('/api/v1/trips/my')
    const { created = [], joined = [] } = res.data.data || {}
    const seen = new Set()
    myTrips.value = [...created, ...joined].filter((t) => {
      if (!t || seen.has(t.id)) return false
      seen.add(t.id)
      return true
    })
  } catch (e) {
    console.error('Could not load trips for invite dialog', e)
  }
})

onUnmounted(() => {
  socialStore.unsubscribeFromConversation(conversationId.value)
})
</script>

<style scoped>
.shadow-up-2 { box-shadow: 0 -2px 8px rgba(0,0,0,0.1); }
.dm-blocked-bar {
  padding: 16px;
  background: #f5f5f5;
  color: #757575;
  font-size: 14px;
  box-shadow: 0 -2px 8px rgba(0,0,0,0.1);
}

/* ── Shared community post ──────────────────────────── */
.share-wrap { max-width: 330px; }
.share-card {
  border-radius: 14px; overflow: hidden; cursor: pointer;
  background: #fff; border: 1px solid #e6dcee;
  transition: border-color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
}
.share-card:hover {
  border-color: #c9b3d6; transform: translateY(-1px);
  box-shadow: 0 4px 16px rgba(43, 27, 51, 0.09);
}
.share-card--gone {
  display: flex; align-items: center; gap: 10px; padding: 12px;
  cursor: default; background: #f5f2f7; border-color: #e8e0ee; color: #8a7a92;
}

.sc-hero { position: relative; height: 150px; background: #1a1020; line-height: 0; }
.sc-hero img { width: 100%; height: 100%; object-fit: cover; display: block; }
.sc-video {
  width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, #4a148c, #1a1020); color: rgba(255,255,255,0.85);
}
.sc-count {
  position: absolute; top: 8px; right: 8px;
  display: inline-flex; align-items: center; gap: 3px;
  padding: 2px 7px; border-radius: 999px;
  background: rgba(20, 10, 26, 0.68); color: #fff; font-size: 10px; line-height: 1.4;
}

.sc-body { padding: 10px 12px 11px; }
.sc-label {
  display: inline-flex; align-items: center; gap: 3px;
  font-size: 9px; font-weight: 700; letter-spacing: 0.06em;
  text-transform: uppercase; color: #9b8aa5;
}
.sc-author { display: flex; align-items: center; gap: 6px; margin-top: 4px; }
.sc-avatar {
  background: linear-gradient(135deg, #7b1fa2, #4a148c);
  color: #fff; font-size: 9px; font-weight: 700;
}
.sc-name { font-size: 12.5px; font-weight: 600; color: #2b1b33; }
.sc-excerpt {
  font-size: 12px; color: #6b5a75; line-height: 1.4; margin-top: 4px;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}

.sc-audio {
  display: flex; align-items: center; gap: 7px; margin-top: 8px;
  padding: 5px 8px; border-radius: 8px;
  background: linear-gradient(120deg, #f7f0fa, #efe7f6); color: #4a148c;
}
.sc-audio-cover { background: #4a148c; color: #fff; flex-shrink: 0; }
.sc-audio-text {
  flex: 1; min-width: 0; font-size: 11px; font-weight: 500;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

.sc-foot {
  display: flex; align-items: center; justify-content: space-between;
  gap: 8px; margin-top: 9px; padding-top: 8px; border-top: 1px solid #f4eff7;
}
.sc-dest {
  display: inline-flex; align-items: center; gap: 2px;
  font-size: 11px; color: #7a6a82; font-weight: 500;
}
.sc-open {
  display: inline-flex; align-items: center; gap: 1px; margin-left: auto;
  font-size: 11.5px; font-weight: 600; color: var(--q-primary);
}
.share-card--gone:hover { transform: none; border-color: #e8e0ee; }
.gone-title { font-size: 12.5px; font-weight: 600; color: #6b5a75; }
.gone-sub { font-size: 11.5px; color: #9b8aa5; }

.share-media {
  width: 46px; height: 46px; border-radius: 9px; flex-shrink: 0; overflow: hidden;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, #7b1fa2, #4a148c); color: #fff;
}
.share-media img { width: 100%; height: 100%; object-fit: cover; }
.share-text { flex: 1; min-width: 0; }
.share-label {
  display: inline-flex; align-items: center; gap: 3px;
  font-size: 9.5px; font-weight: 700; letter-spacing: 0.05em;
  text-transform: uppercase; color: #9b8aa5;
}
.share-author { font-size: 12.5px; font-weight: 600; color: #2b1b33; }
.share-excerpt {
  font-size: 12px; color: #7a6a82; line-height: 1.35; margin-top: 1px;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.share-dest {
  display: inline-flex; align-items: center; gap: 2px;
  font-size: 11px; color: var(--q-primary); margin-top: 3px; font-weight: 500;
}
.share-arrow { color: #c3b3cc; flex-shrink: 0; }
.share-note { font-size: 13px; color: #3a2d42; margin-top: 6px; }

/* Women-only trip accents */
.women-only-badge {
  background: linear-gradient(135deg, #d81b60 0%, #8e24aa 100%);
  color: #fff;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.02em;
  padding: 3px 8px;
  border-radius: 999px;
}

.women-only-note {
  display: flex;
  align-items: flex-start;
  gap: 2px;
  padding: 8px 10px;
  border-radius: 8px;
  background: linear-gradient(135deg, #fce4ec 0%, #f3e5f5 100%);
  border: 1px solid #f8bbd0;
  color: #880e4f;
  font-size: 12px;
  line-height: 1.4;
}
.women-only-note .q-icon { color: #d81b60; margin-top: 2px; }

/* Shown only once the character cap is actually reached. */
.limit-banner {
  padding: 8px 16px;
  background: linear-gradient(90deg, #f3e5f5 0%, #ede7f6 100%);
  color: #5e35b1;
  font-size: 12.5px;
  line-height: 1.35;
  border-bottom: 1px solid rgba(94, 53, 177, 0.15);
}
.limit-banner strong { font-weight: 600; }

.limit-fade-enter-active,
.limit-fade-leave-active { transition: opacity 0.18s ease, transform 0.18s ease; }
.limit-fade-enter-from,
.limit-fade-leave-to { opacity: 0; transform: translateY(4px); }
</style>
