<template>
  <q-page class="chat-page">
    <!-- Chat header -->
    <q-toolbar class="bg-primary text-white shadow-2" style="min-height: 50px;">
      <q-btn flat round dense icon="arrow_back" @click="$router.push(`/trips/${tripId}`)" />
      <q-avatar size="32px" color="white" text-color="primary" class="q-mx-sm text-weight-bold">
        {{ tripInitial }}
      </q-avatar>
      <q-toolbar-title class="text-weight-bold">Group Chat</q-toolbar-title>
    </q-toolbar>

    <!-- Messages list -->
    <div ref="messagesContainer" class="messages-area q-pa-md">
      <div v-if="chatStore.loading" class="row justify-center q-py-xl">
        <div class="column items-center q-gutter-sm">
          <q-skeleton v-for="n in 4" :key="n" type="rect" :width="n % 2 === 0 ? '65%' : '50%'" height="60px"
            :class="n % 2 === 0 ? 'self-end' : 'self-start'" />
        </div>
      </div>

      <template v-else>
        <div v-if="chatStore.messages.length === 0" class="text-center text-grey-5 q-py-xl">
          <q-icon name="chat_bubble_outline" size="3em" />
          <div class="text-body2 q-mt-sm">No messages yet. Say hello!</div>
        </div>

        <div v-for="msg in chatStore.messages" :key="msg.id" class="q-mb-sm">
          <!-- System message -->
          <div v-if="msg.type === 'system'" class="row justify-center q-my-xs">
            <q-chip dense color="grey-3" text-color="grey-7" class="text-caption">
              <em>{{ msg.body }}</em>
            </q-chip>
          </div>

          <!-- Own message -->
          <div v-else-if="msg.sender?.id === authStore.user?.id" class="row justify-end items-end q-gutter-xs">
            <div class="message-bubble own-bubble">
              <div class="text-caption text-purple-2 text-weight-bold q-mb-xs">{{ msg.sender?.name }}</div>
              <div class="message-body">{{ msg.body }}</div>
              <div class="text-caption text-right q-mt-xs" style="opacity: 0.7; font-size: 10px;">
                {{ formatTime(msg.created_at) }}
              </div>
            </div>
            <q-avatar size="28px" color="purple-3" text-color="white">
              <img v-if="msg.sender?.avatar" :src="msg.sender.avatar" />
              <span v-else class="text-caption text-weight-bold">{{ initial(msg.sender?.name) }}</span>
            </q-avatar>
          </div>

          <!-- Others' message — long-press / right-click for actions -->
          <div
            v-else
            class="row justify-start items-end q-gutter-xs"
            @contextmenu.prevent="openMsgMenu($event, msg)"
            @touchstart="startLongPress($event, msg)"
            @touchend="cancelLongPress"
            @touchmove="cancelLongPress"
          >
            <q-avatar size="28px" color="grey-4" text-color="grey-9">
              <img v-if="msg.sender?.avatar" :src="msg.sender.avatar" />
              <span v-else class="text-caption text-weight-bold">{{ initial(msg.sender?.name) }}</span>
            </q-avatar>
            <div class="message-bubble other-bubble">
              <div class="text-caption text-grey-7 text-weight-bold q-mb-xs">
                {{ msg.sender?.name }}
                <q-icon v-if="msg.sender?.is_verified" name="verified" color="deep-purple" size="10px" />
              </div>
              <div class="message-body">{{ msg.body }}</div>
              <div class="text-caption q-mt-xs text-grey-5" style="font-size: 10px;">
                {{ formatTime(msg.created_at) }}
              </div>
            </div>
          </div>
        </div>

        <div ref="bottomAnchor" />
      </template>
    </div>

    <!-- Message context menu -->
    <q-menu v-model="msgMenu.show" :target="msgMenu.target" context-menu>
      <q-list style="min-width: 180px">
        <q-item clickable v-close-popup @click="openReportMsg">
          <q-item-section avatar><q-icon name="flag" color="negative" /></q-item-section>
          <q-item-section class="text-negative">Report Message</q-item-section>
        </q-item>
        <q-item clickable v-close-popup @click="confirmBlockSender">
          <q-item-section avatar>
            <q-icon
              :name="safetyStore.isBlocked(msgMenu.activeMsg?.sender?.id) ? 'person_add' : 'block'"
              color="grey-7"
            />
          </q-item-section>
          <q-item-section>
            {{ safetyStore.isBlocked(msgMenu.activeMsg?.sender?.id) ? 'Unblock User' : 'Block User' }}
          </q-item-section>
        </q-item>
      </q-list>
    </q-menu>

    <!-- Input bar -->
    <div class="input-bar bg-white shadow-up-2">
      <transition name="limit-fade">
        <div v-if="atLimit" class="limit-banner row items-center no-wrap">
          <q-icon name="info" size="16px" class="q-mr-xs" />
          <span>
            You've reached the <strong>{{ MESSAGE_MAX.toLocaleString() }}</strong>-character limit.
            Send this and continue in a new message.
          </span>
        </div>
      </transition>

      <div class="row items-center q-pa-sm q-gutter-xs">
        <q-input
          v-model="inputText"
          class="col"
          outlined
          dense
          placeholder="Type a message..."
          :disable="sending"
          autogrow
          @keyup.enter.prevent="sendMsg"
          @paste="onPaste"
          :maxlength="MESSAGE_MAX"
          style="max-height: 120px;"
        />
        <q-btn
          round
          color="primary"
          icon="send"
          unelevated
          @click="sendMsg"
          :loading="sending"
          :disable="!inputText.trim() || sending"
        />
      </div>
    </div>

    <!-- Report message dialog -->
    <ReportDialog
      v-if="msgMenu.activeMsg"
      v-model="reportMsgDialog"
      :reported-id="msgMenu.activeMsg.id"
      reported-type="message"
    />

    <!-- Block confirmation dialog -->
    <q-dialog v-model="blockConfirmDialog">
      <q-card style="min-width: 280px">
        <q-card-section>
          <div class="text-h6">Block {{ msgMenu.activeMsg?.sender?.name }}?</div>
        </q-card-section>
        <q-card-section class="q-pt-none text-body2 text-grey-8">
          Their messages will be hidden from you in all trips.
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn unelevated color="negative" label="Block" @click="doBlockSender" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useChatStore } from 'src/stores/chatStore'
import { useAuthStore } from 'src/stores/authStore'
import { useSafetyStore } from 'src/stores/safetyStore'
import ReportDialog from 'src/components/ReportDialog.vue'
import { MESSAGE_MAX, clampMessage, overflowFromPaste, notifyTrimmed } from 'src/utils/messageLimit'

const route = useRoute()
const router = useRouter() // eslint-disable-line no-unused-vars
const $q = useQuasar()
const chatStore = useChatStore()
const authStore = useAuthStore()
const safetyStore = useSafetyStore()

const tripId = computed(() => route.params.id)
const tripInitial = computed(() => {
  const name = route.query.name || 'T'
  return name[0].toUpperCase()
})

const inputText = ref('')
const sending = ref(false)

const atLimit = computed(() => inputText.value.length >= MESSAGE_MAX)

// Native maxlength truncates the paste; we only tell the user what was lost.
const onPaste = (e) => {
  const pasted = e.clipboardData?.getData('text') ?? ''
  const el = e.target
  const selectionLength = (el?.selectionEnd ?? 0) - (el?.selectionStart ?? 0)
  const removed = overflowFromPaste(pasted, inputText.value.length, selectionLength)
  if (removed > 0) notifyTrimmed($q, removed)
}

// Defensive: keep the model within the limit even if set programmatically.
watch(inputText, (val) => {
  if (val && val.length > MESSAGE_MAX) inputText.value = clampMessage(val)
})
const messagesContainer = ref(null)
const bottomAnchor = ref(null)

// Message context menu state
const msgMenu = ref({ show: false, target: null, activeMsg: null })
const reportMsgDialog = ref(false)
const blockConfirmDialog = ref(false)

// Long-press timer
let longPressTimer = null

const scrollToBottom = () => {
  nextTick(() => {
    bottomAnchor.value?.scrollIntoView({ behavior: 'smooth' })
  })
}

watch(() => chatStore.messages.length, scrollToBottom)

onMounted(async () => {
  // Needed so the message menu can show Block vs Unblock correctly.
  if (authStore.isLoggedIn) safetyStore.fetchBlockList()

  chatStore.messages = []
  await chatStore.fetchMessages(tripId.value)
  chatStore.subscribeToTrip(tripId.value)
  scrollToBottom()
})

onUnmounted(() => {
  chatStore.unsubscribeFromTrip(tripId.value)
  cancelLongPress()
})

const sendMsg = async () => {
  const body = inputText.value.trim()
  if (!body || sending.value) return
  sending.value = true
  try {
    await chatStore.sendMessage(tripId.value, body)
    inputText.value = ''
  } catch (err) {
    const isValidation = err.response?.status === 422
    $q.notify({
      icon: isValidation ? 'rule' : 'error',
      color: isValidation ? 'amber-8' : 'negative',
      textColor: 'white',
      message: 'Message not sent',
      caption: isValidation
        ? err.response?.data?.errors?.body?.[0] ||
          err.response?.data?.message ||
          `Messages are limited to ${MESSAGE_MAX.toLocaleString()} characters.`
        : err.response?.data?.message || 'Check your connection and try again.',
      position: 'top',
      timeout: 5000,
    })
  } finally {
    sending.value = false
  }
}

// Right-click (desktop)
const openMsgMenu = (event, msg) => {
  msgMenu.value.target = event.target
  msgMenu.value.activeMsg = msg
  msgMenu.value.show = true
}

// Long-press (mobile)
const startLongPress = (event, msg) => {
  longPressTimer = setTimeout(() => {
    msgMenu.value.target = event.target
    msgMenu.value.activeMsg = msg
    msgMenu.value.show = true
  }, 500)
}

const cancelLongPress = () => {
  if (longPressTimer) {
    clearTimeout(longPressTimer)
    longPressTimer = null
  }
}

const openReportMsg = () => {
  reportMsgDialog.value = true
}

const confirmBlockSender = () => {
  // Unblocking is harmless — only confirm the destructive direction.
  if (safetyStore.isBlocked(msgMenu.value.activeMsg?.sender?.id)) {
    doBlockSender()
    return
  }
  blockConfirmDialog.value = true
}

const doBlockSender = async () => {
  blockConfirmDialog.value = false
  const sender = msgMenu.value.activeMsg?.sender
  if (!sender?.id) return
  try {
    const result = await safetyStore.toggleBlock(sender.id, sender)
    $q.notify({
      color: 'info',
      icon: result.blocked ? 'block' : 'person_add',
      message: result.message,
    })
  } catch {
    $q.notify({ color: 'negative', message: 'Action failed' })
  }
}

const initial = (name) => {
  if (!name) return '?'
  return name[0].toUpperCase()
}

const formatTime = (dateStr) => {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleTimeString('en-PK', { hour: '2-digit', minute: '2-digit' })
}
</script>

<style scoped>
.chat-page {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 50px);
}

.messages-area {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

.input-bar {
  flex-shrink: 0;
  border-top: 1px solid #e0e0e0;
}

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

.message-bubble {
  max-width: 70%;
  border-radius: 12px;
  padding: 8px 12px;
  word-break: break-word;
  overflow-wrap: anywhere;
}

.own-bubble {
  background: #7b1fa2;
  color: white;
  border-bottom-right-radius: 4px;
}

.other-bubble {
  background: #f5f5f5;
  color: #212121;
  border-bottom-left-radius: 4px;
}

.message-body {
  font-size: 14px;
  line-height: 1.4;
}
</style>
