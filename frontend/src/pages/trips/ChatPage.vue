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

          <!-- Others' message -->
          <div v-else class="row justify-start items-end q-gutter-xs">
            <q-avatar size="28px" color="grey-4" text-color="grey-9">
              <img v-if="msg.sender?.avatar" :src="msg.sender.avatar" />
              <span v-else class="text-caption text-weight-bold">{{ initial(msg.sender?.name) }}</span>
            </q-avatar>
            <div class="message-bubble other-bubble">
              <div class="text-caption text-grey-7 text-weight-bold q-mb-xs">{{ msg.sender?.name }}</div>
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

    <!-- Input bar -->
    <div class="input-bar row items-center q-pa-sm q-gutter-xs bg-white shadow-up-2">
      <q-input
        v-model="inputText"
        class="col"
        outlined
        dense
        placeholder="Type a message..."
        :disable="sending"
        autogrow
        @keyup.enter.prevent="sendMsg"
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
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useChatStore } from 'src/stores/chatStore'
import { useAuthStore } from 'src/stores/authStore'

const route = useRoute()
const router = useRouter() // eslint-disable-line no-unused-vars
const $q = useQuasar()
const chatStore = useChatStore()
const authStore = useAuthStore()

const tripId = computed(() => route.params.id)
const tripInitial = computed(() => {
  const name = route.query.name || 'T'
  return name[0].toUpperCase()
})

const inputText = ref('')
const sending = ref(false)
const messagesContainer = ref(null)
const bottomAnchor = ref(null)

const scrollToBottom = () => {
  nextTick(() => {
    bottomAnchor.value?.scrollIntoView({ behavior: 'smooth' })
  })
}

watch(() => chatStore.messages.length, scrollToBottom)

onMounted(async () => {
  chatStore.messages = []
  await chatStore.fetchMessages(tripId.value)
  chatStore.subscribeToTrip(tripId.value)
  scrollToBottom()
})

onUnmounted(() => {
  chatStore.unsubscribeFromTrip(tripId.value)
})

const sendMsg = async () => {
  const body = inputText.value.trim()
  if (!body || sending.value) return
  sending.value = true
  try {
    await chatStore.sendMessage(tripId.value, body)
    inputText.value = ''
  } catch (err) {
    const msg = err.response?.data?.message || 'Could not send message'
    $q.notify({ color: 'negative', message: msg, icon: 'error' })
  } finally {
    sending.value = false
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

.message-bubble {
  max-width: 70%;
  border-radius: 12px;
  padding: 8px 12px;
  word-break: break-word;
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
