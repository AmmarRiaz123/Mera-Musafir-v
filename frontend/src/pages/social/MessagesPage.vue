<template>
  <q-page padding>
    <div class="text-h4 text-weight-bold q-mb-lg">Messages</div>

    <!-- Loading -->
    <div v-if="socialStore.convsLoading">
      <q-item v-for="n in 5" :key="n" class="q-mb-sm">
        <q-item-section avatar><q-skeleton type="QAvatar" /></q-item-section>
        <q-item-section>
          <q-skeleton type="text" width="40%" />
          <q-skeleton type="text" width="70%" />
        </q-item-section>
      </q-item>
    </div>

    <!-- Empty state -->
    <div v-else-if="socialStore.conversations.length === 0" class="text-center q-py-xl">
      <q-icon name="chat_bubble_outline" size="64px" color="grey-5" />
      <div class="text-h6 text-grey-7 q-mt-md">No conversations yet</div>
      <div class="text-grey-6 q-mb-lg">Find people to chat with</div>
      <q-btn color="primary" label="Discover People" to="/people" unelevated rounded />
    </div>

    <!-- Conversation list -->
    <q-list v-else separator bordered class="rounded-borders">
      <q-item
        v-for="conv in sorted"
        :key="conv.id"
        clickable
        v-ripple
        @click="$router.push(`/messages/${conv.id}`)"
        :class="{ 'bg-purple-1': conv.unread_count > 0 }"
      >
        <q-item-section avatar>
          <q-avatar size="48px">
            <img v-if="conv.other_user.avatar" :src="conv.other_user.avatar" />
            <q-icon v-else name="person" size="24px" color="grey-5" />
          </q-avatar>
        </q-item-section>

        <q-item-section>
          <q-item-label class="row items-center q-gutter-xs">
            <span :class="{ 'text-weight-bold': conv.unread_count > 0 }">{{ conv.other_user.name }}</span>
            <q-icon v-if="conv.other_user.is_verified" name="verified" color="deep-purple" size="14px" />
          </q-item-label>
          <q-item-label caption lines="1">
            <span v-if="conv.last_message">
              <span v-if="conv.last_message.is_mine" class="text-grey-6">You: </span>
              <span v-if="conv.last_message.type === 'trip_invite'">
                <q-icon name="card_travel" size="xs" /> Trip Invite
              </span>
              <span v-else>{{ conv.last_message.body }}</span>
            </span>
            <span v-else class="text-grey-5">No messages yet</span>
          </q-item-label>
        </q-item-section>

        <q-item-section side top>
          <q-item-label caption>{{ formatTime(conv.last_message_at) }}</q-item-label>
          <q-badge v-if="conv.unread_count > 0" color="deep-purple" :label="conv.unread_count" rounded />
        </q-item-section>
      </q-item>
    </q-list>
  </q-page>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useSocialStore } from 'src/stores/socialStore'

const socialStore = useSocialStore()

const sorted = computed(() =>
  [...socialStore.conversations].sort((a, b) => {
    if (!a.last_message_at) return 1
    if (!b.last_message_at) return -1
    return new Date(b.last_message_at) - new Date(a.last_message_at)
  }),
)

const formatTime = (ts) => {
  if (!ts) return ''
  const d = new Date(ts)
  const now = new Date()
  const diff = now - d
  if (diff < 86400000) return d.toLocaleTimeString('en-PK', { hour: '2-digit', minute: '2-digit' })
  return d.toLocaleDateString('en-PK', { day: 'numeric', month: 'short' })
}

onMounted(() => socialStore.fetchConversations())
</script>
