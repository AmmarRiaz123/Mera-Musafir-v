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
          <!-- Trip invite card -->
          <div v-if="msg.type === 'trip_invite'" class="invite-card" style="max-width: 320px">
            <q-card bordered flat>
              <q-card-section class="q-pb-xs">
                <div class="row items-center q-gutter-xs q-mb-xs">
                  <q-icon name="card_travel" color="deep-purple" />
                  <span class="text-caption text-grey-6">Trip Invite</span>
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

              <!-- Accept / Decline (recipient only, pending only) -->
              <q-card-actions v-if="!isMine(msg) && msg.metadata?.status === 'pending'" align="right">
                <q-btn flat dense color="negative" label="Decline" @click="respond(msg, 'decline')" />
                <q-btn unelevated dense color="primary" label="Accept" @click="respond(msg, 'accept')" />
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
    <div v-else class="row items-center q-pa-sm bg-white shadow-up-2">
      <q-input
        v-model="newMessage"
        outlined
        dense
        rounded
        class="col q-mr-sm"
        placeholder="Type a message..."
        @keyup.enter="sendMsg"
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
    } else {
      $q.notify({ type: 'negative', message: 'Failed to send message', position: 'top' })
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
    $q.notify({ type: 'negative', message: e.response?.data?.message || 'Failed', position: 'top' })
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
    $q.notify({
      type: 'negative',
      message: e.response?.data?.message || 'Failed to send invite',
      position: 'top',
    })
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

  // Fetch user's joined trips for invite dialog
  try {
    const res = await api.get('/api/v1/trips/my')
    myTrips.value = (res.data.data || []).filter((t) => t.status === 'joined' || t.role === 'host')
  } catch { /* non-critical — trip list is optional for invite dialog */ }
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
</style>
